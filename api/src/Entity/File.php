<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\FileRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about a file.
 */
#[
    ApiResource(
        operations: [
            new Get(          "/admin/files/{id}"),
            new Put(          "/admin/files/{id}"),
            new Delete(       "/admin/files/{id}"),
            new GetCollection("/admin/files"),
            new Post(         "/admin/files")
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
    ORM\Entity(repositoryClass: FileRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(SearchFilter::class)
]
class File
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
     * @var string The name of this Application.
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        Assert\NotNull,
        ORM\Column(
            type: 'string',
            length: 255
        ),
        Gedmo\Versioned
    ]
    private string $name;

    /**
     * @var string The extension of this File
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
    private string $extension;

    /**
     * @var string The mimeType of this File
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
    private string $mimeType;

    /**
     * @var string The size of this File
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
    private string $size;

    /**
     * @var string The base encoded string of this file
     */
    #[
        Groups(['read', 'write']),
        Assert\NotNull,
        Gedmo\Versioned,
        ORM\Column(
            type: 'text'
        )
    ]
    private string $base64;

    /**
     * @var Value The value the file belongs to.
     */
    #[
        Groups(['read', 'write']),
        ORM\ManyToOne(
            targetEntity: Value::class,
            inversedBy: 'files'
        )
    ]
    private Value $value;

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

    public function __construct()
    {
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function setSize(string $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getBase64(): string
    {
        return $this->base64;
    }

    public function setBase64(string $base64): self
    {
        $this->base64 = $base64;

        return $this;
    }

    public function getValue(): ?Value
    {
        return $this->value;
    }

    public function setValue(?Value $value): self
    {
        $this->value = $value;

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
