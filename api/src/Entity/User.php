<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about an User.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/users/{id}"},
 *      "put"={"path"="/admin/users/{id}"},
 *      "delete"={"path"="/admin/users/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/users"},
 *      "post"={"path"="/admin/users"}
 *  })
 * )
 *
 * @ORM\HasLifecycleCallbacks
 *
 * @ORM\Entity(repositoryClass=App\Repository\UserRepository::class)
 *
 * @ORM\Table(name="`user`")
 */
class User implements PasswordAuthenticatedUserInterface
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     *
     * @Groups({"read","read_secure"})
     *
     * @ORM\Id
     *
     * @ORM\Column(type="uuid", unique=true)
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var string The name of this User.
     *
     * @Gedmo\Versioned
     *
     * @Assert\Length(
     *     max = 255
     * )
     *
     * @Assert\NotNull
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @var string A description of this User.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default": null})
     */
    private ?string $reference = null;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default": null})
     */
    private ?string $version = null;

    /**
     * @Groups({"write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private $password;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToOne(targetEntity=Organization::class, inversedBy="users")
     *
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Organization $organisation = null;

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=Application::class, inversedBy="users")
     */
    private $applications;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $locale = 'en';

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $person;

    /**
     * @Groups({"read"})
     * The roles that this user inherrits from the user groups
     */
    private $scopes = [];

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=SecurityGroup::class, mappedBy="users")
     */
    private $securityGroups;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource was last Modified
     *
     * @Groups({"read"})
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    /**
     * @var string RS512 token
     *
     * @Groups({"read"})
     */
    private string $jwtToken = '';

    public function __construct()
    {
        $this->applications = new ArrayCollection();
        $this->securityGroups = new ArrayCollection();
    }

    /**
     * Create or update this User from an external schema array.
     *
     * This function is used to update and create users form user.json objects.
     *
     * @param array $schema The schema to load.
     *
     * @return $this This User.
     */
    public function fromSchema(array $schema): self
    {
        // Basic stuff
        if (array_key_exists('$id', $schema)) {
            $this->setReference($schema['$id']);
        }
        if (array_key_exists('version', $schema)) {
            $this->setVersion($schema['version']);
        }

        array_key_exists('title', $schema) ? $this->setName($schema['title']) : '';
        array_key_exists('description', $schema) ? $this->setDescription($schema['description']) : '';
        array_key_exists('locale', $schema) ? $this->setLocale($schema['locale']) : '';
        array_key_exists('email', $schema) ? $this->setEmail($schema['email']) : '';
        // Todo: for now set password always to !ChangeMe! When we support hashed password maybe change this.
        $this->setPassword('!ChangeMe!');
        array_key_exists('locale', $schema) ? $this->setLocale($schema['locale']) : '';
        array_key_exists('person', $schema) ? $this->setPerson($schema['person']) : '';
        array_key_exists('organization', $schema) ? $this->setOrganisation($schema['organization']) : '';
        array_key_exists('applications', $schema) ? $this->setApplications($schema['applications']) : '';

        // Todo: temporary? make sure we never allow admin scopes to be added or removed with fromSchema
        if (array_key_exists('securityGroups', $schema)) {
            $scopes = $this->getScopes();
            foreach ($schema['securityGroups'] as $securityGroup) {
                if ($securityGroup instanceof SecurityGroup === false) {
                    return $this;
                }
                $scopes = array_merge($scopes, $securityGroup->getScopes());
            }
            foreach ($scopes as $scope) {
                if (str_contains(strtolower($scope), 'admin')) {
                    return $this;
                }
            }
            $this->setSecurityGroups($schema['securityGroups']);
        }

        return $this;
    }

    /**
     * Convert this User to a schema.
     *
     * @return array Schema array.
     */
    public function toSchema(): array
    {
        $applications = [];
        foreach ($this->applications as $application) {
            if ($application !== null) {
                $application = $application->toSchema();
            }
            $applications[] = $application;
        }

        $securityGroups = [];
        foreach ($this->securityGroups as $securityGroup) {
            if ($securityGroup !== null) {
                $securityGroup = $securityGroup->toSchema(1);
            }
            $securityGroups[] = $securityGroup;
        }

        // Do not return password this way!
        return [
            '$id'                            => $this->getReference(), //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'                        => 'https://docs.commongateway.nl/schemas/User.schema.json',
            'title'                          => $this->getName(),
            'description'                    => $this->getDescription(),
            'version'                        => $this->getVersion(),
            'name'                           => $this->getName(),
            'email'                          => $this->getEmail(),
            'locale'                         => $this->getLocale(),
            'person'                         => $this->getPerson(),
            'scopes'                         => $this->getScopes(),
            'organization'                   => $this->getOrganisation() ? $this->getOrganisation()->toSchema() : null,
            'applications'                   => $applications,
            'securityGroups'                 => $securityGroups
        ];
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = Uuid::fromString($id);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getOrganisation(): ?Organization
    {
        return $this->organisation;
    }

    public function setOrganisation(?Organization $organization): self
    {
        $this->organisation = $organization;

        return $this;
    }

    /**
     * @return Collection|Application[]
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function setApplications(?array $applications): Collection
    {
        $this->applications->clear();
        if ($applications !== null && $applications !== []) {
            foreach ($applications as $application) {
                $this->addApplication($application);
            }
        }

        return $this->securityGroups;
    }

    public function addApplication(Application $application): self
    {
        if (!$this->applications->contains($application)) {
            $this->applications[] = $application;
        }

        return $this;
    }

    public function removeApplication(Application $application): self
    {
        $this->applications->removeElement($application);

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getPerson(): ?string
    {
        return $this->person;
    }

    public function setPerson(?string $person): self
    {
        $this->person = $person;

        return $this;
    }

    public function getScopes()
    {
        // Lets see if we need to establish al the scopes
        if (!empty($this->scopes)) {
            foreach ($this->securityGroups as $securityGroup) {
                array_merge($this->scopes, $securityGroup->getScopes());
            }
        }

        return $this->scopes;
    }

    /**
     * @return Collection|SecurityGroup[]
     */
    public function getSecurityGroups(): Collection
    {
        return $this->securityGroups;
    }

    public function setSecurityGroups(?array $securityGroups): Collection
    {
        $this->securityGroups->clear();
        if ($securityGroups !== null && $securityGroups !== []) {
            foreach ($securityGroups as $securityGroup) {
                $this->addSecurityGroup($securityGroup);
            }
        }

        return $this->securityGroups;
    }

    public function addSecurityGroup(SecurityGroup $securityGroup): self
    {
        if (!$this->securityGroups->contains($securityGroup)) {
            $this->securityGroups[] = $securityGroup;
            $securityGroup->addUser($this);
        }

        return $this;
    }

    public function removeSecurityGroup(SecurityGroup $securityGroup): self
    {
        if ($this->securityGroups->removeElement($securityGroup)) {
            $securityGroup->removeUser($this);
        }

        return $this;
    }

    public function getDateCreated(): ?DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateModified(): ?DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }

    public function setJwtToken(string $jwtToken): self
    {
        $this->jwtToken = $jwtToken;

        return $this;
    }

    public function getJwtToken(): string
    {
        return $this->jwtToken;
    }
}
