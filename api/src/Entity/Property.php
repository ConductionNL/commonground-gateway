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
use App\Repository\PropertyRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An entity containing path properties.
 * @deprecated
 */
#[
    ApiResource(
        operations: [
            new Get(          "/admin/properties/{id}"),
            new Put(          "/admin/properties/{id}"),
            new Delete(       "/admin/properties/{id}"),
            new GetCollection("/admin/properties"),
            new Post(         "/admin/properties")
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
    ORM\Entity(repositoryClass: PropertyRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(SearchFilter::class,)
]
class Property
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
        ORM\GeneratedValue,
        ORM\CustomIdGenerator(class: "Ramsey\Uuid\Doctrine\UuidGenerator")
    ]
    private UuidInterface $id;

    /**
     * @var string The name of this Application.
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
     * @var string Type of where this property is in a Endpoint.path
     */
    #[
        Groups(['read', 'write']),
        Assert\NotNull,
        Assert\Choice([
            'enum',
            'query',
            'path'
        ]),
        ORM\Column(
            type: 'string',
            length: 5
        )
    ]
    private string $inType;

    /**
     * @var bool|null Whether this Property is required or not
     */
    #[
        Groups(['read', 'write']),
        Assert\Type('boolean'),
        ORM\Column(
            type: 'boolean',
            nullable: true
        )
    ]
    private ?bool $required;

    /**
     * @var array|null Schema of this property
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'array',
            nullable: true
        )
    ]
    private ?array $schemaArray;

    /**
     * @var int|null Order of this Property if there are more Properties in a Endpoint
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'integer',
            nullable: true
        )
    ]
    private ?int $pathOrder;

    /**
     * @var Endpoint|null Endpoint of this Property
     */
    #[
        Groups(['read', 'write']),
        ORM\ManyToOne(
            targetEntity: Endpoint::class,
            inversedBy: 'properties'
        )
    ]
    private ?Endpoint $endpoint;

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

    public function __toString()
    {
        return $this->getName();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getInType(): ?string
    {
        return $this->inType;
    }

    public function setInType(string $inType): self
    {
        $this->inType = $inType;

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

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    public function setRequired(?bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    public function getSchemaArray(): ?array
    {
        return $this->schemaArray;
    }

    public function setSchemaArray(?array $schemaArray): self
    {
        $this->schemaArray = $schemaArray;

        return $this;
    }

    public function getPathOrder(): ?int
    {
        return $this->pathOrder;
    }

    public function setPathOrder(?int $pathOrder): self
    {
        $this->pathOrder = $pathOrder;

        return $this;
    }

    public function getEndpoint(): ?Endpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(?Endpoint $endpoint): self
    {
        $this->endpoint = $endpoint;

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
