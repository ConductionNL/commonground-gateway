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
use App\Exception\GatewayException;
use App\Repository\ActionRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about an Application.
 */
#[
    ApiResource(
        operations: [
            new Get('/admin/actions/{id}'),
            new Put('/admin/actions/{id}'),
            new Delete('/admin/actions/{id}'),
            new GetCollection('/admin/actions'),
            new Post('/admin/actions'),
        ],
        normalizationContext: [
            'groups'           => ['read'],
            'enable_max_depth' => true,
        ],
        denormalizationContext: [
            'groups'           => ['write'],
            'enable_max_depth' => true,
        ],
    ),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(SearchFilter::class, properties: ['name' => 'exact', 'reference' => 'exact']),
    UniqueEntity('reference'),
    ORM\HasLifecycleCallbacks,
    ORM\Entity(repositoryClass: ActionRepository::class)
]
class Action
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     */
    #[
        Assert\Uuid,
        Groups(['read', 'write']),
        ORM\Id,
        ORM\Column(
            type: 'uuid',
            unique: true
        ),
        ORM\GeneratedValue,
        ORM\CustomIdGenerator(class: "Ramsey\Uuid\Doctrine\UuidGenerator")
    ]
    private $id;

    /**
     * @var string|null The reference of the action.
     */
    #[
        Groups(['read', 'write']),
        Assert\NotNull,
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true,
            options: ['default' => null]
        )
    ]
    private ?string $reference;

    /**
     * @var string The version of the action.
     */
    #[
        Groups(['read', 'write']),
        Assert\NotNull,
        ORM\Column(
            type: 'string',
            length: 255,
            options: ['default' => '0.0.0']
        )
    ]
    private string $version = '0.0.0';

    /**
     * @var string The name of the action
     */
    #[
        Assert\Length(max: 255),
        Assert\NotNull,
        Groups(['read', 'write', 'read_secure']),
        ORM\Column(
            type: 'string',
            length: 255
        ),
        Gedmo\Versioned
    ]
    private string $name;

    /**
     * @var string|null A description of this Organization.
     */
    #[
        Groups(['read', 'write', 'read_secure']),
        ORM\Column(
            type: 'text',
            nullable: true
        )
    ]
    private ?string $description = null;

    /**
     * @var array The event names the action should listen to
     */
    #[
        Groups(['read', 'write', 'read_secure']),
        ORM\Column(type: 'simple_array')
    ]
    private array $listens = [];

    /**
     * @var array|null The event names the action should trigger
     */
    #[
        Groups(['read', 'write', 'read_secure']),
        ORM\Column(
            type: 'simple_array',
            nullable: true
        )
    ]
    private ?array $throws = [];

    /**
     * @var array|null The conditions that the data object should match for the action to be triggered
     */
    #[
        Groups(['read', 'write', 'read_secure']),
        ORM\Column(
            type: 'json',
            nullable: true
        )
    ]
    private ?array $conditions = [];

    /**
     * @var string|null The class that should be run when the action is triggered
     */
    #[
        Groups(['read', 'write', 'read_secure']),
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true
        )
    ]
    private ?string $class = null;

    /**
     * @var int The priority of the action
     */
    #[
        Groups(['read', 'write', 'read_secure']),
        Assert\NotNull,
        ORM\Column(type: 'integer')
    ]
    private int $priority = 1;

    /**
     * @var bool Whether the action should be run asynchronous
     */
    #[
        Groups(['read', 'write', 'read_secure']),
        ORM\Column(type: 'boolean')
    ]
    private bool $async = false;

    /**
     * @var array|null The configuration of the action
     */
    #[
        Groups(['read', 'write', 'read_secure']),
        ORM\Column(
            type: 'array',
            nullable: true
        )
    ]
    private ?array $configuration = [];

    /**
     * @var string|null The userId of a user. This user will be used to run this Action for, if there is no logged-in user.
     * This helps when, for example: setting the organization of newly created ObjectEntities while running this Action.
     */
    #[
        Groups(['read', 'write']),
        Assert\NotNull,
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true
        )
    ]
    private ?string $userId = null;

    /**
     * @var bool Whether the action is lockable or not.
     */
    #[
        Groups(['read', 'write', 'read_secure']),
        ORM\Column(
            type: 'boolean',
            options: ['default' => false]
        )
    ]
    private bool $isLockable = false;

    /**
     * @var DateTimeInterface The date and time the action has been locked.
     */
    #[
        Groups(['read','write']),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private $locked;

    /**
     * @var DateTimeInterface The date and time the action has last been run.
     */
    #[
        Groups(['read','write']),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private $lastRun;

    /**
     * @var int|null The amount of time the action has run for during the last operation.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'integer',
            nullable: true,
            options: ['default' => 0]
        )
    ]
    private ?int $lastRunTime = 0;

    /**
     * @var ?bool true if last run went good and false if something went wrong.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'boolean',
            nullable: true,
            options: ['default' => null]
        )
    ]
    private ?bool $status = null;

     /**
      * @var ?bool true if action should be ran.
      */
     #[
     Groups(['read', 'write']),
     ORM\Column(
     type: 'boolean',
     nullable: true,
     options: ['default' => null]
     )
     ]
     private ?bool $isEnabled = true;

    /**
     * @var array|null The configuration of the action handler
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'array',
            length: 255,
            nullable: true
        )
    ]
    private ?array $actionHandlerConfiguration;

    /**
     * @var Datetime The moment this resource was created
     */
    #[
        Groups(['read']),
        Gedmo\Timestampable(on: 'create'),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private $dateCreated;

    /**
     * @var Datetime The moment this resource was last Modified
     */
    #[
        Groups(['read']),
        Gedmo\Timestampable(on: 'update'),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private $dateModified;

    public function __construct(
        $actionHandler = false
    ) {
        if ($actionHandler) {
            if (!$schema = $actionHandler->getConfiguration()) {
                return;
            }

            isset($schema['title']) ? $this->setName($schema['title']) : '';
            isset($schema['description']) ? $this->setDescription($schema['description']) : '';
            $this->setClass(get_class($actionHandler));
            $this->setConditions(['==' => [1, 1]]);
            $this->setConfiguration($this->getDefaultConfigFromSchema($schema));
        }
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function fromSchema(array $schema): self
    {
        if (!isset($schema['$schema']) || $schema['$schema'] != 'https://docs.commongateway.nl/schemas/Action.schema.json') {
            // todo: throw exception on wrong schema (requires design on references)
            // throw new GatewayException('The given schema is of the wrong type. It is '.$schema['$schema'].' but https://docs.commongateway.nl/schemas/Mapping.schema.json is required');
        }

        isset($schema['$id']) ? $this->setReference($schema['$id']) : '';
        isset($schema['title']) ? $this->setName($schema['title']) : '';
        isset($schema['description']) ? $this->setDescription($schema['description']) : '';
        isset($schema['version']) ? $this->setVersion($schema['version']) : '';
        isset($schema['listens']) ? $this->setListens($schema['listens']) : '';
        isset($schema['throws']) ? $this->setThrows($schema['throws']) : '';
        isset($schema['conditions']) ? $this->setConditions($schema['conditions']) : '';
        isset($schema['configuration']) ? $this->setConfiguration($schema['configuration']) : '';
        isset($schema['isLockable']) ? $this->setIsLockable($schema['isLockable']) : '';
        isset($schema['isEnabled']) ? $this->setIsEnabled($schema['isEnabled']) : '';
        isset($schema['class']) ? $this->setClass($schema['class']) : '';
        isset($schema['async']) ? $this->setAsync($schema['async']) : '';
        isset($schema['priority']) ? $this->setPriority($schema['priority']) : '';

        return  $this;
    }

    public function toSchema(): array
    {
        return [
            '$id'                    => $this->getReference(), //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'                => 'https://docs.commongateway.nl/schemas/Action.schema.json',
            'title'                  => $this->getName(),
            'description'            => $this->getDescription(),
            'version'                => $this->getVersion(),
            'listens'                => $this->getListens(),
            'throws'                 => $this->getThrows(),
            'conditions'             => $this->getConditions(),
            'configuration'          => $this->getConfiguration(),
            'isLockable'             => $this->getIsLockable(),
            'isEnabled'              => $this->getIsEnabled(),
            'async'                  => $this->getAsync(),
            'priority'               => $this->getPriority(),
        ];
    }

    /**
     * Gets the default config from a json schema definition of an ActionHandler.
     *
     * @param array $schema
     *
     * @return array
     */
    private function getDefaultConfigFromSchema(array $schema): array
    {
        $config = [];

        if (!isset($schema['properties'])) {
            return $config;
        }

        // Lets grap al the default values
        foreach ($schema['properties'] as $key => $property) {
            if (isset($property['default'])) {
                $config[$key] = $property['default'];
            }
        }

        return $config;
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getversion(): ?string
    {
        return $this->version;
    }

    public function setversion(string $version): self
    {
        $this->version = $version;

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

    public function getListens(): ?array
    {
        return $this->listens;
    }

    public function setListens(?array $listens): self
    {
        $this->listens = $listens;

        return $this;
    }

    public function getThrows(): ?array
    {
        return $this->throws;
    }

    public function setThrows(?array $throws): self
    {
        $this->throws = $throws;

        return $this;
    }

    public function getConditions(): ?array
    {
        return $this->conditions;
    }

    public function setConditions(?array $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(?string $class): self
    {
        $this->class = $class;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getAsync(): ?bool
    {
        return $this->async;
    }

    public function setAsync(bool $async): self
    {
        $this->async = $async;

        return $this;
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(?array $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getIsLockable(): ?bool
    {
        return $this->isLockable;
    }

    public function setIsLockable(?bool $isLockable): self
    {
        $this->isLockable = $isLockable;

        return $this;
    }

    public function getLocked(): ?\DateTimeInterface
    {
        return $this->locked;
    }

    public function setLocked(?\DateTimeInterface $locked): self
    {
        $this->locked = $locked;

        return $this;
    }

    public function getLastRun(): ?\DateTimeInterface
    {
        return $this->lastRun;
    }

    public function setLastRun(?\DateTimeInterface $lastRun): self
    {
        $this->lastRun = $lastRun;

        return $this;
    }

    public function getLastRunTime(): ?int
    {
        return $this->lastRunTime;
    }

    public function setLastRunTime(?int $lastRunTime): self
    {
        $this->lastRunTime = $lastRunTime;

        return $this;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(?bool $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getActionHandlerConfiguration(): ?array
    {
        return $this->actionHandlerConfiguration;
    }

    public function setActionHandlerConfiguration(?array $actionHandlerConfiguration): self
    {
        $this->actionHandlerConfiguration = $actionHandlerConfiguration;

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
}
