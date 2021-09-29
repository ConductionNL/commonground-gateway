<?php


namespace App\Service;


use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthorizationService
{
    private AuthorizationChecker $authorizationChecker;
    private ParameterBagInterface $parameterBag;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        AccessDecisionManagerInterface $accessDecisionManager,
        ParameterBagInterface $parameterBag
    )
    {
        $this->authorizationChecker = new AuthorizationChecker($tokenStorage, $authenticationManager, $accessDecisionManager);
        $this->parameterBag = $parameterBag;
    }

    public function checkAuthorization(array $roles) : bool
    {
        if(!$this->parameterBag->get('app_auth')){
            return true;
        }

        foreach($roles as $role){
            if($this->authorizationChecker->isGranted($role)){
                return true;
            }
        }

        if($this->authorizationChecker->isGranted('IS_AUTHENTICATED_ANONYMOUSLY')) {
            throw new AuthenticationException('Authentication Required');
        } else {
            throw new AccessDeniedException();
        }
    }
}