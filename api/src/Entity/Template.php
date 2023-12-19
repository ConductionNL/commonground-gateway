<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\TemplateRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A template to create custom renders of an object.
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/templates/{id}"},
 *      "put"={"path"="/admin/templates/{id}"},
 *      "delete"={"path"="/admin/templates/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/templates"},
 *      "post"={"path"="/admin/templates"}
 *  }))
 *
 * @ORM\Entity(repositoryClass=TemplateRepository::class)
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "supportedSchemas": "exact",
 *     "name": "exact",
 *     "reference": "exact"
 * })
 *
 * @UniqueEntity("reference")
 */
class Template
{
    /**
     * @var UuidInterface The UUID identifier of this object
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Groups({"read"})
     *
     * @Assert\Uuid
     *
     * @ORM\Id
     *
     * @ORM\Column(type="uuid", unique=true)
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private UuidInterface $id;

    /**
     * @var string The name of this Template
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
    private string $name = '';

    /**
     * @var string|null The description of this Template
     *
     * @Gedmo\Versioned
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @var string|null The .html.twig template
     *
     * @Gedmo\Versioned
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $content = null;

    /**
     * @var Organization|null the organization this template belongs to
     *
     * @Groups({"read","write"})
     *
     * @ORM\ManyToOne(targetEntity=Organization::class, inversedBy="templates")
     *
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Organization $organization = null;

    /**
     * @var array The schemas supported by this template
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="array")
     */
    private array $supportedSchemas = [];

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $reference = null;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $version = '0.0.1';

    /**
     * @var Datetime|null The moment this resource was created
     *
     * @Groups({"read"})
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $dateCreated = null;

    /**
     * @var Datetime|null The moment this resource was last Modified
     *
     * @Groups({"read"})
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $dateModified = null;


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
}
