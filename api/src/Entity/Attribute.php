<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
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
 * An possible attribute on an Entity.
 *
 * @category Entity
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/attributes/{id}"},
 *      "put"={"path"="/admin/attributes/{id}"},
 *      "delete"={"path"="/admin/attributes/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/attributes"},
 *      "post"={"path"="/admin/attributes"}
 *  })
 * @ORM\Entity(repositoryClass="App\Repository\AttributeRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "entity.id": "exact",
 *     "object.id": "exact",
 *     "name": "exact"
 * })
 */
class Attribute
{
    /**
     * @var UuidInterface The UUID identifier of this object
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Groups({"read"})
     * @Assert\Uuid
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var string The name of the property as used in api calls
     *
     * @example my_property
     *
     * @Gedmo\Versioned
     * @Assert\Length(
     *     max = 255
     * )
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /* @todo slugify to key */

    /**
     * @var string The type of this property
     *
     * @example string
     *
     * @Assert\NotBlank
     * @Assert\Length(max = 255)
     * @Assert\Choice({"string", "integer", "boolean", "float", "number", "datetime", "date", "file", "object", "array"})
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255)
     */
    private $type = 'string';

    /**
     * @var string The swagger type of the property as used in api calls
     *
     * @example string
     *
     * @Assert\Length(max = 255)
     * @Assert\Choice({"countryCode","bsn","url","uri","uuid","email","phone","json","dutch_pc4"})
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $format;

    /**
     * @var bool True if this attribute expects an array of the given type.
     *
     * @example true
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean")
     */
    private bool $multiple = false;

    /**
     * The Entity this attribute is part of.
     *
     * @Groups({"write"})
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="attributes")
     * @MaxDepth(1)
     */
    private ?Entity $entity;

    /**
     * @var string The function of this Attribute. This is used for making specific attribute types/functions work differently.
     *             Note that the following options also expect the attribute to be readOnly: "self", "uri", "externalId", "dateCreated", "dateModified".
     *             And the type of this attribute must be string (or date/datetime for dateCreated/dateModified) or the function can not be set/changed!
     *
     * @example self
     *
     * @Assert\Choice({"noFunction", "id", "self", "uri", "externalId", "dateCreated", "dateModified", "userName"})
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", options={"default":"noFunction"}, name="function_column")
     */
    private string $function = 'noFunction';

    /**
     * Null, or the Entity this attribute is part of, if it is allowed to partial search on this attribute using the search query parameter.
     *
     * @Groups({"write"})
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="searchPartial")
     * @ORM\JoinColumn(nullable=true)
     * @MaxDepth(1)
     */
    private ?Entity $searchPartial = null;

    /**
     * @Groups({"write"})
     * @ORM\OneToMany(targetEntity=Value::class, mappedBy="attribute", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @MaxDepth(1)
     */
    private Collection $attributeValues;

    /**
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="usedIn", fetch="EAGER")
     * @ORM\JoinColumn(nullable=true)
     * @MaxDepth(1)
     */
    private ?Entity $object = null;

    /**
     * @var bool whether the properties of the original object are automatically include.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private bool $extend = false;

    /**
     * @var bool whether the properties of the object are always shown, even outside or instead of the embedded array.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private bool $include = false;

    /**
     * Used for schema or oas parsing.
     *
     * @Assert\Length(max = 255)
     */
    private $ref;

