<?php

// src/Security/User/WebserviceUser.php

namespace App\Security\User;

use App\Entity\Application;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/*
 * The gateway UserClass
 */
class AuthenticationUser implements UserInterface, EquatableInterface
{
    private Uuid $id;

    /* The username display */
    private string $username;

    /* Provide UUID instead of normal password */
    private string $password;

    /* The first and last name of the user */
    private string $email;


    /* Iether a BRP or CC person URI */
    private array $roles;

    /* Always true */
    private bool $isActive;

    /**
     * The language of this user
     *
     * @var string|null
     */
    private string $locale;

    /**
     * @var Organization|null
     */
    private Organization $organization;

    /**
     * @var Application|Application[]|\Doctrine\Common\Collections\Collection
     */
    private Application $application;

    /**
     * @var string|\App\Entity\SecurityGroup[]|Collection
     */
    private string $securityGroups;


    private Collection $person;

    /**
     * @var string
     */
    private string $salt;

    public function __construct(User $user)
    {
        $this->userIdentifier = $user->getId();
        $this->username = $user->getUsername();
        $this->password = $user->getPassword();
        $this->email = $user->getEmail();
        $this->roles = $user->getScopes();
        $this->enabled = $user->getEnabled();
        $this->locale = $user->getLocale(); //
        $this->organization = $user->getOrganisation();
        $this->application = $user->getApplications();
        $this->person = $user->getPerson();
        $this->securityGroups = $user->getSecurityGroups();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }
    public function getEmail()
    {
        return $this->email;
    }

    public function getRoles()
    {
        return $this->roles;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function getLocale():string
    {
        return $this->locale;
    }

    public function getOrganization():Organization
    {
        return $this->organization;
    }

    public function getApplication():Application
    {
        return $this->application;
    }

    public function getPerson()
    {
        return $this->person;
    }

    public function getSecurityGroups()
    {
        return $this->securityGroups;
    }

    public function getSalt()
    {
        return $this->salt;
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

    public function isEqualTo(UserInterface $user)
    {
        return true;
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement @method string getUserIdentifier()
    }

    public function __toString()
    {
        return $this->username;
    }
}
