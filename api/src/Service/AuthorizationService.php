<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Contract;
use App\Entity\Entity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
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

    public function getScopesFromRoles(array $roles): array
    {
        $scopes = [];
        foreach ($roles as $role) {
            if (strpos($role, 'scope') !== null) {
                $scopes[] = substr($role, strlen('ROLE_scope.'));
            }
        }

        return $scopes;
    }

    private function getContractScopes(): array
    {
        $parameters = $this->session->get('parameters');
        if (array_key_exists('headers', $parameters) && array_key_exists('contract', $parameters['headers']) && Uuid::isValid($parameters['headers']['contract'][0])) {
            $contract = $this->entityManager->getRepository('App:Contract')->findOneBy(['id' => $parameters['headers']['contract'][0]]);
            if (!empty($contract)) {
                return $contract->getGrants();
            }
        }

        return [];
    }

    public function checkAuthorization(array $scopes): void
    {
        if (!$this->parameterBag->get('app_auth')) {
            return;
        }

        // First check if we have these scopes in cache (this gets removed from cache everytime we start a new api call, see eavService ->handleRequest)
        $item = $this->cache->getItem('grantedScopes');
        if ($item->isHit()) {
            $grantedScopes = $item->get();
        } else {
            // TODO: This is a quick fix for taalhuizen, find a better way of showing taalhuizen for an anonymous user!
            $this->session->set('anonymous', false);

            if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
                $grantedScopes = $this->getScopesFromRoles($this->security->getUser()->getRoles());
            } else {
                $grantedScopes = $this->getScopesForAnonymous();

                // TODO: This is a quick fix for taalhuizen, find a better way of showing taalhuizen for an anonymous user!
                $this->session->set('anonymous', true);
            }

            $contractScopes = $this->getContractScopes();
            $grantedScopes = array_merge($grantedScopes, $contractScopes);

            $item->set($grantedScopes);
            $item->tag('grantedScopes');
            $this->cache->save($item);
        }
        if (in_array($scopes['admin_scope'], $grantedScopes)) {
            return;
        }
        if (in_array($scopes['base_scope'], $grantedScopes)
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
