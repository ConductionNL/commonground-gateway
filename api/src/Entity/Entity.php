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
use App\Entity\Gateway as Source;
use App\Exception\GatewayException;
use App\Repository\EntityRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use phpDocumentor\Reflection\Types\This;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A schema that functions as an object template for objects that might be stored in the EAV database.
 */
#[
    ApiResource(
        operations: [
            new Get(          "/admin/entities/{id}"),
            new Put(          "/admin/entities/{id}"),
            new Delete(       "/admin/entities/{id}"),
            new GetCollection("/admin/entities"),
            new Post(         "/admin/entities"),
            new Get(          "/admin/schemas/{id}"),
            new Put(          "/admin/schemas/{id}"),
            new Delete(       "/admin/schemas/{id}"),
            new GetCollection("/admin/schemas"),
            new Post(         "/admin/schemas")
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
    ORM\Entity(repositoryClass: EntityRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(
        SearchFilter::class,
        properties: [
            'name'          => 'exact',
            'reference'     => 'exact',
        ]
    ),
    UniqueEntity('reference')
]
class Entity
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
     * @var bool whether the properties of the original object are automatically include.
     */
    #[
        Groups(['read', 'write']),
        Assert\Type('bool'),
        ORM\Column(
            type: 'boolean',
            nullable: true
        )
    ]
    private ?bool $extend = false;

    /**
     * Whether objects created from this entity should be available to child organizations.
     */
    #[
        Groups(['read', 'write']),
        Assert\Type('boolean'),
        ORM\Column(
            type: 'boolean',
            nullable: true
        )
    ]
    private $inherited = false;

    /**
     * @var Collection The attributes of this Schema.
     */
    #[
        Groups(['read', 'write']),
        MaxDepth(1),
        ORM\OneToMany(
            mappedBy: 'entity',
            targetEntity: Attribute::class,
            cascade: ['persist'],
            fetch: 'EAGER',
            orphanRemoval: true
        )
    ]
    private Collection $attributes;

    /**
     * @var Collection The attributes allowed to partial search on using the search query parameter.
     * @deprecated
     */
    #[

        Groups(['read', 'write']),
        MaxDepth(1),
        ORM\OneToMany(
            mappedBy: 'searchPartial',
            targetEntity: Attribute::class,
            fetch: 'EAGER'
        )
    ]
    private Collection $searchPartial;

    /**
     * @var Collection The object entities of this Schema.
     */
    #[
        Groups(['write']),
        MaxDepth(1),
        ORM\OrderBy(['dateCreated' => 'DESC']),
        ORM\OneToMany(
            mappedBy: 'entity',
            targetEntity: ObjectEntity::class,
            cascade: ['remove'],
            fetch: 'EXTRA_LAZY'
        )
    ]
    private Collection $objectEntities;

    /**
     * @var Collection Attributes using this Schema
     */
    #[
        Groups(['write']),
        MaxDepth(1),
        ORM\OneToMany(
            mappedBy: 'object',
            targetEntity: Attribute::class,
            fetch: 'EXTRA_LAZY'
        )
    ]
    private Collection $usedIn;

    /**
     * @var ?Collection The collections of this Entity
     */
    #[
        Groups(['read', 'write']),
        MaxDepth(1),
        ORM\OrderBy(['dateCreated' => 'DESC']),
        ORM\ManyToMany(
            targetEntity: CollectionEntity::class,
            mappedBy: 'entities'
        )
    ]
    private ?Collection $collections;

    /**
     * @var array|null The properties used to set the name for ObjectEntities created linked to this Entity.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'array',
            nullable: true
        )
    ]
    private ?array $nameProperties = [];

    /**
     * @var int The maximum depth that should be used when casting objects of this entity to array
     */
    #[
        Groups(['read', 'write']),
        Assert\Type('integer'),
        Assert\Length(max: 1),
        ORM\Column(
            type: 'integer',
            length: 1,
            options: ['default' => 3]
        )
    ]
    private int $maxDepth = 3;

    /**
     * @var Collection The endpoints of the entity.
     */
    #[
        ORM\ManyToMany(
            targetEntity: Endpoint::class,
            mappedBy: 'entities'
        )
    ]
    private Collection $endpoints;

    /**
     * @var bool Whether the entity should be excluded from rendering as sub object
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'boolean',
            nullable: true,
            options: ['default' => false]
        )
    ]
    private bool $exclude = false;

    /**
     * @var bool Whether the object of the entity should be persisted to the database
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'boolean',
            nullable: true,
            options: ['default' => true]
        )
    ]
    private bool $persist = true;

    /**
     * @var bool Whether audittrails have to be created for the entity
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'boolean',
            nullable: true,
            options: ['default' => false]
        )
    ]
    private bool $createAuditTrails = false;

    /**
     * @var Source|null The default source to synchronise to.
     */
    #[
        Groups(['read', 'write']),
        ORM\ManyToOne(
            targetEntity: Gateway::class,
            cascade: ['persist', 'remove']
        )
    ]
    private ?Source $defaultSource = null;

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
        return $this->getName().' ('.$this->getId().')';
    }

    public function __construct()
    {
        $this->attributes = new ArrayCollection();
        $this->objectEntities = new ArrayCollection();
        $this->usedIn = new ArrayCollection();
        $this->collections = new ArrayCollection();
        $this->endpoints = new ArrayCollection();
        $this->searchPartial = new ArrayCollection();
    }

    public function export()
    {
        if ($this->getDefaultSource() !== null) {
            $source = $this->getDefaultSource()->getId()->toString();
            $source = '@'.$source;
        } else {
            $source = null;
        }

        $data = [
            'gateway'             => $source,
            'name'                => $this->getName(),
            'description'         => $this->getDescription(),
            'extend'              => $this->getExtend(),
        ];

        return array_filter($data, fn ($value) => !is_null($value) && $value !== '' && $value !== []);
    }

    private const SUPPORTED_VALIDATORS = [
        'multipleOf',
        'maximum',
        'exclusiveMaximum',
        'minimum',
        'exclusiveMinimum',
        'maxLength',
        'minLength',
        'maxItems',
        'uniqueItems',
        'maxProperties',
        'minProperties',
        'required',
        'enum',
        'allOf',
        'oneOf',
        'anyOf',
        'not',
        'items',
        'additionalProperties',
        'default',
    ];

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
        // New (example: ObjectEntity will become object_entity)
        // lets make sure this name is slugable
        // $name = trim($name); //removes whitespace at begin and ending
        // $firstChar = strtolower($name[0]); // get first char because we dont want to set a _ before first capital
        // $name = substr($name, 1); // subtract first character
        // $name = preg_replace('/(?<!\ )[A-Z]/', '_$0', $name); // change upper chars to lower and put a _ in front of it
        // $name = $firstChar . strtolower($name); // combine strings

        // Old (example: ObjectEntity would become objectentity)
        // $name = trim($name); //removes whitespace at begin and ending
        // $name = preg_replace('/\s+/', '_', $name); // replaces other whitespaces with _
        // $name = $firstChar . strtolower($name); // combine strings

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

    /**
     * Get an value based on a attribut.
     *
     * @param string $name the name of the attribute that you are searching for
     *
     * @return Attribute|bool Iether the found attribute or false if no attribute could be found
     */
    public function getAttributeByName(string $name): Attribute|bool
    {
        // Check if value with this attribute exists for this ObjectEntity
        $criteria = Criteria::create()->andWhere(Criteria::expr()->eq('name', $name))->setMaxResults(1);
        $attributes = $this->getAttributes()->matching($criteria);

        if ($attributes->isEmpty()) {
            return false;
        }

        return $attributes->first();
    }

    /**
     * @return Collection|Attribute[]
     */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(Attribute $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes[] = $attribute;
            $attribute->setEntity($this);
        }

        return $this;
    }

    public function removeAttribute(Attribute $attribute): self
    {
        if ($this->attributes->removeElement($attribute)) {
            // set the owning side to null (unless already changed)
            if ($attribute->getEntity() === $this) {
                $attribute->setEntity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ObjectEntity[]
     */
    public function getObjectEntities(): Collection
    {
        return $this->objectEntities;
    }

    public function addObjectEntity(ObjectEntity $objectEntity): self
    {
        if (!$this->objectEntities->contains($objectEntity)) {
            $this->objectEntities[] = $objectEntity;
            $objectEntity->setEntity($this);
        }

        return $this;
    }

    public function removeObjectEntity(ObjectEntity $objectEntity): self
    {
        if ($this->objectEntities->removeElement($objectEntity)) {
            // set the owning side to null (unless already changed)
            if ($objectEntity->getEntity() === $this) {
                $objectEntity->setEntity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Attribute[]
     */
    public function getUsedIn(): Collection
    {
        return $this->usedIn;
    }

    public function addUsedIn(Attribute $attribute): self
    {
        if (!$this->usedIn->contains($attribute)) {
            $this->usedIn[] = $attribute;
            $attribute->setObject($this);
        }

        return $this;
    }

    public function removeUsedIn(Attribute $attribute): self
    {
        if ($this->usedIn->removeElement($attribute)) {
            // set the owning side to null (unless already changed)
            if ($attribute->getObject() === $this) {
                $attribute->setObject(null);
            }
        }

        return $this;
    }

    public function getExtend(): ?bool
    {
        return $this->extend;
    }

    public function setExtend(?bool $extend): self
    {
        $this->extend = $extend;

        return $this;
    }

    public function getInherited(): ?bool
    {
        return $this->inherited;
    }

    public function setInherited(?bool $inherited): self
    {
        $this->inherited = $inherited;

        return $this;
    }

    /**
     * @return Collection|CollectionEntity[]
     */
    public function getCollections(): Collection
    {
        return $this->collections;
    }

    public function addCollection(CollectionEntity $collection): self
    {
        if (!$this->collections->contains($collection)) {
            $this->collections[] = $collection;
            $collection->addEntity($this);
        }

        return $this;
    }

    public function removeCollection(CollectionEntity $collection): self
    {
        if ($this->collections->removeElement($collection)) {
            $collection->removeEntity($this);
        }

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

    /**
     * Create or update this schema from an external schema array.
     *
     * This function is used to update and create schema's form schema.json objects
     *
     * @param array $schema The schema to load.
     *
     * @return $this This schema.
     */
    public function fromSchema(array $schema): self
    {
        // Basic stuff.
        if (array_key_exists('$id', $schema)) {
            $this->setReference($schema['$id']);
        }
        if (array_key_exists('title', $schema)) {
            $this->setName($schema['title']);
        }
        if (array_key_exists('description', $schema)) {
            $this->setDescription($schema['description']);
        }
        if (array_key_exists('version', $schema)) {
            $this->setVersion($schema['version']);
        }
        if (array_key_exists('exclude', $schema)) {
            $this->setExclude($schema['exclude']);
        }
        if (array_key_exists('maxDepth', $schema)) {
            $this->setMaxDepth($schema['maxDepth']);
        }
        if (array_key_exists('nameProperties', $schema)) {
            $this->setNameProperties($schema['nameProperties']);
        }
        if (array_key_exists('createAuditTrails', $schema)) {
            $this->setCreateAuditTrails($schema['createAuditTrails']);
        }

        // Properties.
        if (array_key_exists('properties', $schema)) {
            foreach ($schema['properties'] as $name => $property) {
                // Some properties are considered forbidden.
                if (str_starts_with($name, '_') || str_starts_with($name, '$') || str_starts_with($name, '@')) {
                    continue;
                }

                // Let see if the attribute exists.
                if (!$attribute = $this->getAttributeByName($name)) {
                    $attribute = new Attribute();
                    $attribute->setName($name);
                }
                $this->addAttribute($attribute->fromSchema($property));
            }
        }

        // Required stuff.
        if (array_key_exists('required', $schema)) {
            foreach ($schema['required'] as $required) {
                $attribute = $this->getAttributeByName($required);
                //We can only set the attribute on required if it exists so.
                if ($attribute instanceof Attribute === true) {
                    $attribute->setRequired(true);
                }
            }
        }

        // A bit of cleanup.
        foreach ($this->getAttributes() as $attribute) {
            // Remove Required if no longer valid.
            if (array_key_exists('required', $schema) && !in_array($attribute->getName(), $schema['required']) && $attribute->getRequired() == true) {
                $attribute->setRequired(false);
            }
            // Remove attribute if no longer present.
            if (!array_key_exists($attribute->getName(), $schema['properties'])) {
                $this->removeAttribute($attribute);
            }
        }

        return $this;
    }

    /**
     * Convert this Entity to a schema.
     *
     * @throws GatewayException
     *
     * @return array Schema array.
     */
    public function toSchema(?ObjectEntity $objectEntity = null): array
    {
        $schema = [
            '$id'               => $this->getReference(), //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'           => 'https://docs.commongateway.nl/schemas/Entity.schema.json',
            'title'             => $this->getName(),
            'description'       => $this->getDescription(),
            'version'           => $this->getVersion(),
            'exclude'           => $this->isExcluded(),
            'maxDepth'          => $this->getMaxDepth(),
            'nameProperties'    => $this->getNameProperties(),
            'createAuditTrails' => $this->getCreateAuditTrails(),
            'required'          => [],
            'properties'        => [],
        ];

        if ($objectEntity && $objectEntity->getEntity() !== $this) {
            throw new GatewayException('The given objectEntity has not have the same entity as this entity.');
        }

        // Set the schema type to an object.
        $schema['type'] = 'object';

        foreach ($this->getAttributes() as $attribute) {
            // Zetten van required.
            if ($attribute->getRequired()) {
                $schema['required'][] = $attribute->getName();
            }

            $property = [];

            // Aanmaken property.
            // @todo ik laad dit nu in als array maar eigenlijk wil je testen en alleen zetten als er waardes in zitten

            // Create an url to fetch the objects from the schema this property refers to.
            if ($attribute->getType() == 'object' && $attribute->getObject() !== null) {
                $property['_list'] = '/admin/objects?_self.schema.id='.$attribute->getObject()->getId()->toString();
            }

            if ($attribute->getType() === 'datetime' || $attribute->getType() === 'date') {
                $property['type'] = 'string';
                $property['format'] = $attribute->getType();
            } elseif ($attribute->getType()) {
                $property['type'] = $attribute->getType();
                $attribute->getFormat() && $property['format'] = $attribute->getFormat();
            }

            $stringReplace = str_replace('“', "'", $attribute->getDescription());
            $decodedDescription = str_replace('”', "'", $stringReplace);

            $attribute->getDescription() && $property['description'] = $decodedDescription;
            $attribute->getExample() && $property['example'] = $attribute->getExample();

            // What if we have an $object entity.
            if ($objectEntity instanceof ObjectEntity === true) {
                if ($attribute->getType() != 'object') {
                    $property['value'] = $objectEntity->getValue($attribute);
                } elseif ($attribute->getMultiple()) {
                    foreach ($objectEntity->getValueObject($attribute)->getObjects() as $object) {
                        $property['value'][] = $object->getId()->toString();
                    }
                } else {
                    $property['value'] = $objectEntity->getValueObject($attribute)->getStringValue();
                }
            }

            // What if the attribute is hooked to an object.
            if ($attribute->getType() === 'object' && $attribute->getObject() === true) {
                $property['$ref'] = '#/components/schemas/'.$attribute->getObject()->getName();
            }

            // Zetten van de property.
            $schema['properties'][$attribute->getName()] = $property;

            // Add the validators.
            foreach ($attribute->getValidations() as $validator => $validation) {
                if (!array_key_exists($validator, Entity::SUPPORTED_VALIDATORS) && $validation != null) {
                    if ($validator === 'required') {
                        continue;
                    }
                    $schema['properties'][$attribute->getName()][$validator] = $validation;
                }
            }
        }

        if (empty($schema['required']) === true) {
            unset($schema['required']);
        }

        return $schema;
    }

    public function getNameProperties(): ?array
    {
        return $this->nameProperties;
    }

    public function setNameProperties(?array $nameProperties): self
    {
        $this->nameProperties = $nameProperties;

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

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    public function setMaxDepth(int $maxDepth): self
    {
        $this->maxDepth = $maxDepth;

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
            $endpoint->addEntity($this);
        }

        return $this;
    }

    public function removeEndpoint(Endpoint $endpoint): self
    {
        if ($this->endpoints->removeElement($endpoint)) {
            $endpoint->removeEntity($this);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isExcluded(): bool
    {
        return $this->exclude;
    }

    /**
     * @param bool $exclude
     *
     * @return Entity
     */
    public function setExclude(bool $exclude): self
    {
        $this->exclude = $exclude;

        return $this;
    }

    /**
     * @return bool
     */
    public function getPersist(): bool
    {
        return $this->persist;
    }

    /**
     * @param bool $persist
     *
     * @return Entity
     */
    public function setPersist(bool $persist): self
    {
        $this->persist = $persist;

        return $this;
    }

    /**
     * @return bool
     */
    public function getCreateAuditTrails(): bool
    {
        return $this->createAuditTrails;
    }

    /**
     * @param bool $createAuditTrails
     *
     * @return Entity
     */
    public function setCreateAuditTrails(bool $createAuditTrails): self
    {
        $this->createAuditTrails = $createAuditTrails;

        return $this;
    }

    public function getDefaultSource(): ?Source
    {
        return $this->defaultSource;
    }

    public function setDefaultSource(?Source $defaultSource): self
    {
        $this->defaultSource = $defaultSource;

        return $this;
    }


    /**
     * @return Collection|Attribute[]
     */
    public function getSearchPartial(): Collection
    {
        return $this->searchPartial;
    }

    /**
     * @todo docs
     *
     * @param Attribute $attribute
     *
     * @throws Exception
     *
     * @return $this
     */
    public function addSearchPartial(Attribute $attribute): self
    {
        // Only allow adding to searchPartial if the attribute is part of this Entity.
        // Or if this entity has no attributes, when loading in fixtures.
        if (!$this->searchPartial->contains($attribute)
            && ($this->attributes->isEmpty() || $this->attributes->contains($attribute))
        ) {
            $this->searchPartial[] = $attribute;
            $attribute->setSearchPartial($this);
        } else {
            throw new Exception('You are not allowed to set searchPartial of an Entity to an Attribute that is not part of this Entity.');
        }

        return $this;
    }

    public function removeSearchPartial(Attribute $attribute): self
    {
        if ($this->searchPartial->removeElement($attribute)) {
            // set the owning side to null (unless already changed)
            if ($attribute->getSearchPartial() === $this) {
                $attribute->setSearchPartial(null);
            }
        }

        return $this;
    }

}
