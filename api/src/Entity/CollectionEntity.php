<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\CollectionEntityRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about a Collections.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/collections/{id}"},
 *      "put"={"path"="/admin/collections/{id}"},
 *      "delete"={"path"="/admin/collections/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/collections"},
 *      "post"={"path"="/admin/collections"}
 *  })
 *
 * @ORM\Entity(repositoryClass=CollectionEntityRepository::class)
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact",
 *     "reference": "exact"
 * })
 *
 * @UniqueEntity("name")
 */
class CollectionEntity
{
    /**
     * @var UuidInterface The UUID identifier of this Entity.
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
     * @var string The name of this Collection
     *
     * @Assert\NotNull
     *
     * @Assert\Type("string")
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private string $name;

    /**
     * @var ?string The description of this Collection
     *
     * @Assert\Type("string")
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default":null})
     */
    private ?string $description = null;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default": null})
     */
    private ?string $reference = null;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default": null})
     */
    private ?string $version = null;

    /**
     * @var ?string The location where the OAS can be loaded from
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="https://raw.githubusercontent.com/conductionnl/commonground-gateway/master/public/schema/openapi.yaml"
     *         }
     *     }
     * )
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default":null})
     */
    private ?string $locationOAS = null;

    /**
     * @var ?Gateway|string The source of this Collection
     *
     * @Groups({"write"})
     *
     * @ORM\JoinColumn(nullable=true)
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToOne(targetEntity=Gateway::class, inversedBy="collections", fetch="EXTRA_LAZY")
     */
    private $source;

    /**
     * @var ?string The url of this Collection
     *
     * @Assert\Type("string")
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default":null})
     */
    private ?string $sourceUrl = null;

    /**
     * @var ?string The source type of this Collection
     *
     * @Assert\Type("string")
     *
     * @Assert\Choice({"url", "GitHub"})
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default":null})
     */
    private ?string $sourceType = null;

    /**
     * @var ?string The source branch of this Collection
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default":null})
     */
    private ?string $sourceBranch = null;

    /**
     * @var ?string The location where the test data set can be found
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default":null})
     */
    private ?string $testDataLocation = null;

    /**
     * @var bool Wether or not the test data from the location above should be loaded. Defaults to false
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    private bool $loadTestData = false;

    /**
     * @var ?DateTimeInterface The moment this Collection was synced
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="datetime", nullable=true, options={"default":null})
     */
    private ?DateTimeInterface $syncedAt = null;

    /**
     * @var bool Wether or not this Collection's config and testdata should be loaded when fixtures are loaded
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    private bool $autoLoad = false;

    /**
     * @var ?string The prefix for all endpoints on this Collection
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $prefix = null;

    /**
     * @var ?Collection The applications of this Collection
     *
     * @Groups({"write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=Application::class, inversedBy="collections", fetch="EXTRA_LAZY")
     */
    private ?Collection $applications;

    /**
     * @var ?Collection The endpoints of this Collection
     *
     * @Groups({"write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=Endpoint::class, inversedBy="collections", fetch="EXTRA_LAZY")
     */
    private ?Collection $endpoints;

    /**
     * @var ?Collection The entities of this Collection
     *
     * @Groups({"write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=Entity::class, inversedBy="collections", fetch="EXTRA_LAZY")
     */
    private ?Collection $entities;

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
     * @todo
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $plugin;

    public function __construct(?string $name = null, ?string $prefix = null, ?string $plugin = null)
    {
        if ($name) {
            $this->setName($name);
        }
        if ($prefix) {
            $this->setPrefix($prefix);
        }
        if ($plugin) {
            $this->setPlugin($plugin);
        }
        $this->applications = new ArrayCollection();
        $this->endpoints = new ArrayCollection();
        $this->entities = new ArrayCollection();
    }

    /**
     * Create or update this CollectionEntity from an external schema array.
     *
     * This function is used to update and create collections form collection.json objects.
     *
     * @param array $schema The schema to load.
     *
     * @return $this This CollectionEntity.
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
        array_key_exists('prefix', $schema) ? $this->setPrefix($schema['prefix']) : '';
        array_key_exists('plugin', $schema) ? $this->setPlugin($schema['plugin']) : '';

        return $this;
    }

    /**
     * Convert this CollectionEntity to a schema.
     *
     * @return array Schema array.
     */
    public function toSchema(): array
    {
        return [
            '$id'         => $this->getReference(), //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'     => 'https://docs.commongateway.nl/schemas/CollectionEntity.schema.json',
            'title'       => $this->getName(),
            'description' => $this->getDescription(),
            'version'     => $this->getVersion(),
            'name'        => $this->getName(),
            'prefix'      => $this->getPrefix(),
            'plugin'      => $this->getPlugin(),
        ];
    }

    public function export()
    {
        if ($this->getSource() !== null) {
            $source = $this->getSource()->getId()->toString();
            $source = '@'.$source;
        } else {
            $source = null;
        }

        $data = [
            'name'                    => $this->getName(),
            'description'             => $this->getDescription(),
            'source'                  => $source,
            'sourceType'              => $this->getSourceType(),
            'sourceBranch'            => $this->getSourceBranch(),
            'syncedAt'                => $this->getSyncedAt(),
            'applications'            => $this->getApplications(),
            'endpoints'               => $this->getEndpoints(),
            'entities'                => $this->getEntities(),
        ];

        return array_filter($data, fn ($value) => !is_null($value) && $value !== '' && $value !== []);
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

    public function getLocationOAS(): ?string
    {
        return $this->locationOAS;
    }

    public function setLocationOAS(?string $locationOAS): self
    {
        $this->locationOAS = $locationOAS;

        return $this;
    }

    public function getSource(): ?Gateway
    {
        return $this->source;
    }

    public function setSource(?Gateway $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(?string $sourceType): self
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getSourceBranch(): ?string
    {
        return $this->sourceBranch;
    }

    public function setSourceBranch(?string $sourceBranch): self
    {
        $this->sourceBranch = $sourceBranch;

        return $this;
    }

    public function getTestDataLocation(): ?string
    {
        return $this->testDataLocation;
    }

    public function setTestDataLocation(?string $testDataLocation): self
    {
        $this->testDataLocation = $testDataLocation;

        return $this;
    }

    public function getLoadTestData(): bool
    {
        return $this->loadTestData;
    }

    public function setLoadTestData(bool $loadTestData): self
    {
        $this->loadTestData = $loadTestData;

        return $this;
    }

    public function getAutoLoad(): bool
    {
        return $this->autoLoad;
    }

    public function setAutoLoad(bool $autoLoad): self
    {
        $this->autoLoad = $autoLoad;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function setPrefix(?string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getSyncedAt(): ?\DateTimeInterface
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?\DateTimeInterface $syncedAt): self
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    /**
     * @return Collection|Application[]
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function addApplication(Application $application): self
    {
        if (!$this->applications->contains($application)) {
            $this->applications[] = $application;
        }

        return $this;
    }

    public function removeApplication(Application $application): self
    {
        $this->applications->removeElement($application);

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
     * @return Collection|Entity[]
     */
    public function getEntities(): Collection
    {
        return $this->entities;
    }

    public function addEntity(Entity $entity): self
    {
        if (!$this->entities->contains($entity)) {
            $this->entities[] = $entity;
        }

        return $this;
    }

    public function removeEntity(Entity $entity): self
    {
        $this->entities->removeElement($entity);

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

    public function getPlugin(): ?string
    {
        return $this->plugin;
    }

    public function setPlugin(?string $plugin): self
    {
        $this->plugin = $plugin;

        return $this;
    }
}
