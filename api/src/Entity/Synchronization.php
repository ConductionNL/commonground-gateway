<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Gateway as Source;
use App\Repository\SynchronizationRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about the Synchronization.
 */
#[
    ApiResource(
        operations: [
            new Get(          "/admin/synchronizations/{id}"),
            new Put(          "/admin/synchronizations/{id}"),
            new Delete(       "/admin/synchronizations/{id}"),
            new GetCollection("/admin/synchronizations"),
            new Post(         "/admin/synchronizations")
        ],
        normalizationContext: [
            'groups' => ['read'],
            'enable_max_depth' => true
        ],
        denormalizationContext: [
            'groups' => ['write'],
            'enable_max_depth' => true
        ],
    ),
    ORM\Entity(repositoryClass: SynchronizationRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(
        SearchFilter::class,
        properties: [
            'entity.id'  => 'exact',
            'gateway.id' => 'exact',
            'object.id'  => 'exact',
            'sourceId'   => 'exact'
        ]
    )
]

class Synchronization
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     */
    #[
        Groups(['read', 'write']),
        Assert\Uuid,
        ORM\Id,
        ORM\Column(
            type: 'uuid',
            unique: true
        ),
        ORM\GeneratedValue(strategy: 'CUSTOM'),
        ORM\CustomIdGenerator(class: UuidGenerator::class)
    ]
    private UuidInterface $id;

    /**
     * @var Entity The entity of this resource
     */
    #[
        Groups(['read', 'write']),
        ORM\JoinColumn(
            nullable: false
        ),
        ORM\ManyToOne(
            targetEntity: Entity::class
        )
    ]
    private Entity $entity;

    /**
     * @var ObjectEntity|null The object of this resource
     */
    #[
        Groups(['read', 'write']),
        ORM\ManyToOne(
            targetEntity: ObjectEntity::class,
            cascade: ['persist'],
            fetch: 'EAGER',
            inversedBy: 'synchronizations'
        )
    ]
    private ?ObjectEntity $object = null;

    /**
     * @var Action|null The action of this resource
     */
    #[
        Groups(['read', 'write']),
        ORM\ManyToOne(
            targetEntity: Action::class
        )
    ]
    private ?Action $action = null;

    /**
     * @var Source|null The Source of this resource
     *
     * The source of this synchronization might be an external source (gateway).
     */
    #[
        Groups(['read', 'write']),
        ORM\JoinColumn(nullable: true),
        ORM\ManyToOne(
            targetEntity: Gateway::class,
            cascade: ['persist'],
            inversedBy: 'synchronizations'
        )
    ]
    private ?Source $gateway = null;

    /**
     * @var ObjectEntity|null The source object if it is contained in the same gateway.
     */
    #[
        Groups(['read', 'write']),
        ORM\JoinColumn(nullable: true),
        ORM\ManyToOne(
            targetEntity: ObjectEntity::class,
            inversedBy: 'sourceOfSynchronizations'
        )
    ]
    private ?ObjectEntity $sourceObject = null;

    /**
     * @var string|null
     */
    #[
        Assert\Length(max: 255),
        Groups(['read', 'write']),
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true
        )
    ]
    private ?string $endpoint = null;

    /**
     * @var string|null The id of object in the related source
     */
    #[
        Assert\Length(max: 255),
        Groups(['read', 'write']),
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true
        )
    ]
    private ?string $sourceId = null;

    /**
     * @var string|null The hash of this resource
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'text',
            nullable: true
        )
    ]
    private ?string $hash = '';

    /**
     * @var ?string The sha(256) used to check if a Sync should be triggered cause the object has changed
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'text'
        )
    ]
    private ?string $sha = null;

    /**
     * @var bool Whether the synchronization is blocked
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'boolean',
            options: ['default' => true]
        )
    ]
    private bool $blocked = false;

    /**
     * @var DateTimeInterface|null The moment the source of this resource was last changed
     */
    #[
        Groups(['read']),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private ?DateTimeInterface $sourceLastChanged = null;

    /**
     * @var DateTimeInterface|null The moment this resource was last checked
     */
    #[
        Groups(['read']),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private ?DateTimeInterface $lastChecked = null;

    /**
     * @var DateTimeInterface|null The moment this resource was last synced
     */
    #[
        Groups(['read']),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private ?DateTimeInterface $lastSynced = null;

    /**
     * @var int The amount of times that we have tried to sync this item, counted by the amount of times it has been "touched".
     */
    #[
        ORM\Column(
            type: 'integer',
            options: ['default' => 1]
        )
    ]
    private int $tryCounter = 0;

    /**
     * @var DateTimeInterface|null An updated timer that tels the sync service to wait a specific increment beofre trying again.
     */
    #[
        Groups(['read']),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private ?DateTimeInterface $dontSyncBefore = null;

    /**
     * @var Mapping|null Mapping used by this synchronization.
     */
    #[
        ORM\ManyToOne(
            targetEntity: Mapping::class,
            inversedBy: 'synchronizations'
        )
    ]
    private ?Mapping $mapping = null;

    /**
     * @var DateTimeInterface|null The moment this resource was created
     */
    #[
        Groups(['read']),
        Gedmo\Timestampable(on: 'create'),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private ?DateTimeInterface $dateCreated = null;

    /**
     * @var DateTimeInterface|null The moment this resource was last Modified
     */
    #[
        Groups(['read']),
        Gedmo\Timestampable(on: 'update'),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private ?DateTimeInterface $dateModified = null;

    public function __construct(?Source $source = null, ?Entity $entity = null)
    {
        if (isset($source)) {
            $this->gateway = $source;
        }
        if (isset($entity)) {
            $this->entity = $entity;
        }
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

    public function getEntity(): ?Entity
    {
        return $this->entity;
    }

    public function setEntity(Entity $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getObject(): ?ObjectEntity
    {
        return $this->object;
    }

    public function setObject(?ObjectEntity $object): self
    {
        if ($object !== null) {
            $this->setEntity($object->getEntity());

            if ($object->getSynchronizations()->contains($this) === false) {
                $object->addSynchronization($this);
            }
        }
        if ($object === null) {
            $this->setEntity(null);

            if ($this->object->getSynchronizations()->contains($this) === true) {
                $this->object->removeSynchronization($this);
            }
        }

        $this->object = $object;

        return $this;
    }

    public function getAction(): ?Action
    {
        return $this->action;
    }

    public function setAction(?Action $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getGateway(): ?Source
    {
        return $this->getSource();
    }

    public function getSource(): ?Source
    {
        return $this->gateway;
    }

    public function setGateway(?Source $source): self
    {
        return $this->setSource($source);
    }

    public function setSource(?Source $source): self
    {
        $this->gateway = $source;

        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function setSourceId(string $sourceId): self
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function getSha(): ?string
    {
        return $this->sha;
    }

    public function setSha(?string $sha): self
    {
        $this->sha = $sha;

        return $this;
    }

    public function getSourceLastChanged(): ?\DateTimeInterface
    {
        return $this->sourceLastChanged;
    }

    public function setSourceLastChanged(?\DateTimeInterface $sourceLastChanged): self
    {
        $this->sourceLastChanged = $sourceLastChanged;

        return $this;
    }

    public function getLastChecked(): ?\DateTimeInterface
    {
        return $this->lastChecked;
    }

    public function setLastChecked(?\DateTimeInterface $lastChecked): self
    {
        $this->lastChecked = $lastChecked;

        return $this;
    }

    public function getLastSynced(): ?\DateTimeInterface
    {
        return $this->lastSynced;
    }

    public function setLastSynced(?\DateTimeInterface $lastSynced): self
    {
        $this->lastSynced = $lastSynced;
        isset($this->gateway) && $this->gateway->setLastSync($lastSynced);

        return $this;
    }

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?\DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateModified(): ?\DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(?\DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }

    /**
     * @return bool
     */
    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    /**
     * @param bool $blocked
     */
    public function setBlocked(bool $blocked): self
    {
        $this->blocked = $blocked;

        return $this;
    }

    public function getTryCounter(): ?int
    {
        return $this->tryCounter;
    }

    public function setTryCounter(int $tryCounter): self
    {
        $this->tryCounter = $tryCounter;

        return $this;
    }

    public function getDontSyncBefore(): ?\DateTimeInterface
    {
        return $this->dontSyncBefore;
    }

    public function setDontSyncBefore(\DateTimeInterface $dontSyncBefore): self
    {
        $this->dontSyncBefore = $dontSyncBefore;

        return $this;
    }

    /**
     * Set name on pre persist.
     *
     * This function makes sure that each and every oject alwys has a name when saved
     *
     * @ORM\PrePersist
     */
    public function prePersist(): void
    {
        // If we have ten trys or more we want to block the sync
        if ($this->tryCounter >= 10) {
            $this->blocked = true;
        }
    }

    public function getMapping(): ?Mapping
    {
        return $this->mapping;
    }

    public function setMapping(?Mapping $mapping): self
    {
        $this->mapping = $mapping;

        return $this;
    }

    public function getSourceObject(): ?ObjectEntity
    {
        return $this->sourceObject;
    }

    public function setSourceObject(?ObjectEntity $sourceObject): self
    {
        $this->sourceObject = $sourceObject;

        return $this;
    }
}
