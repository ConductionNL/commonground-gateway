<?php

// src/Security/User/WebserviceUser.php

namespace App\Security\User;

use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticationUser implements UserInterface, EquatableInterface
{
    /* The username display */
    private string $username;

    /* Provide UUID instead of normal password */
    private $password;

    /* The first name of the user */
    private $firstName;

    /* The last name of the user */
    private $lastName;

    /* The first and last name of the user */
    private $name;

    /* Leave empty! */
    private $salt;

    /* Iether a BRP or CC person URI */
    private array $roles;

    /* Always true */
    private $isActive;

    private $email;

    private $organization;

    private $person;

    private $userIdentifier;

    public function __construct(string $userIdentifier, string $username = '', string $password = '', string $firstName = '', string $lastName = '', string $name = '', string $salt = null, array $roles = [], string $email = '', $locale = null, ?string $organization = null, ?string $person = null)
    {
        $this->userIdentifier = $userIdentifier;
        $this->username = $username;
        $this->password = $password;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->name = $name;
        $this->salt = $salt;
        $this->roles = $roles;
        $this->isActive = true;
        $this->email = $email;
        $this->locale = $locale; // The language of this user
        $this->organization = $organization;
        $this->person = $person;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function __toString()
    {
        return $this->name;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getSalt()
    {
        return $this->salt;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getOrganization()
    {
        return $this->organization;
    }

    public function getPerson()
    {
        return $this->person;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function isEnabled()
    {
        return $this->isActive;
    }

    public function eraseCredentials()
    {
    }

    // serialize and unserialize must be updated - see below
    public function serialize()
    {
        return serialize([
            $this->username,
            $this->password,
            // see section on salt below
            // $this->salt,
        ]);
    }

    public function unserialize($serialized)
    {
        list(
            $this->username,
            $this->password) = unserialize($serialized);
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return true;
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement @method string getUserIdentifier()
    }
}
