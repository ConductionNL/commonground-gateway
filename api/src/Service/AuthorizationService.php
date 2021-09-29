<?php


namespace App\Service;


use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;;

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
    )
    {
        $this->authorizationChecker = new AuthorizationChecker($tokenStorage, $authenticationManager, $accessDecisionManager);
        $this->parameterBag = $parameterBag;
        $this->commonGroundService = $commonGroundService;
        $this->security = $security;
    }

    public function getScopes(array $properties, string $base): array
    {
        $scopes = ['main_scope' => $base];
        foreach($properties as $property){
            $scopes['sub_scopes'][] = $base.'.'.$property;
        }
        return $scopes;
    }

    public function getEavRequiredRoles(Request $request, string $entityName): array
    {
        if($request->getMethod() == Request::METHOD_POST || $request->getMethod() == Request::METHOD_PUT){
            $content = json_decode($request->getContent(), true);
        } else {
            $content = [];
        }
        $scopes = $this->getScopes(array_keys($content), $request->getMethod().'.'.$entityName);


        return $scopes;
    }

    public function getScopesForAnonymous(): array
    {
        $groups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['name' => 'ANONYMOUS'])['hydra:member'];
        $scopes = [];
        if(count($groups) == 1){

            foreach($groups[1]['scopes'] as $scope){
                $scopes[] = $scope['code'];
            }
        }
        if(count($scopes) > 0){
            return $scopes;
        } else {
            throw new AuthenticationException('Authentication Required');
        }

    }

    public function getScopesFromRoles(array $roles): array
    {
        $scopes = [];
        foreach($roles as $role)
        {
            if(strpos($role, 'scope') !== null){
                $scopes[] = substr($role, strlen('ROLE_scope.'));
            }
        }
        return $scopes;
    }

    public function getProperties(array $scopes, string $base): array
    {
        $properties = [];
        foreach($scopes as $scope){
            if(strpos($scope, $base) !== false){
                $scopeArray = explode('.', $scope);
                $properties[] = end($scopeArray);
            }
        }
        return $properties;
    }

    public function checkSubScopes(array $scopes, array $grantedScopes): void
    {
        if(isset($scopes['sub_scopes'])){
            foreach($scopes['sub_scopes'] as $subScope){
                if(!in_array($subScope, $grantedScopes)){
                    throw new AccessDeniedException('Insufficient Access');
                }
            }
        }
    }

    public function checkAuthorization(array $scopes): array
    {
        $properties = [];

        if(!$this->parameterBag->get('app_auth')){
            return $properties;
        } elseif($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')){
            $grantedScopes = $this->getScopesFromRoles($this->security->getUser()->getRoles());
            if(in_array($scopes['main_scope'], $grantedScopes)){
                return $properties;
            }
        } else {
            $grantedScopes = $this->getScopesForAnonymous();
        }
        $this->checkSubScopes($scopes, $grantedScopes);

        return $this->getProperties($grantedScopes, $scopes['main_scope']);
    }

    public function serializeAccessDeniedException(string $contentType, SerializerService $serializerService, AccessDeniedException $exception): Response
    {
        return new Response(
            $serializerService->serialize(
                new ArrayCollection(['message' => $exception->getMessage()]),
                $serializerService->getRenderType($contentType), []),
            $exception->getCode(),
            ['Content-Type' => $contentType]
        );
    }
}