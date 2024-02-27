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
use App\Repository\SecurityGroupRepository;
use App\Repository\ValueRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A value for a given attribute on an Object Entity.
 */
#[
    ApiResource(
        operations: [
            new Get(          "/admin/values/{id}"),
            new Put(          "/admin/values/{id}"),
            new Delete(       "/admin/values/{id}"),
            new GetCollection("/admin/values"),
            new Post(         "/admin/values")
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
    ORM\Entity(repositoryClass: ValueRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(
        SearchFilter::class,
        properties: [
            'attribute.id'    => 'exact',
            'objectEntity.id' => 'exact',
        ]
    ),
    ApiFilter(
        ExistsFilter::class,
        properties: [
            'stringValue',
            'dateTimeValue'
        ]
    ),
]
class Value
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
     * @var string|null An uri
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        Assert\Url,
        ORM\Column(
            type: 'string',
            length: 10,
            nullable: true
        )
    ]
    private ?string $uri = null;

    // TODO:indexeren
    /**
     * @var string|null The actual value if is of type string
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'text',
            nullable: true
        )
    ]
    private ?string $stringValue = null;

    /**
     * @var int|null Integer if the value is type integer
     */
    #[
        Groups(['read', 'write']),
        Assert\Type('integer'),
        ORM\Column(
            type: 'integer',
            nullable: true
        )
    ]
    private ?int $integerValue = null;

    /**
     * @var float|null Float if the value is type number
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'float',
            nullable: true
        )
    ]
    private ?float $numberValue = null;

    /**
     * @var bool|null Boolean if the value is type boolean
     */
    #[
        Groups(['read', 'write']),
        Assert\Type('bool'),
        ORM\Column(
            type: 'boolean',
            nullable: true
        )

    ]
    private ?bool $booleanValue = null;

    /**
     * @var array|null Array if the value is type multidimensional array
     */
    #[
        Groups(['read', 'write']),
        Assert\Type('array'),
        ORM\Column(
            type: 'array',
            nullable: true
        )
    ]
    private ?array $arrayValue = null;

    /**
     * @var array|null Array if the value is type single-dimensional array without key's e.g. a list
     */
    #[
        Groups(['read', 'write']),
        Assert\Type('array'),
        ORM\Column(
            type: 'simple_array',
            nullable: true
        )
    ]
    private ?array $simpleArrayValue = [];

    /**
     * @var DateTimeInterface|null DateTime if the value is type DateTime
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'datetime',
            nullable: true
        )
    ]
    private ?DateTimeInterface $dateTimeValue = null;

    /**
     * @var Collection The files of this value.
     */
    #[
        Groups(['read', 'write']),
        MaxDepth(1),
        ORM\OneToMany(
            mappedBy: 'value',
            targetEntity: File::class,
            cascade: ['persist', 'remove']
        )
    ]
    private Collection $files;

    /**
     * @var Attribute The attribute the value is an instance of.
     */
    #[
        Groups(['read', 'write']),
        MaxDepth(1),
        ORM\JoinColumn(nullable: false),
        ORM\ManyToOne(
            targetEntity: Attribute::class,
            inversedBy: 'attributeValues'
        )
    ]
    private Attribute $attribute;

    /**
     * @var ObjectEntity|null The object the value belongs to, otherwise known as the parent object.
     */
    #[
        Groups(['write']),
        MaxDepth(1),
        ORM\ManyToOne(
            targetEntity: ObjectEntity::class,
            cascade: ['persist'],
            fetch: 'EXTRA_LAZY',
            inversedBy: 'objectValues'
        )
    ]
    private ?ObjectEntity $objectEntity = null;

    /**
     * @var Collection The object the value refers to, otherwise known as the sub objects.
     */
    #[
        MaxDepth(1),
        ORM\ManyToMany(
            targetEntity: ObjectEntity::class,
            mappedBy: 'subresourceOf',
            cascade: ['persist'],
            fetch: 'EXTRA_LAZY'
        )
    ]
    private Collection $objects;

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
        return $this->getStringValue();
    }

    public function __construct(?Attribute $attribute = null, ?ObjectEntity $objectEntity = null)
    {
        $this->files = new ArrayCollection();
        $this->objects = new ArrayCollection();

        if ($attribute) {
            $this->setAttribute($attribute);
        }

        if ($objectEntity) {
            $this->setObjectEntity($objectEntity);
        }
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

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getStringValue(): ?string
    {
        return $this->stringValue;
    }

    public function setStringValue($stringValue): self
    {
        //@We no longer use string value?
        if (is_array($stringValue)) {
            return $this;
        } elseif ($stringValue instanceof ObjectEntity) {
            $stringValue = $stringValue->getId()->toString();
        }
        $this->stringValue = $stringValue;

        return $this;
    }

    public function getIntegerValue(): ?int
    {
        return $this->integerValue;
    }

    /**
     * Also stores the value in the stringValue in order to facilitate filtering on the string value. (see ObjectEntityRepository).
     *
     * @param int|null $integerValue
     *
     * @return $this
     */
    public function setIntegerValue(?int $integerValue): self
    {
        $this->integerValue = $integerValue;
        $this->stringValue = $integerValue !== null ? "$integerValue" : null;

        return $this;
    }

    public function getNumberValue(): ?float
    {
        return $this->numberValue;
    }

    /**
     * Also stores the value in the stringValue in order to facilitate filtering on the string value. (see ObjectEntityRepository).
     *
     * @param float|null $numberValue
     *
     * @return $this
     */
    public function setNumberValue(?float $numberValue): self
    {
        $this->numberValue = $numberValue;
        $this->stringValue = $numberValue !== null ? (string) $numberValue : null;

        return $this;
    }

    public function getBooleanValue(): ?bool
    {
        return $this->booleanValue;
    }

    /**
     * Also stores the value in the stringValue in order to facilitate filtering on the string value. (see ObjectEntityRepository).
     *
     * @param bool|null $booleanValue
     *
     * @return $this
     */
    public function setBooleanValue(?bool $booleanValue): self
    {
        $this->booleanValue = $booleanValue;
        //        $this->stringValue = $booleanValue !== null ? (string) $booleanValue : null; // results in a string of: "1" or "0"
        $this->stringValue = $booleanValue !== null ? ($booleanValue ? 'true' : 'false') : null;

        return $this;
    }

    public function getArrayValue(): ?array
    {
        if (!$this->arrayValue) {
            return [];
        }

        return $this->arrayValue;
    }

    public function setArrayValue(?array $arrayValue): self
    {
        $this->arrayValue = $arrayValue;

        return $this;
    }

    public function getSimpleArrayValue(): ?array
    {
        // Lets cast everything to the correct type
        $outputArray = [];
        foreach ($this->simpleArrayValue as $input) {
            // Switch for the correct type string", "integer", "boolean", "float", "number", "datetime", "date", "file", "object", "array"
            switch ($this->getAttribute()->getType()) {
                case 'string':
                    // if string
                    $outputArray[] = strval($input);
                    break;
                case 'integer':
                    // if integer
                    $outputArray[] = intval($input);
                    break;
                case 'boolean':
                    // if boolean
                    $outputArray[] = boolval($input);
                    break;
                case 'float':
                    // if float
                    $outputArray[] = floatval($input);
                    break;
                case 'number':
                    // if number
                    // todo: not sure if this is correct for type number
                    $outputArray[] = floatval($input);
                    break;
                case 'date':
                case 'datetime':
                    // if datetime or date
                    $format = $this->getAttribute()->getType() == 'date' ? 'Y-m-d' : 'Y-m-d\TH:i:sP';
                    $outputArray[] = new DateTime($format);
                    break;
                case 'file':
                    // if file
                    //@todo get file from uuid
                    break;
                case 'object':
                    // if object
                    //@todo get object from uuid
                    break;
                case 'array':
                    // if array
                    //@todo ???
                    break;
                default:
                    throw new \UnexpectedValueException('Could not parse to array the attribute type of: '.$this->getAttribute()->getType());
            }
        }

        return $outputArray;
    }

    public function setSimpleArrayValue(?array $inputArray): self
    {
        // Lets cast everything to string
        if ($inputArray) {
            $outputArray = [];
            foreach ($inputArray as $input) {
                // Lets catch files and objects
                $input = $this->simpleArraySwitch($input);

                try {
                    $outputArray[] = strval($input);
                } catch (Exception $exception) {
                    // todo: monolog?
                    // If we catch an array to string conversion here you are probably using windows
                    // and have to many schema files in for example zgw-bundle (delete some in your vendor map)
                }
            }

            $this->simpleArrayValue = $outputArray;

            $newStringValue = !empty($this->simpleArrayValue) ? implode(',', $this->simpleArrayValue) : null;
            $this->stringValue = is_string($newStringValue) ? ','.$newStringValue : null;
        }

        return $this;
    }

    private function simpleArraySwitch($input)
    {
        switch ($this->getAttribute()->getType()) {
            case 'file':
                // if file
                $this->addFile($input);

                return $input->getId();
            case 'object':
                // if object
                $this->addObject($input);

                return $input->getId();
        }

        return $input;
    }

    public function getDateTimeValue(): ?DateTimeInterface
    {
        return $this->dateTimeValue;
    }

    /**
     * Also stores the value in the stringValue in order to facilitate filtering on the string value. (see ObjectEntityRepository).
     *
     * @param DateTimeInterface|null $dateTimeValue
     *
     * @return $this
     */
    public function setDateTimeValue(?DateTimeInterface $dateTimeValue): self
    {
        $this->dateTimeValue = $dateTimeValue;
        $this->stringValue = $dateTimeValue !== null ? $dateTimeValue->format('Y-m-d H:i:s') : null;

        return $this;
    }

    /**
     * @return Collection|Value[]
     */
    public function getObjects(): ?Collection
    {
        return $this->objects;
    }

    public function addObject(ObjectEntity $object): self
    {
        // Make sure we can only add a child ObjectEntity if the Entity of that ObjectEntity has this Value->Attribute configured as a possible child Attribute.
        // TODO: This check should be here, but because we have some ZGW attributes that want to be connected to multiple entities, this will break stuff for now...
        // TODO: https://conduction.atlassian.net/browse/KISS-339
//        if ($this->attribute->getObject() !== $object->getEntity()) {
//            return $this;
//        }

        // let add this
        if (!$this->objects->contains($object)) {
            $this->objects->add($object);
        }

        // handle subresources
        if (!$object->getSubresourceOf()->contains($this)) {
            $object->addSubresourceOf($this);
        }

        // TODO: see https://conduction.atlassian.net/browse/CON-2030
        // todo: bij inversedby setten, validate ook de opgegeven value voor de inversedBy Attribute. hiermee kunnen we json logic naar boven checken.
        // Handle inversed by
        if ($this->getAttribute()->getInversedBy()) {
            $inversedByValue = $object->getValueObject($this->getAttribute()->getInversedBy());
            if ($this->getObjectEntity() !== null && $inversedByValue->getObjects()->contains($this->getObjectEntity()) === false) {
                // TODO: should it be possible for a value to not have an ObjectEntity connected? and if so how do we log an error for this?
                $inversedByValue->addObject($this->getObjectEntity());
            }
        }

        return $this;
    }

    public function removeObject(ObjectEntity $object): self
    {
        // handle subresources
        if ($object->getSubresourceOf()->contains($this)) {
            $object->getSubresourceOf()->removeElement($this);
        }
        // let remove this
        if ($this->objects->contains($object)) {
            $this->objects->removeElement($object);
        }

        // Remove inversed by
        if ($this->getAttribute()->getInversedBy()) {
            $inversedByValue = $object->getValueObject($this->getAttribute()->getInversedBy());
            if ($inversedByValue->getObjects()->contains($this->getObjectEntity())) {
                $inversedByValue->removeObject($this->getObjectEntity());
            }
        }

        return $this;
    }

    /**
     * Removes any values that where not hydrated on the current request.
     *
     * @param ObjectEntity $parent The parent object
     *
     * @return void
     */
    public function removeNonHydratedObjects(): void
    {
        // Savety
        if (!$this->getAttribute()->getMultiple() || $this->getAttribute()->getType() !== 'object') {
            return;
        }

        // Loop trough the objects
        foreach ($this->getObjects() as $object) {
            // Catch new objects

            // If the where not just hydrated remove them
            if ($object->getId() && !$object->getHydrated()) {
                $this->removeObject($object);
            }
        }
    }

    /**
     * @return Collection|File[]
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setValue($this);
        }

        return $this;
    }

    public function removeFile(File $file): self
    {
        if ($this->files->removeElement($file)) {
            // set the owning side to null (unless already changed)
            if ($file->getValue() === $this) {
                $file->setValue(null);
            }
        }

        return $this;
    }

    public function getAttribute(): ?Attribute
    {
        return $this->attribute ?? null;
    }

    /**
     * @throws Exception
     */
    public function setAttribute(?Attribute $attribute): self
    {
        if ($this->getObjectEntity() !== null && $this->getObjectEntity()->getEntity() !== $attribute->getEntity()) {
            return $this;
        }

        // If we have an attribute we can deal with default values
        $this->setDefaultValue();

        $this->attribute = $attribute;

        return $this;
    }

    public function getObjectEntity(): ?ObjectEntity
    {
        return $this->objectEntity;
    }

    public function setObjectEntity(?ObjectEntity $objectEntity): self
    {
        // Make sure we can only add a parent ObjectEntity if the Attribute of this Value has the ObjectEntity->Entity configured as a possible parent Entity.
        if ($objectEntity !== null && $this->getAttribute() !== null && $objectEntity->getEntity() !== $this->getAttribute()->getEntity()) {
            return $this;
        }

        $this->objectEntity = $objectEntity;

        return $this;
    }

    /**
     * @param mixed $value  The value to set
     * @param bool  $unsafe Whether the setter can also remove values
     *
     * @throws Exception
     */
    public function setValue($value, bool $unsafe = false, ?DateTimeInterface $dateModified = null): self
    {
        if ($this->getAttribute()) {
            // For files and objects it quicker to just return the collection (no mapping and aditional query's invollved)
            $doNotGetArrayTypes = ['object', 'file'];
            if ($this->getAttribute()->getMultiple() && !in_array($this->getAttribute()->getType(), $doNotGetArrayTypes)) {
                return $this->setSimpleArrayValue($value);
            } elseif ($this->getAttribute()->getMultiple()) {
                // Lest deal with multiple file subobjects
                if ($unsafe || $value === null || $value === []) {
                    // Make sure we unset inversedBy en subresourceOf, so no: $this->objects->clear();
                    foreach ($this->getObjects() as $object) {
                        $this->removeObject($object);
                    }
                    $this->stringValue = null;
                }

                if (!$value) {
                    return $this;
                }

                $valueArray = $value;
                $idArray = [];
                foreach ($valueArray as $value) {
                    // Catch Array input (for hydrator)
                    if (is_array($value)) {
                        $object = null;

                        // Make sure to not create new objects if we don't have to (_id in testdata)...
                        if (isset($value['_id'])) {
                            $objects = $this->objects->filter(function ($item) use ($value) {
                                return $item->getId() !== null && $item->getId()->toString() === $value['_id'];
                            });

                            if (count($objects) > 0) {
                                $object = $objects[0];
                            }
                        }
                        if ($object instanceof ObjectEntity === false) {
                            // failsafe to not create duplicate sub objects. In some weird cases $objects[0] doesn't return an ObjectEntity.
                            if (isset($objects) === true && count($objects) > 0) {
                                continue;
                            }
                            $object = new ObjectEntity($this->getAttribute()->getObject());
                        }

                        $object->setOwner($this->getObjectEntity()->getOwner());
                        $object->setApplication($this->getObjectEntity()->getApplication());
                        $object->setOrganization($this->getObjectEntity()->getOrganization());
                        $object->hydrate($value, $unsafe, $dateModified);
                        $value = $object;
                    }

                    if (is_string($value)) {
                        $idArray[] = $value;
                    } elseif (!$value) {
                        continue;
                    } elseif ($value instanceof ObjectEntity && $this->objects->contains($value) === false) {
                        $this->addObject($value);
                    }
                }

                // Set a string reprecentation of the object
                $this->stringValue = implode(',', $idArray);
                $this->setArrayValue($idArray);

                return $this;
            }

            switch ($this->getAttribute()->getType()) {
                case 'string':
                    return $this->setStringValue($value);
                case 'integer':
                    if ($value < PHP_INT_MAX) {
                        return $this->setIntegerValue((int) $value);
                    } else {
                        return $this;
                    }
                case 'boolean':

                    // This is used for defaultValue, this is always a string type instead of a boolean
                    if (is_string($value)) {
                        $value = $value === 'true';
                    }

                    return $this->setBooleanValue($value);
                case 'number':
                    return $this->setNumberValue((float) $value);
                case 'date':
                case 'datetime':
                    // if we auto convert null to a date time we would always default to current_timestamp, so lets tackle that
                    if (!$value) {
                        if ($this->getAttribute()->getMultiple()) {
                            return $this->setArrayValue(null);
                        }

                        return $this->setDateTimeValue(null);
                    }

                    if (is_array($value)) {
                        return $this->setDateTimeValue(null);
                    }

                    return $this->setDateTimeValue(new DateTime($value));
                case 'file':
                    if ($value === null) {
                        return $this;
                    }
                    $this->files->clear();
                    // Set a string reprecentation of the object
                    $this->stringValue = $value->getId();

                    return $this->addFile($value);
                case 'object':

                    // Catch empty input
                    if ($value === null) {
                        foreach ($this->getObjects() as $object) {
                            $this->removeObject($object);
                        }

                        return $this;
                    } elseif (is_string($value)) {
                        return $this->setStringValue($value);
                    }

                    // Catch Array input (for hydrator)

                    if (is_array($value) && $this->getAttribute()->getObject()) {
                        if (count($this->getObjects()) === 0) {
                            $object = new ObjectEntity($this->getAttribute()->getObject());
                            $object->setOwner($this->getObjectEntity()->getOwner());
                            $object->setApplication($this->getObjectEntity()->getApplication());
                            $object->setOrganization($this->getObjectEntity()->getOrganization());
                        } else {
                            $object = $this->getObjects()->first();
                        }

                        $object->hydrate($value, $unsafe, $dateModified);
                        $value = $object;
                    } elseif (is_array($value)) {
                        return $this;
                    }

                    // Make sure we unset inversedBy en subresourceOf, so no: $this->objects->clear();
                    foreach ($this->getObjects() as $object) {
                        $this->removeObject($object);
                    }

                    // Set a string reprecentation of the object
                    // var_dump('schema: '.$this->getObjectEntity()->getEntity()->getName());
                    $value->getId() && $this->stringValue = $value->getId()->toString();

                    return $this->addObject($value);

                case 'array':
                    return $this->setArrayValue($value);
                default:
                    throw new \UnexpectedValueException($this->getAttribute()->getEntity()->getName().$this->getAttribute()->getName().': Could not create a value for the attribute type of: '.$this->getAttribute()->getType());
            }
        } else {
            //TODO: correct error handling
            return false;
        }
    }

    public function getValue()
    {
        if ($this->getAttribute()) {
            // For files and objects it quicker to just return the collection (no mapping and aditional query's invollved)

            $doNotGetArrayTypes = ['object', 'file'];

            if ($this->getAttribute()->getMultiple() && !in_array($this->getAttribute()->getType(), $doNotGetArrayTypes)) {
                // Lets be backwards compatable
                if (!empty($this->getSimpleArrayValue())) {
                    return $this->getSimpleArrayValue();
                }
                // If simple array value is empty we want the normal array value
                return $this->getArrayValue();
            }

            switch ($this->getAttribute()->getType()) {
                case 'string':
                    return $this->getStringValue();
                case 'integer':
                    return $this->getIntegerValue();
                case 'boolean':
                    return $this->getBooleanValue();
                case 'number':
                    return $this->getNumberValue();
                case 'array':
                    return $this->getArrayValue();
                case 'date':
                case 'datetime':
                    $format = $this->getAttribute()->getType() == 'date' ? 'Y-m-d' : 'Y-m-d\TH:i:sP';

                    if ($this->getAttribute()->getFormat()) {
                        $format = $this->getAttribute()->getFormat();
                    }

                    // We don't want to format null
                    if ((!$this->getDateTimeValue() && !$this->getAttribute()->getMultiple())
                        || (!$this->getArrayValue() && $this->getAttribute()->getMultiple())
                    ) {
                        return null;
                    }

                    $datetime = $this->getDateTimeValue();

                    return $datetime->format($format);
                case 'file':
                    $files = $this->getFiles();
                    if (!$this->getAttribute()->getMultiple()) {
                        return $files->first();
                    }
                    if (count($files) == 0) {
                        return null;
                    }

                    return $files;
                case 'object':
                    // $objects can be a single object
                    $objects = $this->getObjects();
                    if (!$this->getAttribute()->getMultiple()) {
                        return $objects->first();
                    }
                    if (count($objects) == 0) {
                        return new ArrayCollection();
                    }

                    return $objects;
                default:
                    throw new \UnexpectedValueException('Could not return a value for the attribute type of: '.$this->getAttribute()->getType());
            }
        } else {
            //TODO: correct error handling
            return false;
        }
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
     * Set the default value for this object.
     *
     * @throws Exception
     *
     * @return $this
     */
    public function setDefaultValue(): self
    {
        if (!$this->getAttribute() || $this->getAttribute()->getDefaultValue()) {
            return $this;
        }

        // OKe lets grap the default value
        $defaultValue = $this->getAttribute()->getDefaultValue();

        // Lets double check if we are Expacting an array
        if ($this->getAttribute()->getMultiple()) {
            $defaultValue = explode(',', $defaultValue);
        }

        // And the we can set the result
        $this->setValue($defaultValue);

        return $this;
    }
}
