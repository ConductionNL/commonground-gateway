<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;

class AuthorizationService
{
    private AuthorizationChecker $authorizationChecker;
    private ParameterBagInterface $parameterBag;
    private CommonGroundService $commonGroundService;
    private Security $security;
    private SessionInterface $session;
    private CacheInterface $cache;
    private EntityManagerInterface $entityManager;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        AccessDecisionManagerInterface $accessDecisionManager,
        ParameterBagInterface $parameterBag,
        CommonGroundService $commonGroundService,
        Security $security,
        SessionInterface $session,
        CacheInterface $cache,
        EntityManagerInterface $entityManager
    ) {
        $this->authorizationChecker = new AuthorizationChecker($tokenStorage, $authenticationManager, $accessDecisionManager);
        $this->parameterBag = $parameterBag;
        $this->commonGroundService = $commonGroundService;
        $this->security = $security;
        $this->session = $session;
        $this->cache = $cache;
        $this->entityManager = $entityManager;
    }

    /**
     * @TODO
     *
     * @param array $info
     * @return array
     */
    private function handleInfoArray(array $info): array
    {
        return [
            'method' => $info['method'] ?? 'GET',
            'entity' => $info['entity'] ?? null,
            'attribute' => $info['attribute'] ?? null,
            'object' => $info['object'] ?? null,
            'value' => $info['value'] ?? null,
        ];
    }

    /**
     * @TODO
     *
     * @param array|null $info Can contain the following "keys"(=>Default): "method"=>"GET", "entity"=>null, "attribute"=>null, "object"=>null & "value"=>null. Should at least contain Entity or Attribute, else the check only passes if the user has admin scopes. Should also contain an Object or a Value in order to check for 'valueScopes'.
     * @return void
     * @throws AccessDeniedException
     */
    public function checkAuthorization(array $info = []): void
    {
        if (!$this->parameterBag->get('app_auth')) {
            return;
        }

        $info = $this->handleInfoArray($info);
        $requiredScopes = $this->getRequiredScopes($info);
        try {
            $grantedScopes = $this->getGrantedScopes();
        } catch (InvalidArgumentException|CacheException $e) {
            throw new AccessDeniedException("Something went wrong, please contact IT for help");
        }

        if (in_array($requiredScopes['admin_scope'], $grantedScopes)) {
            return;
        }

        if ($this->handleValueScopes($grantedScopes, $info)
            || in_array($requiredScopes['base_scope'], $grantedScopes)
            || (array_key_exists('sub_scope', $requiredScopes) && in_array($requiredScopes['sub_scope'], $grantedScopes))
            || (array_key_exists('sub_scopes', $requiredScopes) && array_intersect($requiredScopes['sub_scopes'], $grantedScopes))) {
            return;
        }
        if (array_key_exists('sub_scopes', $requiredScopes)) {
            $subScopes = '['.implode(', ', $requiredScopes['sub_scopes']).']';

            throw new AccessDeniedException("Insufficient Access, scope {$requiredScopes['base_scope']} or one of {$subScopes} is required");
        } elseif (array_key_exists('sub_scope', $requiredScopes)) {
            throw new AccessDeniedException("Insufficient Access, scope {$requiredScopes['base_scope']} or {$requiredScopes['sub_scope']} is required");
        }

        throw new AccessDeniedException("Insufficient Access, scope {$requiredScopes['base_scope']} is required");
    }

    /**
     * @TODO
     *
     * @param array $info
     * @return array
     */
    private function getRequiredScopes(array $info): array
    {
        $method = $info['method'];
        $entity = $info['entity'];
        $attribute = $info['attribute'];

        $requiredScopes['admin_scope'] = $method.'.admin';
        if ($entity) {
            $requiredScopes['base_scope'] = $method.'.'.$entity->getName();
            if ($method == 'GET') { //TODO: maybe for all methods? but make sure to implement BL for it first!
                $requiredScopes['sub_scopes'] = [];
                $requiredScopes['sub_scopes'][] = $requiredScopes['base_scope'].'.id';
                if ($entity->getAvailableProperties()) {
                    $attributes = $entity->getAttributes()->filter(function (Attribute $attribute) use ($entity) {
                        return in_array($attribute->getName(), $entity->getAvailableProperties());
                    });
                }
                foreach ($attributes ?? $entity->getAttributes() as $attribute) {
                    $requiredScopes['sub_scopes'][] = $requiredScopes['base_scope'].'.'.$attribute->getName();
                }
            }
        } elseif ($attribute) {
            $requiredScopes['base_scope'] = $method.'.'.$attribute->getEntity()->getName();
            $requiredScopes['sub_scope'] = $requiredScopes['base_scope'].'.'.$attribute->getName();
        } else {
            //todo maybe throw an error here?
        }

        return $requiredScopes;
    }

    /**
     * @TODO
     *
     * @return array|string[]
     * @throws CacheException|InvalidArgumentException
     */
    private function getGrantedScopes(): array
    {
        // First check if we have these scopes in cache (this gets removed from cache everytime we start a new api call, see eavService ->handleRequest)
        $item = $this->cache->getItem('grantedScopes');

        if ($item->isHit()) {
            $grantedScopes = $item->get();
        } else {
            $this->session->set('anonymous', false);

            if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
                $grantedScopes = $this->getScopesFromRoles($this->security->getUser()->getRoles());
            } else {
                $grantedScopes = $this->getScopesForAnonymous();

                $this->session->set('anonymous', true);
            }
            $item->set($grantedScopes);
            $item->tag('grantedScopes');
            $this->cache->save($item);
        }

        return $grantedScopes;
    }

    /**
     * @TODO
     *
     * @param array $roles
     * @return string[]
     */
    private function getScopesFromRoles(array $roles): array
    {
        //todo: for testing, remove;
        $scopes = ['POST.organizations.type=taalhuis,team', 'PUT.organizations.type=taalhuis', 'DELETE.organizations.type=taalhuis', 'GET.organizations.type=taalhuis'];

        foreach ($roles as $role) {
            if (strpos($role, 'scope') !== null) {
                $scopes[] = substr($role, strlen('ROLE_scope.'));
            }
        }

        return $scopes;
    }

    /**
     * @TODO
     *
     * @return array
     * @throws CacheException|InvalidArgumentException
     */
    private function getScopesForAnonymous(): array
    {
        // First check if we have these scopes in cache (this gets removed from cache when a userGroup with name ANONYMOUS is changed, see FunctionService)
        $item = $this->cache->getItem('anonymousScopes');
        if ($item->isHit()) {
            return $item->get();
        }

        // Get the ANONYMOUS userGroup
        $groups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['name' => 'ANONYMOUS'], false)['hydra:member'];
        $scopes = [];
        if (count($groups) == 1) {
            foreach ($groups[0]['scopes'] as $scope) {
                $scopes[] = $scope['code'];
            }
        }
        if (count($scopes) > 0) {
            // Save in cache
            $item->set($scopes);
            $item->tag('anonymousScopes');
            $this->cache->save($item);

            return $scopes;
        } else {
            throw new AuthenticationException('Authentication Required');
        }
    }

    /**
     * @TODO
     *
     * @param array $grantedScopes
     * @param array $info
     * @return bool
     */
    private function handleValueScopes(array $grantedScopes, array $info): bool
    {
        // Check for Value scopes
        $checkValueScopes = $this->checkValueScopes($grantedScopes, $info);

        // Handle the result
        if ($checkValueScopes['hasValueScopes']) {
            if ($checkValueScopes['matchValueScopes']) {
                return true;
            } else {
                $message = $checkValueScopes['error']['message'] ?? '';
                $scope = $checkValueScopes['error']['failedScope'] ?? '';
                $attributeName = $info['attribute'] ? $info['attribute']->getName() : ($checkValueScopes['error']['attribute'] ? $checkValueScopes['error']['attribute']->getName() : '');
                $shouldMatchValues = '';
                if ($checkValueScopes['error']['shouldMatchValues']) {
                    $shouldMatchValues = count($checkValueScopes['error']['shouldMatchValues']) > 1 ? 'one of ' : '';
                    $shouldMatchValues = $shouldMatchValues.'['.implode(', ', $checkValueScopes['error']['shouldMatchValues']).']';
                }
                $failedValue = $checkValueScopes['error']['failedValue'] ?? '';
                throw new AccessDeniedException("{$message}, because of scope {$scope}. {$attributeName} set to {$shouldMatchValues} is required. [{$failedValue}] is not allowed.");
            }
        }
        return false;
    }

    /**
     * @TODO
     *
     * @param array $grantedScopes
     * @param array $info
     * @return array
     */
    private function checkValueScopes(array $grantedScopes, array $info): array
    {
        //todo: cache this function somehow? or a part of it ?
        //todo: split up this function into multiple other functions, try to remove duplicate code!

        $method = $info['method'];
        $entity = $info['entity'];
        $attribute = $info['attribute'];
        $object = $info['object'];
        $value = $info['value'];

        // We need at least a value or an object for this to work
        if (empty($object) && empty($value)) { //$method !== GET makes sure we don't throw this error on get Collection calls
            return $this->checkValueScopesReturn($this->checkValueScopesErrorReturn('no object & no value'), $method !== "GET");
        }

        // And we always need at least an Entity from somewhere
        if (empty($entity)) {
            if (empty($attribute)) {
                if (empty($object)) {
                    return $this->checkValueScopesReturn($this->checkValueScopesErrorReturn('no entity, attribute or object'), true);
                }
                $entity = $object->getEntity();
            } else {
                $entity = $attribute->getEntity();
            }
        }

        // Get the base scope for the given Entity and/or Attribute we are going to check
        $base_scope = $info['method'].'.'.$entity->getName();

        // If no $value is given, but $object & $attribute are given, get $value from the $object using the $attribute
        if (empty($value) && !empty($object) && !empty($attribute) &&
            !in_array($attribute->getType(), ['object', 'file', 'array']) &&
            !$attribute->getMultiple()
        ) {
            $value = $object->getValueByAttribute($attribute)->getValue();
        }

        // If we have an Attribute and a Value we are checking for a single scope
        if (!empty($attribute) && !empty($value)) {
            // Get the sub scope for the given Attribute we are going to check
            $sub_scope = $base_scope.'.'.$attribute->getName();

            // todo: make this foreach a function, same function as other foreach withh duplicate code
            // Loop through all scopes the user has
            foreach ($grantedScopes as $grantedScope) {
                // example: POST.organizations.type=taalhuis,team
                $grantedScopeExploded = explode("=", $grantedScope);

                // If count grantedScope == 2, the user has a scope with a = value check. If so, make sure the scope we are checking matches this scope (the part before the =)
                if (count($grantedScopeExploded) == 2 && $grantedScopeExploded[0] == $sub_scope) {
                    $hasValueScopes = true; // At this point we have a = value scope, and we are sure the given input ($info) matches this scope.
                    $scopeValues = explode(',', $grantedScopeExploded[1]);
                    // Compare value with scope
                    if (in_array($value, $scopeValues)) {
                        $matchValueScopes = true;
                        continue;
                    }
                    $failedScope = $grantedScope;
                    $failedValue = $value;
                    break;
                }
            }
        }
        // Else we are checking for multiple scopes of an Entity, if we also have an object, continue... (never continue if we have an Attribute!)
        elseif (!empty($entity) && !empty($object) && empty($attribute)) {
            if ($method === "POST") {
                // Do not check for ValueScopes on Entity & Object level when doing a POST, because values of the Object are not set at this point
                return $this->checkValueScopesReturn($this->checkValueScopesErrorReturn('Do not check for ValueScopes on Entity & Object level when doing a POST, because values of the Object are not set at this point'));
            }

            // todo: make this foreach a function, same function as other foreach withh duplicate code
            // Find scopes with = value check
            // Loop through all scopes the user has
            foreach ($grantedScopes as $grantedScope) {
                // example: POST.organizations.type=taalhuis,team
                $grantedScopeExploded = explode("=", $grantedScope);

                // If count grantedScope == 2, the user has a scope with a = value check.
                if (count($grantedScopeExploded) == 2) {
                    // Get info out of the scope
                    $grantedScopeName = explode(".", $grantedScopeExploded[0]);
                    $grantedScopeMethod = $grantedScopeName[0];
                    // Make sure we only check scopes with the correct method
                    if ($grantedScopeMethod !== $method) {
                        continue;
                    }
                    // Get the attribute name out of the scope
                    $attributeName = end($grantedScopeName);
                    // Find the attribute with the $entity and $attributeName
                    $scopeAttribute = $this->entityManager->getRepository('App:Attribute')->findOneBy(['entity' => $entity, 'name' => $attributeName]);
                    if (empty($scopeAttribute) ||
                        in_array($scopeAttribute->getType(), ['object', 'file', 'array']) ||
                        $scopeAttribute->getMultiple()
                    ) {
                        // If we don't find an Attribute for that Entity with the attribute name, continue
                        // If the attribute will return a value we can not compare to a string, continue
                        continue;
                    } else {
                        // Get the sub scope for the given Attribute we are going to check
                        $sub_scope = $base_scope.'.'.$scopeAttribute->getName();
                        if ($grantedScopeExploded[0] != $sub_scope) {
                            // Make sure the scope we are checking matches this scope (the part before the =)
                            continue;
                        }

                        // Get the value from the Object with the attribute of this scope
                        $value = $object->getValueByAttribute($scopeAttribute)->getValue();

                        //todo, what to do if a $value is empty?
                    }

                    $hasValueScopes = true; // At this point we have a = value scope, and we are sure the given input ($info) matches this scope.
                    $scopeValues = explode(',', $grantedScopeExploded[1]);
                    // Compare value with allowed values from the scope
                    if (in_array($value, $scopeValues)) {
                        $matchValueScopes = true;
                        continue;
                    }
                    $failedScope = $grantedScope;
                    $failedValue = $value;
                    break;
                }
            }
        }

        return $this->checkValueScopesReturn(
            $this->checkValueScopesErrorReturn(
                'Insufficient Access',
                $scopeValues ?? null,
                $failedValue ?? null,
                $failedScope ?? null,
                $scopeAttribute ?? null
            ),
            $hasValueScopes ?? false,
            $matchValueScopes ?? false
        );
    }

    /**
     * @TODO
     *
     * @param array $errorArray Use function checkValueScopesErrorReturn()
     * @param bool $hasValueScopes True if there is at least one grantedScope with a = value
     * @param bool $matchValueScopes True if the given value for this grantedScope with = value matches the required value
     * @return array
     */
    private function checkValueScopesReturn(array $errorArray, bool $hasValueScopes = false, bool $matchValueScopes = false): array
    {
        return [
            'error' => $errorArray,
            'hasValueScopes' => $hasValueScopes,
            'matchValueScopes' => $matchValueScopes
        ];
    }

    /**
     * @TODO
     *
     * @param string $message An error message, default = 'Something went wrong'.
     * @param array|null $scopeValues The values that are allowed by the $failedScope.
     * @param null $failedValue The value that did not match the $scopeValues.
     * @param string|null $failedScope The scope that the user failed to 'match'.
     * @param Attribute|null $attribute The attribute of the scope that the user failed to 'match' $failedScope.
     * @return array
     */
    private function checkValueScopesErrorReturn(string $message = 'Something went wrong', array $scopeValues = null, $failedValue = null, string $failedScope = null, ?Attribute $attribute = null): array
    {
        return [
            'message' => $message,
            'shouldMatchValues' => $scopeValues,
            'failedValue' => $failedValue,
            'failedScope' => $failedScope,
            'attribute' => $attribute
        ];
    }

    /**
     * @TODO
     *
     * @param string $contentType
     * @param SerializerService $serializerService
     * @param AccessDeniedException $exception
     * @return Response
     */
    public function serializeAccessDeniedException(string $contentType, SerializerService $serializerService, AccessDeniedException $exception): Response
    {
        return new Response(
            $serializerService->serialize(
                new ArrayCollection(['message' => $exception->getMessage()]),
                $serializerService->getRenderType($contentType),
                []
            ),
            $exception->getCode(),
            ['Content-Type' => $contentType]
        );
    }
}
