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
 *     "name": "exact"
 * })
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
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Organization $organization = null;

    /**
     * @var array The schemas supported by this template
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="array")
     */
    private $supportedSchemas = [];

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
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default": null})
     */
    private ?string $reference = null;

    public function __construct(array $data) {
        if (isset($data['organization']) === true) {
            $this->setOrganization($data['organization']);
        }
        if (isset($data['name']) === true) {
            $this->setName($data['name']);
        }
        if (isset($data['content']) === true) {
            $this->setContent($data['content']);
        }
        if (isset($data['description'])) {
            $this->setDescription($data['description']);
        }
        if (isset($data['supportedSchemas']) === true) {
            $this->setSupportedSchemas($data['supportedSchemas']);
        }
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }
}
