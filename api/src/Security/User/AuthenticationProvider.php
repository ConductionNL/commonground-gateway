<?php

// src/Security/User/CommongroundUserProvider.php

namespace App\Security\User;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @method UserInterface loadUserByIdentifier(string $identifier)
 */
class AuthenticationProvider implements UserProviderInterface
{
    private $params;
    private $commonGroundService;

    public function __construct(ParameterBagInterface $params, CommonGroundService $commonGroundService)
    {
        $this->params = $params;
        $this->commonGroundService = $commonGroundService;
    }

    public function loadUserByUsername($username)
    {
        return $this->fetchUser($username);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof AuthenticationUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        $userIdentifier = $user->getUserIdentifier();
        $username = $user->getUsername();
        $password = $user->getPassword();
        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();
        $name = $user->getName();
        $email = $user->getEmail();
        $roles = $user->getRoles();

        return $this->fetchUser($userIdentifier, $username, $password, $firstName, $lastName, $name, $roles, $email);
    }

    public function supportsClass($class): bool
    {
        return AuthenticationUser::class === $class;
    }

    private function fetchUser($userIdentifier, $username, $password, $firstName, $lastName, $name, $roles, $email)
    {
        return new AuthenticationUser($userIdentifier, $username, $password, $firstName, $lastName, $name, null, $roles, $email);
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement @method UserInterface loadUserByIdentifier(string $identifier)
    }
}
