<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about an Application.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/applications/{id}"},
 *      "put"={"path"="/admin/applications/{id}"},
 *      "delete"={"path"="/admin/applications/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/applications"},
 *      "post"={"path"="/admin/applications"}
 *  })
 * )
 *
 * @ORM\HasLifecycleCallbacks
 *
 * @ORM\Entity(repositoryClass="App\Repository\ApplicationRepository")
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class)
 */
class Application
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
     * @var string The name of this Application.
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
     * @var string A description of this Application.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * The hosts that this applications uses, keep in ind that a host is exluding a trailing slach / and https:// ot http://.
     *
     * @var array An array of hosts of this Application.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array")
     */
    private array $domains = [];

    /**
     * @var string A public key of this Application.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="text", nullable=true, name="public_column")
     */
    private ?string $public = null;

    /**
     * @var string A secret key of this Application.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $secret = null;

    /**
     * @var string|null A public key for authentication, or a secret for HS256 keys
     *
     * @Groups({"write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $publicKey = null;

    /**
     * @var string|null A private key for authentication, or a secret for HS256 keys
     *
     * @Groups({"write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $privateKey = null;

    /**
     * @var string Uri of user object.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $resource = null;

    // TODO: make this required?
    /**
     * @var Organization An uuid or uri of an organization for this Application.
     *
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToOne(targetEntity=Organization::class, inversedBy="applications")
     *
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Organization $organization;

    /**
     * @MaxDepth(1)
     *
     * @ORM\OneToMany(targetEntity=ObjectEntity::class, mappedBy="application", fetch="EXTRA_LAZY", cascade={"remove"})
     */
    private Collection $objectEntities;

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=Endpoint::class, inversedBy="applications")
     */
    private $endpoints;

    /**
     * @var ?Collection The collections of this Application
     *
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=CollectionEntity::class, mappedBy="applications")
     */
    private ?Collection $collections;

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\OneToMany(targetEntity=Contract::class, mappedBy="application")
     */
    private ?Collection $contracts;

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
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="applications")
     */
    private $users;

    /**
     * @var array Certificates that can be used to verify with this application
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private array $certificates = [];

    /**
     * @var array|null The configuration of this application.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $configuration = [];

    public function __construct()
    {
        $this->objectEntities = new ArrayCollection();
        $this->endpoints = new ArrayCollection();
        $this->collections = new ArrayCollection();
        $this->contracts = new ArrayCollection();
        $this->users = new ArrayCollection();
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

    public function getPublic(): ?string
    {
        return $this->public;
    }

    public function setPublic(?string $public): self
    {
        $this->public = $public;

        return $this;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(?string $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;

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

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDomains(): ?array
    {
        return $this->domains;
    }

    public function setDomains(array $domains): self
    {
        $this->domains = $domains;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * @return Collection|ObjectEntity[]
     */
    public function getObjectEntities(): Collection
    {
        return $this->objectEntities;
    }

    public function addObjectEntity(ObjectEntity $objectEntity): self
    {
        if (!$this->objectEntities->contains($objectEntity)) {
            $this->objectEntities[] = $objectEntity;
            $objectEntity->setApplication($this);
        }

        return $this;
    }

    public function removeObjectEntity(ObjectEntity $objectEntity): self
    {
        if ($this->objectEntities->removeElement($objectEntity)) {
            // set the owning side to null (unless already changed)
            if ($objectEntity->getApplication() === $this) {
                $objectEntity->setApplication(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Endpoint[]
     */
    public function getEndpoints(): Collection
    {
        return $this->endpoints;
    }

    public function addEndpoint(Endpoint $endpoint): self
    {
        if (!$this->endpoints->contains($endpoint)) {
            $this->endpoints[] = $endpoint;
        }

        return $this;
    }

    public function removeEndpoint(Endpoint $endpoint): self
    {
        $this->endpoints->removeElement($endpoint);

        return $this;
    }

    /**
     * @return Collection|CollectionEntity[]
     */
    public function getCollections(): Collection
    {
        return $this->collections;
    }

    public function addCollection(CollectionEntity $collection): self
    {
        if (!$this->collections->contains($collection)) {
            $this->collections[] = $collection;
            $collection->addApplication($this);
        }

        return $this;
    }

    public function removeCollection(CollectionEntity $collection): self
    {
        if ($this->collections->removeElement($collection)) {
            $collection->removeApplication($this);
        }

        return $this;
    }

    /**
     * @return Collection|Contract[]
     */
    public function getContracts(): Collection
    {
        return $this->contracts;
    }

    public function addContract(Contract $contract): self
    {
        if (!$this->contracts->contains($contract)) {
            $this->contracts[] = $contract;
            $contract->setApplication($this);
        }

        return $this;
    }

    public function removeContract(Contract $contract): self
    {
        if ($this->contracts->removeElement($contract)) {
            // set the owning side to null (unless already changed)
            if ($contract->getApplication() === $this) {
                $contract->setApplication(null);
            }
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

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): self
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->addApplication($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            $user->removeApplication($this);
        }

        return $this;
    }

    /**
     *  @ORM\PrePersist
     *
     *  @ORM\PreUpdate
     */
    public function prePersist()
    {
        if (!$this->getSecret()) {
            $secret = Uuid::uuid4()->toString();
            $this->setSecret($secret);
        }

        if (!$this->getPublic()) {
            $secret = Uuid::uuid4()->toString();
            $this->setPublic($secret);
        }
    }

    /**
     * @return string|null
     */
    public function getPrivateKey(): ?string
    {
        return $this->privateKey;
    }

    /**
     * @param string|null $privateKey
     */
    public function setPrivateKey(?string $privateKey): self
    {
        $this->privateKey = $privateKey;

        return $this;
    }

    /**
     * @return array
     */
    public function getCertificates(): array
    {
        return $this->certificates;
    }

    /**
     * @param array|null $certificates
     *
     * @return Application
     */
    public function setCertificates(?array $certificates): self
    {
        $this->certificates = $certificates;

        return $this;
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(?array $configuration = []): self
    {
        $this->configuration = $configuration;

        return $this;
    }
}
