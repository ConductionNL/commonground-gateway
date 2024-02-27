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
use App\Repository\TemplateRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A template to create custom renders of an object.
 */
#[
    ApiResource(
        operations: [
            new Get(          "/admin/templates/{id}"),
            new Put(          "/admin/templates/{id}"),
            new Delete(       "/admin/templates/{id}"),
            new GetCollection("/admin/templates"),
            new Post(         "/admin/templates")
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
    ORM\Entity(repositoryClass: TemplateRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(
        SearchFilter::class,
        properties: [
            'name'            => 'exact',
            'reference'       => 'exact',
            'supportedShemas' => 'exact'
        ]
    ),
    UniqueEntity('reference')
]
class Template
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
    private ?UuidInterface $id = null;

    /**
     * @var string The name of this Template.
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        Assert\NotNull,
        Gedmo\Versioned,
        ORM\Column(
            type: 'string',
            length: 255
        )
    ]
    private string $name;

    /**
     * @var string|null A description of this Template.
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
     * @var string|null The reference of the Template
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
     * @var string The version of the Template.
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
     * @var string|null The .html.twig template
     */
    #[
        Groups(['read', 'write']),
        Gedmo\Versioned,
        ORM\Column(
            type: 'text',
            nullable: true
        )
    ]
    private ?string $content = null;

    /**
     * @var Organization|null the organization this template belongs to
     */
    #[
        Groups(['read', 'write']),
        ORM\JoinColumn(nullable: true),
        ORM\ManyToOne(
            targetEntity: Organization::class,
            inversedBy: 'templates'
        )
    ]
    private ?Organization $organization = null;

    /**
     * @var array The schemas supported by this template
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="array")
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'array'
        )
    ]
    private array $supportedSchemas = [];

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


    /**
     * Constructor for creating an Template.
     *
     * @param array|null   $configuration A configuration array used to correctly create a Template. The following keys are supported:
     *
     */
    public function __construct(?array $configuration = [])
    {
        if ($configuration) {
            $this->fromSchema($configuration);
        }
    }

    /**
     * Uses given $configuration array to set the properties of this Template.
     *
     * @param array $schema  The schema to load.
     *
     * @return void
     */
    public function fromSchema(array $schema)
    {
        if (key_exists('$id', $schema) === true) {
            $this->setReference($schema['$id']);
        }
        if (key_exists('version', $schema) === true) {
            $this->setVersion($schema['version']);
        }

        if (key_exists('name', $schema) === true) {
            $this->setName($schema['name']);
        }

        if (key_exists('description', $schema) === true) {
            $this->setDescription($schema['description']);
        }

        if (key_exists('content', $schema) === true) {
            $this->setContent($schema['content']);
        }

        if (key_exists('organization', $schema) === true) {
            $this->setOrganization($schema['organization']);
        }

        if (key_exists('supportedSchemas', $schema) === true) {
            $this->setSupportedSchemas($schema['supportedSchemas']);
        }
    }

    /**
     * Convert this Template to a schema.
     *
     * @return array Schema array.
     */
    public function toSchema(): array
    {
        return [
            '$id'              => $this->getReference(), //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'          => 'https://docs.commongateway.nl/schemas/Template.schema.json',
            'name'             => $this->getName(),
            'description'      => $this->getDescription(),
            'content'          => $this->getContent(),
            'version'          => $this->getVersion(),
            'organization'     => $this->getOrganization(),
            'supportedSchemas' => $this->getSupportedSchemas(),
        ];
    }

    public function setId(UuidInterface $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?UuidInterface
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getSupportedSchemas(): ?array
    {
        return $this->supportedSchemas;
    }

    public function setSupportedSchemas(array $supportedSchemas): self
    {
        $this->supportedSchemas = $supportedSchemas;

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

    public function setDateModified(?DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }
}
