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
use App\Repository\CronjobRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An entity that holds a cronjob.
 */
#[
    ApiResource(
        operations: [
            new Get("/admin/cronjobs/{id}"),
            new Put("/admin/cronjobs/{id}"),
            new Delete("/admin/cronjobs/{id}"),
            new GetCollection("/admin/cronjobs"),
            new Post("/admin/cronjobs")
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
    ORM\Entity(repositoryClass: CronjobRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(SearchFilter::class, properties: ['name' => 'exact', 'reference' => 'exact']),
    UniqueEntity('reference')
]
class Cronjob
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
    private UuidInterface $id;

    /**
     * @var string The name of this Application.
     */
    #[
        Assert\Length(max: 255),
        Assert\NotNull,
        Groups(['read', 'write']),
        ORM\Column(
            type: 'string',
            length: 255
        ),
        Gedmo\Versioned
    ]
    private string $name;

    /**
     * @var string|null A description of this Application.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'text',
            nullable: true
        )
    ]
    private ?string $description = null;

    /**
     * @var string|null The reference of the application
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
    private ?string $reference = null;

    /**
     * @var string The version of the application.
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
     * @var string The crontab that determines the interval https://crontab.guru/
     *             defaulted at every 5 minutes * / 5  *  *  *  *
     */
    #[
        Groups(['read', 'write']),
        Assert\Regex(
            pattern: '/(^((\*\/)?([0-5]?[0-9])((\,|\-|\/)([0-5]?[0-9]))*|\*)\s+((\*\/)?((2[0-3]|1[0-9]|[0-9]|00))((\,|\-|\/)(2[0-3]|1[0-9]|[0-9]|00))*|\*)\s+((\*\/)?([1-9]|[12][0-9]|3[01])((\,|\-|\/)([1-9]|[12][0-9]|3[01]))*|\*)\s+((\*\/)?([1-9]|1[0-2])((\,|\-|\/)([1-9]|1[0-2]))*|\*|(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|des))\s+((\*\/)?[0-6]((\,|\-|\/)[0-6])*|\*|00|(sun|mon|tue|wed|thu|fri|sat))\s*$)|@(annually|yearly|monthly|weekly|daily|hourly|reboot)/',
            message: 'This is an invalid crontab, see https://crontab.guru/ to create an interval',
            match: true
        ),
        ORM\Column(
            type: 'string',
            length: 255
        )
    ]
    private string $crontab = '*/5 * * * *';

    /**
     * @var string|null The userId of a user. This user will be used to run this Cronjob for, if there is no logged-in user.
     * This helps when, for example: setting the organization of newly created ObjectEntities while running this Cronjob.
     */
    #[
        Groups(['read' , 'write']),
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true
        )
    ]
    private ?string $userId = null;

    /**
     * @var array The actions that put on the stack by the crontab.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(type: 'array')
    ]
    private array $throws = [];

    /**
     * @var array|null The optional data array of this Cronjob
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'array',
            nullable: true
        )
    ]
    private ?array $data = [];

    /**
     * @var DatetimeInterface|null The last run of this Cronjob
     */
    #[
        Groups(['read']),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private ?DateTimeInterface $lastRun = null;

    /**
     * @var DateTimeInterface|null The next run of this Cronjob
     */
    #[
        Groups(['read']),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private ?DateTimeInterface $nextRun = null;

    /**
     * @var ?bool true if action should be ran
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'boolean',
            nullable: true,
            options: ['default' => true]
        )
    ]
    private ?bool $isEnabled = true;

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

    public function __construct(
        $action = false
    ) {
        if ($action) {
            $this->setName($action->getName());
            $this->setDescription($action->getDescription());
            $this->setThrows([reset($action->getListens()->first())]);
        }
    }

    /**
     * Create or update this Cronjob from an external schema array.
     *
     * This function is used to update and create cronjobs form cronjob.json objects.
     *
     * @param array $schema The schema to load.
     *
     * @return $this This Cronjob.
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
        array_key_exists('crontab', $schema) ? $this->setCrontab($schema['crontab']) : '';
        array_key_exists('data', $schema) ? $this->setData($schema['data']) : '';
        array_key_exists('isEnabled', $schema) ? $this->setIsEnabled($schema['isEnabled']) : '';
        array_key_exists('throws', $schema) ? $this->setThrows($schema['throws']) : '';

        return $this;
    }

    /**
     * Convert this Cronjob to a schema.
     *
     * @return array Schema array.
     */
    public function toSchema(): array
    {
        return [
            '$id'         => $this->getReference(), //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'     => 'https://docs.commongateway.nl/schemas/Cronjob.schema.json',
            'title'       => $this->getName(),
            'description' => $this->getDescription(),
            'version'     => $this->getVersion(),
            'name'        => $this->getName(),
            'crontab'     => $this->getCrontab(),
            'data'        => $this->getData(),
            'isEnabled'   => $this->getIsEnabled(),
            'throws'      => $this->getThrows(),
        ];
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getId()
    {
        return $this->id;
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

    public function getCrontab(): ?string
    {
        return $this->crontab;
    }

    public function setCrontab(string $crontab): self
    {
        $this->crontab = $crontab;

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

    public function getThrows(): ?array
    {
        return $this->throws;
    }

    public function setThrows(array $throws): self
    {
        $this->throws = $throws;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getLastRun(): ?DateTimeInterface
    {
        return $this->lastRun;
    }

    public function setLastRun(?DateTimeInterface $lastRun): self
    {
        $this->lastRun = $lastRun;

        return $this;
    }

    public function getNextRun(): ?DateTimeInterface
    {
        return $this->nextRun;
    }

    public function setNextRun(?DateTimeInterface $nextRun): self
    {
        $this->nextRun = $nextRun;

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

    public function getDateCreated(): ?DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?DateTimeInterface $dateCreated): self
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