    /**
     * @var string *Can only be used in combination with type integer* Specifies a number where the value should be a multiple of, e.g. a multiple of 2 would validate 2,4 and 6 but would prevent 5
     *
     * @example 2
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $multipleOf;

    /**
     * @var string *Can only be used in combination with type integer* The maximum allowed value
     *
     * @example 2
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $maximum;

    /**
     * @var string *Can only be used in combination with type integer* Defines if the maximum is exclusive, e.g. a exclusive maximum of 5 would invalidate 5 but validate 4
     *
     * @example true
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $exclusiveMaximum;

    /**
     * @var string *Can only be used in combination with type integer* The minimum allowed value
     *
     * @example 2
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $minimum;

    /**
     * @var string *Can only be used in combination with type integer* Defines if the minimum is exclusive, e.g. a exclusive minimum of 5 would invalidate 5 but validate 6
     *
     * @example true
     *
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $exclusiveMinimum;

    /**
     * @var string The maximum amount of characters in the value
     *
     * @example 2
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $maxLength;

    /**
     * @var int The minimal amount of characters in the value
     *
     * @example 2
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $minLength;

    /**
     * @var ?int *Can only be used in combination with type array* The maximum array length
     *
     * @example 2
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $maxItems = null;

    /**
     * @var ?int *Can only be used in combination with type array* The minimum array length
     *
     * @example 2
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $minItems = null;

    /**
     * @var bool *Can only be used in combination with type array* Define whether or not values in an array should be unique
     *
     * @example false
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $uniqueItems = null;

    /**
     * @var string *Can only be used in combination with type object* The maximum amount of properties an object should contain
     *
     * @example 2
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $maxProperties = null;

    /**
     * @var int *Can only be used in combination with type object* The minimum amount of properties an object should contain
     *
     * @example 2
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $minProperties = null;

    /**
     * @var Attribute If the attribute targets an object that object might have an inversedBy field allowing a two-way connection
     *
     * @Groups({"read","write"})
     * @ORM\OneToOne(targetEntity=Attribute::class)
     * @MaxDepth(1)
     */
    private $inversedBy = null;

    /**
     * @var bool Only whether or not this property is required
     *
     * @example false
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $required = null;

    /**
     * @var array conditional requiremends for field
     *
     * @example false
     *
     * @Assert\Type("array")
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $requiredIf = [];

    /**
     * @var array conditional requiremends for field
     *
     * @example false
     *
     * @Assert\Type("array")
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $forbiddenIf = [];

    /**
     * @var array An array of possible values, input is limited to this array]
     *
     * @Assert\Type("array")
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $enum = [];

    /**
     * @var array *mutually exclusive with using type* An array of possible types that an property should confirm to
     *
     * @Assert\Type("array")
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $allOf = [];

    /**
     * @var array *mutually exclusive with using type* An array of possible types that an property might confirm to
     *
     * @Assert\Type("array")
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $anyOf = [];

    /**
     * @var array *mutually exclusive with using type* An array of possible types that an property must confirm to
     *
     * @Assert\Type("array")
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $oneOf = [];

    /**
     * @var string An description of the value asked, supports markdown syntax as described by [CommonMark 0.27.](https://spec.commonmark.org/0.27/)
     *
     * @example My value
     *
     * @Assert\Type("string")
     * @Groups({"read", "write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private $description = null;

    /**
     * @var string An default value for this value that will be used if a user doesn't supply a value
     *
     * @example My value
     *
     * @Assert\Length(max = 255)
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $defaultValue = null;

    /**
     * @var bool Whether or not this property can be left empty
     *
     * @example false
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true, options={"default":true})
     */
    private $nullable = true;

    /**
     * @var bool Whether or not this property must be unique
     *
     * @example false
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $mustBeUnique = null;

    /**
     * @var bool Whether or not the mustBeUnique check is case sensitive
     *
     * @example false
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true, options={"default":true})
     */
    private bool $caseSensitive = true;

    /**
     * @var bool Whether or not this property is read only
     *
     * @example false
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $readOnly = null;

    /**
     * @var bool Whether or not this property is write only
     *
     * @example false
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $writeOnly = null;

    /**
     * @var string An example of the value that should be supplied
     *
     * @example My value
     *
     * @Assert\Length(max = 255)
     * @Groups({"read", "write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private $example = null;

    /**
     * @var string Pattern which value should suffice to (Ecma-262 Edition 5.1 regular expression dialect)
     *
     * @example ^[1-9][0-9]{9}$
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $pattern = null;

    /**
     * @var bool Whether or not this property has been deprecated and wil be removed in the future
     *
     * @example false
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $deprecated = null;

    /**
     * @var string The minimal date for value, either a date, datetime or duration (ISO_8601)
     *
     * @example 2019-09-16T14:26:51+00:00
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $minDate = null;

    /**
     * @var string The maximum date for value, either a date, datetime or duration (ISO_8601)
     *
     * @example 2019-09-16T14:26:51+00:00
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $maxDate = null;

    /**
     * @var string *Can only be used in combination with type file* The maximum allowed file size in bytes
     *
     * @example 32000
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $maxFileSize = null;

    /**
     * @var string *Can only be used in combination with type file* The minimum allowed file size in bytes
     *
     * @example 32000
     *
     * @Assert\Type("integer")
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $minFileSize = null;

    /**
     * @var array *Can only be used in combination with type file* The type of the file
     *
     * @example image/png
     *
     * @Assert\Type("array")
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $fileTypes = null;

    /**
     * @Groups({"read", "write"})
     *
     * @Assert\Type("array")
     *
     * @var array This convieniance property alows us to get and set our validations as an array instead of loose objects
     */
    private $validations = [];

