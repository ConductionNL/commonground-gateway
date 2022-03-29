<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
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
    public CacheInterface $cache;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        AccessDecisionManagerInterface $accessDecisionManager,
        ParameterBagInterface $parameterBag,
        CommonGroundService $commonGroundService,
        Security $security,
        SessionInterface $session,
        CacheInterface $cache
    ) {
        $this->authorizationChecker = new AuthorizationChecker($tokenStorage, $authenticationManager, $accessDecisionManager);
        $this->parameterBag = $parameterBag;
        $this->commonGroundService = $commonGroundService;
        $this->security = $security;
        $this->session = $session;
        $this->cache = $cache;
    }

    /**
     * @TODO
     *
     * @param string $method
     * @param Attribute|null $attribute
     * @param Entity|null $entity
     * @return array
     */
    public function getRequiredScopes(string $method, ?Attribute $attribute, ?Entity $entity = null): array
    {
        $scopes['admin_scope'] = $method.'.admin';
        if ($entity) {
            $scopes['base_scope'] = $method.'.'.$entity->getName();
            if ($method == 'GET') { //TODO: maybe for all methods? but make sure to implement BL for it first!
                $scopes['sub_scopes'] = [];
                $scopes['sub_scopes'][] = $scopes['base_scope'].'.id';
                if ($entity->getAvailableProperties()) {
                    $attributes = $entity->getAttributes()->filter(function (Attribute $attribute) use ($entity) {
                        return in_array($attribute->getName(), $entity->getAvailableProperties());
                    });
                }
                foreach ($attributes ?? $entity->getAttributes() as $attribute) {
                    $scopes['sub_scopes'][] = $scopes['base_scope'].'.'.$attribute->getName();
                }
            }
        } else {
            $scopes['base_scope'] = $method.'.'.$attribute->getEntity()->getName();
            $scopes['sub_scope'] = $scopes['base_scope'].'.'.$attribute->getName();
        }

        return $scopes;
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
            'objectEntity' => $info['objectEntity'] ?? null,
            'value' => $info['value'] ?? null,
        ];
    }

    /**
     * @TODO
     *
     * @param array $scopes
     * @param array|null $info can contain the following keys: method, entity, attribute, objectEntity & value
     * @return void
     * @throws CacheException|InvalidArgumentException
     */
    public function checkAuthorization(array $scopes, ?array $info = []): void
    {
        if (!$this->parameterBag->get('app_auth')) {
            return;
        }

        $grantedScopes = $this->getGrantedScopes();

        if (in_array($scopes['admin_scope'], $grantedScopes)) {
            return;
        }

        if ($this->handleValueScopes($scopes, $grantedScopes, $this->handleInfoArray($info))
            || in_array($scopes['base_scope'], $grantedScopes)
            || (array_key_exists('sub_scope', $scopes) && in_array($scopes['sub_scope'], $grantedScopes))
            || (array_key_exists('sub_scopes', $scopes) && array_intersect($scopes['sub_scopes'], $grantedScopes))) {
            return;
        }
        if (array_key_exists('sub_scopes', $scopes)) {
            $subScopes = '['.implode(', ', $scopes['sub_scopes']).']';

            throw new AccessDeniedException("Insufficient Access, scope {$scopes['base_scope']} or one of {$subScopes} is required");
        } elseif (array_key_exists('sub_scope', $scopes)) {
            throw new AccessDeniedException("Insufficient Access, scope {$scopes['base_scope']} or {$scopes['sub_scope']} is required");
        }

        throw new AccessDeniedException("Insufficient Access, scope {$scopes['base_scope']} is required");
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
    public function getScopesFromRoles(array $roles): array
    {
        $scopes = ['POST.organizations.type=taalhuis', 'GET.organizations.type=taalhuis']; //todo: for testing, remove
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
    public function getScopesForAnonymous(): array
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
        $checkValueScopes = $this->checkValueScopes($grantedScopes, $info);
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
                throw new AccessDeniedException("Insufficient Access, because of scope {$scope}, {$info['attribute']->getName()} set to {$shouldMatchValues} is required");
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
        $method = $info['method'];
        $entity = $info['entity'];
        $attribute = $info['attribute'];
        $object = $info['objectEntity'];
        $value = $info['value'];

        //todo: if $object & $entity are given, make sure $object->getEntity() matches $entity
        //todo: cache this somehow? or a part of it

        //If no $value is given, but $object & $attribute are given, get value from the $object using $object->getValueByAttribute($attribute)
        if (empty($value) && !empty($object) && !empty($attribute) && !in_array($attribute->getType(), ['object', 'file', 'array'])) {
            $value = $object->getValueByAttribute($attribute)->getValue();
        }

        //todo: first draft, needs some improvements to deal with $entity and $object, also see above todo's ^
        if (!empty($value)) {
            // Get the base & sub scope(s) for the given Entity and/or Attribute we are going to check
            if (empty($entity) && !empty($attribute)) {
                $entity = $attribute->getEntity();
            }
            $base_scope = $info['method'].'.'.$entity->getName();
            $sub_scope = null;
            if (!empty($attribute)) {
                $sub_scope = $base_scope.'.'.$attribute->getName();
//                var_dump($attribute->getName().' : '.$value);
            }

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
                    if (in_array($value, $scopeValues)) {
                        var_dump('match');
                        $matchValueScopes = true;
                        continue;
                    }
                    break;
                }
            }
        }

        return [
            'hasValueScopes' => $hasValueScopes ?? false, // true if there is at least one scope with an = value
            'matchValueScopes' => $matchValueScopes ?? false, // true if the given value for the attribute with a scope with = value matches the required value
            'shouldMatchValues' => $scopeValues ?? null // the value(s) that should be matched because of the = value scope (used for error message)
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
