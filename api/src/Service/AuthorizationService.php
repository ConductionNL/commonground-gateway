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
     * @param array|null $info Can contain the following "keys"(=>Default): "method"=>"GET", "entity"=>null, "attribute"=>null, "objectEntity"=>null & "value"=>null. Should at least contain Entity or Attribute, else the check only passes if the user has admin scopes. Should also contain an Object or a Value in order to check for 'valueScopes'.
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

        if ($this->handleValueScopes($requiredScopes, $grantedScopes, $info)
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
        $scopes = ['POST.organizations.type=taalhuis', 'PUT.organizations.type=taalhuis', 'DELETE.organizations.type=taalhuis', 'GET.organizations.type=taalhuis']; //todo: for testing, remove
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
     * @param array $scopes
     * @param array $grantedScopes
     * @param array $info
     * @return bool
     */
    private function handleValueScopes(array $scopes, array $grantedScopes, array $info): bool
    {
        // Check for Value scopes
        $checkValueScopes = $this->checkValueScopes($grantedScopes, $info);

        if ($info['method'] && $info['method'] == "PUT" && $info['object'] && $info['entity']) {
            var_dump($checkValueScopes);
        }

        // Handle the result
        if ($checkValueScopes['hasValueScopes']) {
            //todo: turn this into a function? something like handleValueScopesResult() ?
            if ($checkValueScopes['matchValueScopes']) {
                return true;
            } else {
                $shouldMatchValues = count($checkValueScopes['shouldMatchValues']) > 1 ? 'one of ' : '';
                $shouldMatchValues = $shouldMatchValues.'['.implode(', ', $checkValueScopes['shouldMatchValues']).']';
                // todo: handle different exceptions based on $scopes array, see other throw AccessDeniedException in if statement(s) below this.
                $scope = $scopes['base_scope'];
                if (array_key_exists('sub_scope', $scopes)) {
                    $scope = $scopes['sub_scope'];
                }
                if (array_key_exists('sub_scopes', $scopes)) {
                    $scope = '['.implode(', ', $scopes['sub_scopes']).']';
                }
                //todo: get correct scope name for error message from $grantedScopes
                //todo: $grantedScopes has POST.organizations.type=taalhuis
                //todo: $scopes only has POST.organizations.type
                //todo: what if we don't have an info['attribute']?
                $str = $info['attribute'] ? $info['attribute']->getName() : ($info['entity'] ? $info['entity']->getName() : '');
                throw new AccessDeniedException("Insufficient Access, because of scope {$scope}, {$str} set to {$shouldMatchValues} is required");
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
        //todo: test what happens if we do an api-call on a resource with a subresource that isn't allowed to be seen or changed because of a value scope
        //todo: cache this function somehow? or a part of it ?
        //todo: split up this function into multiple other functions, try to remove duplicate code
        //todo: remove var_dumps

        $entity = $info['entity'];
        $attribute = $info['attribute'];
        $object = $info['object'];
        $value = $info['value'];

        // We need at least a value or an object for this to work
        if (empty($object) && empty($value)) {
//            var_dump('no object & no value');
            return $this->checkValueScopesReturn(); // todo: add some message to return?
        }

        // And we always need at least an Entity from somewhere
        if (empty($entity)) {
            if (empty($attribute)) {
                if (empty($object)) {
//                    var_dump('no entity, attribute or object');
                    return $this->checkValueScopesReturn(); // todo: add some message to return?
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
            !in_array($attribute->getType(), ['object', 'file', 'array']) // todo: what about $attribute->getMultiple() ?
        ) {
            $value = $object->getValueByAttribute($attribute)->getValue();
        }

        // If we have an Attribute and a Value we are checking for a single scope
        if (!empty($attribute) && !empty($value)) {
            // Get the sub scope for the given Attribute we are going to check
            $sub_scope = $base_scope.'.'.$attribute->getName();
//            var_dump($attribute->getName().' : ');
//            var_dump($value);

            // Loop through all scopes the user has
            foreach ($grantedScopes as $grantedScope) {
                // example: POST.organizations.type=taalhuis,team
                $grantedScope = explode("=", $grantedScope);

                // If count grantedScope == 2, the user has a scope with a = value check. If so, make sure the scope we are checking matches this scope (the part before the =)
                if (count($grantedScope) == 2 && $grantedScope[0] == $sub_scope) {
                    $hasValueScopes = true;
                    $scopeValues = explode(',', $grantedScope[1]);
                    var_dump('ScopeValues: ');
                    var_dump($scopeValues);
                    // Compare value with scope
                    if (in_array($value, $scopeValues)) {
                        var_dump('match');
                        $matchValueScopes = true;
                        continue;
                    }
                    break;
                }
            }
        }
        // Else we are checking for multiple scopes of an Entity (at this point we always have an Entity, if we also have an object, continue...)
        elseif (!empty($object)) {
            // Find scopes with = value check
            // Loop through all scopes the user has
            foreach ($grantedScopes as $grantedScope) {
                // example: POST.organizations.type=taalhuis,team
                $grantedScope = explode("=", $grantedScope);

                // If count grantedScope == 2, the user has a scope with a = value check. If so, make sure the scope we are checking matches this scope (the part before the =)
                if (count($grantedScope) == 2) {
                    // Get the attribute out of the scope
                    $grantedScope[0] = explode(".", $grantedScope[0]);
                    $attributeName = end($grantedScope[0]);
                    // Find the attribute with the $entity and $attributeName
                    var_dump('Found attribute name: '.$attributeName);
                    $scopeAttribute = $this->entityManager->getRepository('App:Attribute')->findOneBy(['entity' => $entity, 'name' => $attributeName]);
                    if (!empty($scopeAttribute)) {
                        // Get the value from Object for the attribute of this scope
                        $value = $object->getValueByAttribute($scopeAttribute)->getValue(); // todo: make sure we get a 'valid' value we can compare later
                        var_dump('value: '.$value);
                    } // todo else

                    $hasValueScopes = true;
                    $scopeValues = explode(',', $grantedScope[1]);
                    var_dump('ScopeValues: ');
                    var_dump($scopeValues);
                    // Compare value with scope
                    if (in_array($value, $scopeValues)) {
                        var_dump('match');
                        $matchValueScopes = true;
                        continue;
                    }
                    break; // todo: what if we have multiple (different) scopes with an = value
                }
            }
        }

        return $this->checkValueScopesReturn($hasValueScopes ?? false, $matchValueScopes ?? false, $scopeValues ?? null);
    }

    /**
     * @TODO
     *
     * @param bool $hasValueScopes True if there is at least one grantedScope with a = value
     * @param bool $matchValueScopes True if the given value for this grantedScope with = value matches the required value
     * @param array|null $scopeValues The value(s) that should be matched because of the = value grantedScope (used for error message)
     * @return array
     */
    private function checkValueScopesReturn(bool $hasValueScopes = false, bool $matchValueScopes = false, array $scopeValues = null): array
    {
        return [
            // todo: add some message to return?
            'hasValueScopes' => $hasValueScopes,
            'matchValueScopes' => $matchValueScopes,
            'shouldMatchValues' => $scopeValues
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