    /**
     * Setting this property to true wil force the property to be saved in the gateway endpoint (default behafure is saving in the EAV).
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $persistToGateway = false;

    /**
     * Whether or not this property is searchable.
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    private $searchable = false;

    /**
     * Whether or not this property is sortable. (orderBy).
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    private $sortable = false;

    /**
     * Only works if this attribute has type 'object'. When set to true, updating the object of this property will also trigger an Update event for the parent object.
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    private bool $triggerParentEvents = false;

    /**
     * Only works if this attribute has type 'object'. Whether or not the object of this property will be deleted if the parent object is deleted.
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private bool $cascadeDelete = false;

    /**
     * Only works if this attribute has type 'object'. Whether or not this property kan be used to create new entities (versus when it can only be used to link exsisting entities).
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true, name="allow_cascade")
     */
    private $cascade = false;

    /**
     * @var array Config for getting the object result info from the correct places (id is required!). "envelope" for where to find this item and "id" for where to find the id. (both from the root! So if id is in the envelope example: envelope = instance, id = instance.id)
     *
     * @Assert\Type("array")
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $objectConfig = ['id' => 'id'];

    /**
     * Setting this property to true makes it so that this property is not allowed to be changed after creation.
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private bool $immutable = false;

    /**
     * Setting this property to true makes it so that this property is only allowed to be set or changed after creation.
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private bool $unsetable = false;

    /**
     * Whether or not this property can be orphaned. If mayBeOrphaned = false, the parent object can not be deleted if this property still has an object.
     *
     * @Assert\Type("bool")
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private bool $mayBeOrphaned = true;

    /**
     * @var ?string The uri to a schema.org property
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true, options={"default":null}, name="schema_column")
     */
    private ?string $schema = null;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated = null;

    /**
     * @var Datetime The moment this resource was last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified = null;

    /**
     * @todo
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $reference = null;

    public function __construct()
    {
        $this->attributeValues = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getName();
    }

    /**
     * JSON schema to attribute.
     *
     * @throws GatewayException
     */
    public function fromSchema(array $property): Attribute
    {
        // Handle the property setup
        if (array_key_exists('type', $property)) {
            $this->setType($property['type']);
        }
        if (array_key_exists('format', $property)) {
            $this->setFormat($property['format']);
        }
        if (array_key_exists('example', $property)) {
            $this->setExample($property['example']);
        }
        if (array_key_exists('readOnly', $property)) {
            $this->setReadOnly($property['readOnly']);
        }
        if (array_key_exists('description', $property)) {
            $this->setDescription($property['description']);
        }
        if (array_key_exists('$ref', $property)) {
            $this->setSchema($property['$ref']);
        }
        if (array_key_exists('items', $property) && array_key_exists('$ref', $property['items'])) {
            $this->setSchema($property['items']['$ref']);
            $this->setMultiple(true);
        }
        if (array_key_exists('maxLength', $property)) {
            $this->setMaxLength($property['maxLength']);
        }
        if (array_key_exists('enum', $property)) {
            $this->setEnum($property['enum']);
        }
        if (array_key_exists('default', $property)) {
            $this->setDefaultValue($property['default']);
        }

        $this->setDateModified(new DateTime());

        return $this;
    }

