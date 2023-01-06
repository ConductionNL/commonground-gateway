<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A value for a given attribute on an Object Entity.
 *
 * @category Entity
 * @ORM\HasLifecycleCallbacks()
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/values/{id}"},
 *      "put"={"path"="/admin/values/{id}"},
 *      "delete"={"path"="/admin/values/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/values"},
 *      "post"={"path"="/admin/values"}
 *  })
 * @ORM\Entity(repositoryClass="App\Repository\ValueRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "attribute.id": "exact",
 *     "objectEntity.id": "exact"
 * })
 * @ApiFilter(ExistsFilter::class, properties={
 *     "stringValue",
 *     "dateTimeValue",
 * })
 */
class Value
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     * @Groups({"read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    // TODO:indexeren
    /**
     * @var string An uri
     *
     * @Assert\Url
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uri;

    // TODO:indexeren
    /**
     * @var string The actual value if is of type string
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private $stringValue; //TODO make this type=string again!?

    /**
     * @var int Integer if the value is type integer
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $integerValue;

    /**
     * @var float Float if the value is type number
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="float", nullable=true)
     */
    private $numberValue;

    /**
     * @var bool Boolean if the value is type boolean
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $booleanValue;

    /**
     * @var array Array if the value is type multidemensional array
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $arrayValue;

    /**
     * @var array Array if the value is type singledimensional array without key's e.g. a list
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="simple_array", nullable=true)
     */
    private $simpleArrayValue = [];

    /**
     * @var DateTime DateTime if the value is type DateTime
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateTimeValue;

    /**
     * @Groups({"read","write"})
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity=File::class, mappedBy="value", cascade={"persist", "remove"})
     */
    private $files;

    /**
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Attribute::class, inversedBy="attributeValues")
     * @ORM\JoinColumn(nullable=false)
     * @MaxDepth(1)
     */
    private Attribute $attribute;

    /**
     * @Groups({"write"})
     * @ORM\ManyToOne(targetEntity=ObjectEntity::class, inversedBy="objectValues", fetch="EXTRA_LAZY", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     * @MaxDepth(1)
     */
    private $objectEntity; // parent object

    /**
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=ObjectEntity::class, mappedBy="subresourceOf", fetch="LAZY", cascade={"persist"})
     */
    private $objects; // sub objects

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

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

    public function setId(UuidInterface $id): self
    {
        $this->id = $id;

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

                $outputArray[] = strval($input);
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
            if (!$inversedByValue->getObjects()->contains($this->getObjectEntity())) {
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
        return isset($this->attribute) ? $this->attribute : null;
    }

    public function setAttribute(?Attribute $attribute): self
    {

        // If we have an atribute we can deal with default values
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
        $this->objectEntity = $objectEntity;

        return $this;
    }

    /**
     * @param $value The value to set
     * @param bool $unsafe Wheter the setter can also remove values
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
                if ($unsafe) {
                    $this->objects->clear();
                }

                if (!$value) {
                    return $this;
                }

                $valueArray = $value;
                $idArray = [];
                foreach ($valueArray as $value) {

                    // Catch Array input (for hydrator)
                    if (is_array($value)) {
                        $valueObject = new ObjectEntity($this->getAttribute()->getObject());
                        $valueObject->setOwner($this->getObjectEntity()->getOwner());
                        $valueObject->setApplication($this->getObjectEntity()->getApplication());
                        $valueObject->setOrganization($this->getObjectEntity()->getOrganization());
                        $valueObject->hydrate($value, $unsafe, $dateModified);
                        $value = $valueObject;
                    }

                    if (is_string($value)) {
                        $idArray[] = $value;
                    } elseif (!$value) {
                        continue;
                    } elseif ($value instanceof ObjectEntity) {
                        $this->addObject($value);
                    }
                }

                // Set a string reprecentation of the object
                $this->stringValue = ','.implode(',', $idArray);
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
                        return $this;
                    } elseif (is_string($value)) {
                        return $this->setStringValue($value);
                    }

                    // Catch Array input (for hydrator)
                    if (is_array($value) && $this->getAttribute()->getObject()) {
                        $valueObject = new ObjectEntity($this->getAttribute()->getObject());
                        $valueObject->setOwner($this->getObjectEntity()->getOwner());
                        $valueObject->setApplication($this->getObjectEntity()->getApplication());
                        $valueObject->setOrganization($this->getObjectEntity()->getOrganization());
                        $valueObject->hydrate($value, $unsafe, $dateModified);
                        $value = $valueObject;
                    } elseif (is_array($value)) {
                        return $this;
                    }

                    $this->objects->clear();

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
    }
}
