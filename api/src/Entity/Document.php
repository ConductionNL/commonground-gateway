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
use App\Repository\DocumentRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about a document.
 */
#[
    ApiResource(
        operations: [
            new Get("/admin/documents/{id}"),
            new Put("/admin/documents/{id}"),
            new Delete("/admin/documents/{id}"),
            new GetCollection("/admin/documents"),
            new Post("/admin/documents")
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
    ORM\Entity(repositoryClass: DocumentRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(SearchFilter::class)
]
class Document
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
        ORM\GeneratedValue(strategy: 'CUSTOM'),

        ORM\CustomIdGenerator(class: UuidGenerator::class)
    ]
    private ?UuidInterface $id = null;

    /**
     * @var string The name of this Document.
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true
        )
    ]
    private string $name;

    /**
     * @var string The route of this Document.
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true
        )
    ]
    private string $route;

    /**
     * @Groups({"read","write"})
     */
    #[
        Groups(['read', 'write']),
        MaxDepth(1),
        ORM\ManyToOne(
            targetEntity: Entity::class,
            fetch: 'EAGER'
        ),
        ORM\JoinColumn(nullable: true)
    ]
    private ?Entity $object = null;

    /**
     * @var string The data of this Document.
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        ORM\Column(
            type: 'string',
            length: 255
        )
    ]
    private string $data;

    /**
     * @var string The data id of this Document.
     *
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        ORM\Column(
            type: 'string',
            length: 255
        )
    ]
    private string $dataId;

    /**
     * @var string An uri to the document creation service.
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        ORM\Column(
            type: 'string',
            length: 255
        )
    ]
    private string $documentCreationService;

    /**
     * @var string The type of this Document.
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        ORM\Column(
            type: 'string',
            length: 255
        )
    ]
    private string $documentType;

    /**
     * @var string|null The type of template
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true
        )
    ]
    private ?string $type;

    /**
     * @var string|null The content of the template
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'text',
            nullable: true
        )
    ]
    private ?string $content;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(string $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getObject(): ?Entity
    {
        return $this->object;
    }

    public function setObject(?Entity $object): self
    {
        $this->type = 'object';
        $this->object = $object;

        return $this;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getDataId(): ?string
    {
        return $this->dataId;
    }

    public function setDataId(string $dataId): self
    {
        $this->dataId = $dataId;

        return $this;
    }

    public function getDocumentCreationService(): ?string
    {
        return $this->documentCreationService;
    }

    public function setDocumentCreationService(string $documentCreationService): self
    {
        $this->documentCreationService = $documentCreationService;

        return $this;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): self
    {
        $this->documentType = $documentType;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

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