    public function export()
    {
        if ($this->getEntity() !== null) {
            $entity = $this->getEntity()->getId()->toString();
            $entity = '@'.$entity;
        } else {
            $entity = null;
        }

        if ($this->getObject() !== null) {
            $object = $this->getObject()->getId()->toString();
            $object = '@'.$object;
        } else {
            $object = null;
        }

        if ($this->getInversedBy() !== null) {
            $inversed = $this->getInversedBy()->getId()->toString();
            $inversed = '@'.$inversed;
        } else {
            $inversed = null;
        }

        $data = [
            'name'             => $this->getName(),
            'type'             => $this->getType(),
            'format'           => $this->getFormat(),
            'multiple'         => $this->getMultiple(),
            'entity'           => $entity,
            'object'           => $object,
            'multipleOf'       => $this->getMultipleOf(),
            'maximum'          => $this->getMaximum(),
            'exclusiveMaximum' => $this->getExclusiveMaximum(),
            'minimum'          => $this->getMinimum(),
            'exclusiveMinimum' => $this->getExclusiveMaximum(),
            'maxLength'        => $this->getMaxLength(),
            'minLength'        => $this->getMinLength(),
            'maxItems'         => $this->getMaxItems(),
            'minItems'         => $this->getMinItems(),
            'uniqueItems'      => $this->getUniqueItems(),
            'maxProperties'    => $this->getMaxProperties(),
            'minProperties'    => $this->getMinProperties(),
            'inversedBy'       => $inversed,
            'required'         => $this->getRequired(),
            'requiredIf'       => $this->getRequiredIf(),
            'forbiddenIf'      => $this->getForbiddenIf(),
            'enum'             => $this->getEnum(),
            'allOf'            => $this->getAllOf(),
            'anyOf'            => $this->getAnyOf(),
            'oneOf'            => $this->getOneOf(),
            'description'      => $this->getDescription(),
            'defaultValue'     => $this->getDefaultValue(),
            'nullable'         => $this->getNullable(),
            'mustBeUnique'     => $this->getMustBeUnique(),
            'readOnly'         => $this->getReadOnly(),
            'writeOnly'        => $this->getWriteOnly(),
            'example'          => $this->getExample(),
            'deprecated'       => $this->getDeprecated(),
            'minDate'          => $this->getMinDate(),
            'maxDate'          => $this->getMaxDate(),
            'maxFileSize'      => $this->getMaxFileSize(),
            'fileTypes'        => $this->getFileTypes(),
            'persistToGateway' => $this->getPersistToGateway(),
            'searchable'       => $this->getSearchable(),
            'cascade'          => $this->getCascade(),
        ];

        return array_filter($data, fn ($value) => !is_null($value) && $value !== '' && $value !== []);
    }

    public function getId()
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

    public function getMultiple(): ?bool
    {
        return $this->multiple;
    }

    public function setMultiple(?bool $multiple): self
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function getEntity(): ?Entity
    {
        return $this->entity;
    }

