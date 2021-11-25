<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
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

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        AccessDecisionManagerInterface $accessDecisionManager,
        ParameterBagInterface $parameterBag,
        CommonGroundService $commonGroundService,
        Security $security
    ) {
        $this->authorizationChecker = new AuthorizationChecker($tokenStorage, $authenticationManager, $accessDecisionManager);
        $this->parameterBag = $parameterBag;
        $this->commonGroundService = $commonGroundService;
        $this->security = $security;
    }

    public function getRequiredScopes(string $method, ?Attribute $attribute, Entity $entity = null): array
    {
        if ($entity) {
            $scopes['base_scope'] = $method.'.'.$entity->getName();
        } else {
            $scopes['base_scope'] = $method.'.'.$attribute->getEntity()->getName();
            $scopes['sub_scope'] = $scopes['base_scope'].'.'.$attribute->getName();
        }

        return $scopes;
    }

    public function getScopesForAnonymous(): array
    {
        $groups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['name' => 'ANONYMOUS'])['hydra:member'];
        $scopes = [];
        if (count($groups) == 1) {
            foreach ($groups[1]['scopes'] as $scope) {
                $scopes[] = $scope['code'];
            }
        }
        if (count($scopes) > 0) {
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

    public function checkAuthorization(array $scopes): void
    {
        if (!$this->parameterBag->get('app_auth')) {
            return;
        } elseif ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            $grantedScopes = $this->getScopesFromRoles($this->security->getUser()->getRoles());
        } else {
            $grantedScopes = $this->getScopesForAnonymous();
        }
        if (in_array($scopes['base_scope'], $grantedScopes) || (array_key_exists('sub_scope', $scopes) && in_array($scopes['sub_scope'], $grantedScopes))) {
            return;
        }
        if (!array_key_exists('sub_scope', $scopes)) {
            throw new AccessDeniedException("Insufficient Access, scope {$scopes['base_scope']} is required");
        }

        throw new AccessDeniedException("Insufficient Access, scope {$scopes['base_scope']} or {$scopes['sub_scope']} are required");
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