    public function setEntity(?Entity $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getFunction(): ?string
    {
        return $this->function;
    }

    /**
     * @todo docs
     *
     * @param string|null $function
     *
     * @throws Exception
     *
     * @return $this
     */
    public function setFunction(?string $function): self
    {
        if (in_array($function, ['id', 'self', 'uri', 'externalId', 'dateCreated', 'dateModified', 'userName'])) {
            if ($this->getType() !== 'string' && (!str_contains($function, 'date') || !str_contains($this->getType(), 'date'))) {
                throw new Exception('This function expects this attribute to have a different type! string or date/datetime, depending on the function (not: '.$this->getType().')');
                // todo: or just always set the type to the correct one?
            }
            // These functions require this attribute to be readOnly!
            $this->setReadOnly(true);
        }
        $this->function = $function;

        return $this;
    }

    public function getSearchPartial(): ?Entity
    {
        return $this->searchPartial;
    }

    /**
     * @todo docs
     *
     * @param Entity|null $searchPartial
     *
     * @throws Exception
     *
     * @return $this
     */
    public function setSearchPartial(?Entity $searchPartial): self
    {
        // Only allow null or the entity of this Attribute when setting searchPartial.
        // Allow setting searchPartial when entity is not set, because of loading in fixtures.
        if ($searchPartial === null || !isset($this->entity) || $searchPartial === $this->entity) {
            $this->searchPartial = $searchPartial;
        } else {
            throw new Exception('You are not allowed to set searchPartial of an Attribute to any other Entity than the Entity of the Attribute.');
        }

        return $this;
    }

    /**
     * @return Collection|Value[]
     */
    public function getAttributeValues(): Collection
    {
        return $this->attributeValues;
    }

    public function addAttributeValue(Value $attributeValue): self
    {
        if (!$this->attributeValues->contains($attributeValue)) {
            $this->attributeValues[] = $attributeValue;
            $attributeValue->setAttribute($this);
        }

        return $this;
    }

    public function removeAttributeValue(Value $attributeValue): self
    {
        if ($this->attributeValues->removeElement($attributeValue)) {
            // set the owning side to null (unless already changed)
            if ($attributeValue->getAttribute() === $this) {
                $attributeValue->setAttribute(null);
            }
        }

        return $this;
    }

    public function getObject(): ?Entity
    {
        return $this->object;
    }

    public function setObject(?Entity $object): self
    {
        if (!$object) {
            return $this;
        }
        $this->type = 'object';
        $this->object = $object;

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

    public function getInclude(): ?bool
    {
        return $this->include;
    }

    public function setInclude(?bool $include): self
    {
        $this->include = $include;

        return $this;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function setRef(string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getMultipleOf(): ?int
    {
        return $this->multipleOf;
    }

    public function setMultipleOf(?int $multipleOf): self
    {
        $this->multipleOf = $multipleOf;

        return $this;
    }

    public function getMaximum(): ?int
    {
        return $this->maximum;
    }

    public function setMaximum(?int $maximum): self
    {
        $this->maximum = $maximum;

        return $this;
    }

    public function getExclusiveMaximum(): ?bool
    {
        return $this->exclusiveMaximum;
    }

    public function setExclusiveMaximum(?bool $exclusiveMaximum): self
    {
        $this->exclusiveMaximum = $exclusiveMaximum;

        return $this;
    }

    public function getMinimum(): ?int
    {
        return $this->minimum;
    }

    public function setMinimum(?int $minimum): self
    {
        $this->minimum = $minimum;

        return $this;
    }

    public function getExclusiveMinimum(): ?bool
    {
        return $this->exclusiveMinimum;
    }

    public function setExclusiveMinimum(?bool $exclusiveMinimum): self
    {
        $this->exclusiveMinimum = $exclusiveMinimum;

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function setMaxLength(?int $maxLength): self
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function setMinLength(?int $minLength): self
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function setMaxItems(?int $maxItems): self
    {
        $this->maxItems = $maxItems;

        return $this;
    }

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function setMinItems(?int $minItems): self
    {
        $this->minItems = $minItems;

        return $this;
    }

    public function getUniqueItems(): ?bool
    {
        return $this->uniqueItems;
    }

    public function setUniqueItems(?bool $uniqueItems): self
    {
        $this->uniqueItems = $uniqueItems;

        return $this;
    }

    public function getMaxProperties(): ?int
    {
        return $this->maxProperties;
    }

    public function setMaxProperties(?int $maxProperties): self
    {
        $this->maxProperties = $maxProperties;

        return $this;
    }

    public function getMinProperties(): ?int
    {
        return $this->minProperties;
    }

    public function setMinProperties(?int $minProperties): self
    {
        $this->minProperties = $minProperties;

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

    public function getRequiredIf(): ?array
    {
        return $this->requiredIf;
    }

    public function setRequiredIf(?array $requiredIf): self
    {
        $this->requiredIf = $requiredIf;

        return $this;
    }

    public function getForbiddenIf(): ?array
    {
        return $this->forbiddenIf;
    }

    public function setForbiddenIf(?array $forbiddenIf): self
    {
        $this->forbiddenIf = $forbiddenIf;

        return $this;
    }

    public function getEnum(): ?array
    {
        return $this->enum;
    }

    public function setEnum(?array $enum): self
    {
        $this->enum = $enum;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        // If the attribute type is changes away from an object we need to drop the object
        if ($type != 'object' and $this->object) {
            $this->object = null;
        }

        return $this;
    }

    public function getAllOf(): ?array
    {
        return $this->allOf;
    }

    public function setAllOf(?array $allOf): self
    {
        $this->allOf = $allOf;

        return $this;
    }

    public function getAnyOf(): ?array
    {
        return $this->anyOf;
    }

    public function setAnyOf(?array $anyOf): self
    {
        $this->anyOf = $anyOf;

        return $this;
    }

    public function getOneOf(): ?array
    {
        return $this->oneOf;
    }

    public function setOneOf(?array $oneOf): self
    {
        $this->oneOf = $oneOf;

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

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): self
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(?string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function getNullable(): ?bool
    {
        return $this->nullable;
    }

    public function setNullable(bool $nullable): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function getMustBeUnique(): ?bool
    {
        return $this->mustBeUnique;
    }

    public function setMustBeUnique(?bool $mustBeUnique): self
    {
        $this->mustBeUnique = $mustBeUnique;

        return $this;
    }

    public function getCaseSensitive(): ?bool
    {
        return $this->caseSensitive;
    }

    public function setCaseSensitive(bool $caseSensitive): self
    {
        $this->caseSensitive = $caseSensitive;

        return $this;
    }

    public function getReadOnly(): ?bool
    {
        return $this->readOnly;
    }

    public function setReadOnly(?bool $readOnly): self
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    public function getWriteOnly(): ?bool
    {
        return $this->writeOnly;
    }

    public function setWriteOnly(?bool $writeOnly): self
    {
        $this->writeOnly = $writeOnly;

        return $this;
    }

    public function getExample(): ?string
    {
        return $this->example;
    }

    public function setExample(?string $example): self
    {
        $this->example = $example;

        return $this;
    }

    public function getDeprecated(): ?bool
    {
        return $this->deprecated;
    }

    public function setDeprecated(?bool $deprecated): self
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    public function getMinDate(): ?string
    {
        return $this->minDate;
    }

    public function setMinDate(?string $minDate): self
    {
        $this->minDate = $minDate;

        return $this;
    }

    public function getMaxDate(): ?string
    {
        return $this->maxDate;
    }

    public function setMaxDate(?string $maxDate): self
    {
        $this->maxDate = $maxDate;

        return $this;
    }

    public function getMaxFileSize(): ?int
    {
        return $this->maxFileSize;
    }

    public function setMaxFileSize(?int $maxFileSize): self
    {
        $this->maxFileSize = $maxFileSize;

        return $this;
    }

    public function getMinFileSize(): ?int
    {
        return $this->minFileSize;
    }

    public function setMinFileSize(?int $minFileSize): self
    {
        $this->minFileSize = $minFileSize;

        return $this;
    }

    public function getFileTypes(): ?array
    {
        return $this->fileTypes;
    }

    public function setFileTypes(?array $fileTypes): self
    {
        $this->fileTypes = $fileTypes;

        return $this;
    }

    public function getValidations(): ?array
    {
        //TODO: this list of validations is not complete!
        $validations = [];
        (isset($this->multipleOf) ? $validations['multipleOf'] = $this->getMultipleOf() : '');
        (isset($this->pattern) ? $validations['pattern'] = $this->getPattern() : '');
        (isset($this->maximum) ? $validations['maximum'] = $this->getMaximum() : '');
        (isset($this->exclusiveMaximum) ? $validations['exclusiveMaximum'] = $this->getExclusiveMaximum() : '');
        (isset($this->minimum) ? $validations['minimum'] = $this->getMinimum() : '');
        (isset($this->exclusiveMinimum) ? $validations['exclusiveMinimum'] = $this->getExclusiveMinimum() : '');
        (isset($this->maxLength) ? $validations['maxLength'] = $this->getMaxLength() : '');
        (isset($this->minLength) ? $validations['minLength'] = $this->getMinLength() : '');
        (isset($this->maxItems) ? $validations['maxItems'] = $this->getMaxItems() : '');
        (isset($this->minItems) ? $validations['minItems'] = $this->getMinItems() : '');
        (isset($this->uniqueItems) ? $validations['uniqueItems'] = $this->getUniqueItems() : '');
        (isset($this->maxProperties) ? $validations['maxProperties'] = $this->getMaxProperties() : '');
        (isset($this->minProperties) ? $validations['minProperties'] = $this->getMinProperties() : '');
        (isset($this->required) ? $validations['required'] = $this->getRequired() : '');
        (isset($this->requiredIf) ? $validations['requiredIf'] = $this->getRequiredIf() : '');
        (isset($this->forbiddenIf) ? $validations['forbiddenIf'] = $this->getForbiddenIf() : '');
        (isset($this->enum) ? $validations['enum'] = $this->getEnum() : '');
        (isset($this->allOf) ? $validations['allOf'] = $this->getAllOf() : ''); //todo: validation/BL toevoegen
        (isset($this->anyOf) ? $validations['anyOf'] = $this->getAnyOf() : ''); //todo: validation/BL toevoegen
        (isset($this->oneOf) ? $validations['oneOf'] = $this->getOneOf() : ''); //todo: validation/BL toevoegen
        (isset($this->defaultValue) ? $validations['defaultValue'] = $this->getDefaultValue() : ''); //todo: validation/BL toevoegen
        (isset($this->nullable) ? $validations['nullable'] = $this->getNullable() : '');
        (isset($this->mustBeUnique) ? $validations['mustBeUnique'] = $this->getMustBeUnique() : ''); //todo: validation/BL toevoegen
        (isset($this->maxDate) ? $validations['maxDate'] = $this->getMaxDate() : '');
        (isset($this->minDate) ? $validations['minDate'] = $this->getMinDate() : '');
        (isset($this->multiple) ? $validations['multiple'] = $this->getMultiple() : '');
        (isset($this->maxFileSize) ? $validations['maxFileSize'] = $this->getMaxFileSize() : '');
        (isset($this->minFileSize) ? $validations['minFileSize'] = $this->getMinFileSize() : '');
        (isset($this->fileTypes) ? $validations['fileTypes'] = $this->getFileTypes() : '');
        (isset($this->cascade) ? $validations['cascade'] = $this->getCascade() : '');
        (isset($this->imutable) ? $validations['immutable'] = $this->getImmutable() : '');
        (isset($this->unsetable) ? $validations['unsetable'] = $this->getUnsetable() : '');
        (isset($this->readOnly) ? $validations['readOnly'] = $this->getReadOnly() : '');

        return $validations;
    }

    public function setValidations(?array $validations): self
    {
        //TODO: this list of validations is not complete!
        if (array_key_exists('multipleOf', $validations)) {
            $this->setMultipleOf($validations['multipleOf']);
        }
        if (array_key_exists('maximum', $validations)) {
            $this->setMaximum($validations['maximum']);
        }
        if (array_key_exists('exclusiveMaximum', $validations)) {
            $this->setExclusiveMaximum($validations['exclusiveMaximum']);
        }
        if (array_key_exists('minimum', $validations)) {
            $this->setMinimum($validations['minimum']);
        }
        if (array_key_exists('exclusiveMinimum', $validations)) {
            $this->setExclusiveMinimum($validations['exclusiveMinimum']);
        }
        if (array_key_exists('maxLength', $validations)) {
            $this->setMaxLength($validations['maxLength']);
        }
        if (array_key_exists('minLength', $validations)) {
            $this->setMinLength($validations['minLength']);
        }
        if (array_key_exists('maxItems', $validations)) {
            $this->setMaxItems($validations['maxItems']);
        }
        if (array_key_exists('minItems', $validations)) {
            $this->setMinItems($validations['minItems']);
        }
        if (array_key_exists('uniqueItems', $validations)) {
            $this->setUniqueItems($validations['uniqueItems']);
        }
        if (array_key_exists('maxProperties', $validations)) {
            $this->setMaxProperties($validations['maxProperties']);
        }
        if (array_key_exists('minProperties', $validations)) {
            $this->setMinProperties($validations['minProperties']);
        }
        if (array_key_exists('required', $validations)) {
            $this->setRequired($validations['required']);
        }
        if (array_key_exists('requiredIf', $validations)) {
            $this->setRequiredIf($validations['requiredIf']);
        }
        if (array_key_exists('forbiddenIf', $validations)) {
            $this->setForbiddenIf($validations['forbiddenIf']);
        }
        if (array_key_exists('enum', $validations)) {
            $this->setEnum($validations['enum']);
        }
        if (array_key_exists('allOf', $validations)) {
            $this->setAllOf($validations['allOf']);
        }
        if (array_key_exists('anyOf', $validations)) {
            $this->setAnyOf($validations['anyOf']);
        }
        if (array_key_exists('oneOf', $validations)) {
            $this->setOneOf($validations['oneOf']);
        }
        if (array_key_exists('defaultValue', $validations)) {
            $this->setDefaultValue($validations['defaultValue']);
        }
        if (array_key_exists('nullable', $validations)) {
            $this->setNullable($validations['nullable']);
        }
        if (array_key_exists('mustBeUnique', $validations)) {
            $this->setMustBeUnique($validations['mustBeUnique']);
        }
        if (array_key_exists('maxDate', $validations)) {
            $this->setMaxDate($validations['maxDate']);
        }
        if (array_key_exists('minDate', $validations)) {
            $this->setMinDate($validations['minDate']);
        }
        if (array_key_exists('multiple', $validations)) {
            $this->setMultiple($validations['multiple']);
        }
        if (array_key_exists('cascade', $validations)) {
            $this->setCascade($validations['cascade']);
        }
        if (array_key_exists('immutable', $validations)) {
            $this->setImmutable($validations['immutable']);
        }
        if (array_key_exists('unsetable', $validations)) {
            $this->setUnsetable($validations['unsetable']);
        }
        if (array_key_exists('readOnly', $validations)) {
            $this->setReadOnly($validations['readOnly']);
        }

        return $this;
    }

    public function getPersistToGateway(): ?bool
    {
        return $this->persistToGateway;
    }

    public function setPersistToGateway(?bool $persistToGateway): self
    {
        $this->persistToGateway = $persistToGateway;

        return $this;
    }

    public function getSearchable(): ?bool
    {
        return $this->searchable;
    }

    public function setSearchable(?bool $searchable): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function getSortable(): ?bool
    {
        return $this->sortable;
    }

    public function setSortable(?bool $sortable): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function getCascade(): ?bool
    {
        return $this->cascade;
    }

    public function setCascade(?bool $cascade): self
    {
        $this->cascade = $cascade;

        return $this;
    }

    public function getInversedBy(): ?Attribute
    {
        return $this->inversedBy;
    }

    public function setInversedBy(?Attribute $inversedBy): self
    {
        $this->inversedBy = $inversedBy;

        return $this;
    }

    public function getObjectConfig(): ?array
    {
        return $this->objectConfig;
    }

    public function setObjectConfig(?array $objectConfig): self
    {
        $this->objectConfig = $objectConfig;

        return $this;
    }

    public function getTriggerParentEvents(): ?bool
    {
        return $this->triggerParentEvents;
    }

    public function setTriggerParentEvents(?bool $triggerParentEvents): self
    {
        $this->triggerParentEvents = $triggerParentEvents;

        return $this;
    }

    public function getCascadeDelete(): ?bool
    {
        return $this->cascadeDelete;
    }

    public function setCascadeDelete(?bool $cascadeDelete): self
    {
        $this->cascadeDelete = $cascadeDelete;

        return $this;
    }

    public function getImmutable(): ?bool
    {
        return $this->immutable;
    }

    public function setImmutable(?bool $immutable): self
    {
        $this->immutable = $immutable;

        return $this;
    }

    public function getUnsetable(): ?bool
    {
        return $this->unsetable;
    }

    public function setUnsetable(?bool $unsetable): self
    {
        $this->unsetable = $unsetable;

        return $this;
    }

    public function getMayBeOrphaned(): ?bool
    {
        return $this->mayBeOrphaned;
    }

    public function setMayBeOrphaned(?bool $mayBeOrphaned): self
    {
        $this->mayBeOrphaned = $mayBeOrphaned;

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

    public function getSchema(): ?string
    {
        return $this->schema;
    }

    public function setSchema(?string $schema): self
    {
        $this->schema = $schema;

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
