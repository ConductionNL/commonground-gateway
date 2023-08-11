<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

use function Symfony\Component\Translation\t;

/**
 * An (data) object that resides within the datalayer of the gateway.
 *
 * @category Entity
 *
 * @ORM\Entity(repositoryClass="App\Repository\ObjectEntityRepository")
 *
 * @ORM\HasLifecycleCallbacks
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 */
class ObjectEntity
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     *
     * @Groups({"read"})
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
     * @var ?string The name of this Object (configured from a attribute)
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $name = null;

    /**
     * @var string The {at sign} id or self->href of this Object.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $self;

    /**
     * @var string UUID of the external object of this ObjectEntity
     *
     * @Assert\Uuid
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $externalId;

    /**
     * @var string An uri (or url identifier) for this object
     *
     * @Assert\Url
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uri;

    /**
     * The application that this object belongs to.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\ManyToOne(targetEntity=Application::class, inversedBy="objectEntities")
     *
     * @MaxDepth(1)
     */
    private ?Application $application = null;

    /**
     * @var string An uuid or uri of an organization
     *
     * @Groups({"read", "write"})
     *
     * @ORM\ManyToOne(targetEntity=Organization::class, inversedBy="objectEntities")
     */
    private ?Organization $organization = null;

    /**
     * @var string An uuid or uri of an owner of this object
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $owner;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="objectEntities", fetch="EAGER")
     *
     * @MaxDepth(1)
     */
    private ?Entity $entity = null;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\OneToMany(targetEntity=Value::class, mappedBy="objectEntity", cascade={"persist","remove"}, orphanRemoval=true)
     *
     * @MaxDepth(1)
     */
    private $objectValues;

    /**
     * @Groups({"read"})
     */
    private bool $hasErrors = false;

    /**
     * @Groups({"read", "write"})
     */
    private ?array $errors = [];

    /**
     * @Groups({"read"})
     */
    private bool $hasPromises = false;

    /**
     * @Groups({"read", "write"})
     */
    private ?array $promises = [];

    /**
     * @Groups({"read", "write"})
     */
    private ?array $externalResult = [];

    /**
     * @Groups({"read"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=Value::class, inversedBy="objects", cascade={"persist"}, fetch="EXTRA_LAZY")
     */
    private $subresourceOf;

    /**
     * If this is a subresource part of a list of subresources of another ObjectEntity this represents the index of this ObjectEntity in that list.
     * Used for showing correct index in error messages.
     *
     * @var string|null
     *
     * @Groups({"read", "write"})
     */
    private ?string $subresourceIndex = null;

    /**
     * @MaxDepth(1)
     *
     * @ORM\OneToMany(targetEntity=Synchronization::class, mappedBy="object", fetch="EXTRA_LAZY", cascade={"remove"})
     */
    private Collection $synchronizations;

    /**
     * @MaxDepth(1)
     *
     * @ORM\OneToMany(targetEntity=Attribute::class, mappedBy="object", fetch="EXTRA_LAZY", cascade={"remove","persist"})
     */
    private Collection $usedIn;

    /**
     * Used to check if the object has been hydrated.
     *
     * @var bool
     */
    private bool $hydrated = false;

    /**
     * Filled when the object entity is locked.
     *
     * @Groups({"read", "write"})
     *
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $lock = null;

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
     * @ORM\OneToMany(targetEntity=Synchronization::class, mappedBy="sourceObject")
     */
    private $sourceOfSynchronizations;

    public function __toString()
    {
        return $this->getName().' ('.$this->getId().')';
    }

    public function __construct(?Entity $entity = null)
    {
        $this->objectValues = new ArrayCollection();
        $this->subresourceOf = new ArrayCollection();
        $this->synchronizations = new ArrayCollection();
        $this->usedIn = new ArrayCollection();

        if ($entity) {
            $this->setEntity($entity);
        }
        $this->sourceOfSynchronizations = new ArrayCollection();
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

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Creates the self iri for an object. If an GET endpoint with id field can be determined we use the path of that endpoint.
     * If multiple GET endpoints are found, then we take the shortest IRI that can be build.
     * If no endpoint exist we use an internal reference to the admin paths.
     *
     * @return string|null
     */
    public function getSelf(): ?string
    {
        // If self not set we generate a uri with linked endpoints
        if (isset($this->self) === true || empty($this->self) === false || $this->self === '') {
            return $this->self;
        }
        $pathStrings = [];

        $pathString = '/api';

        if ($this->getEntity() === null || $this->getEntity()->getEndpoints()->isEmpty() === true) {
            // Fallback for valid url.
            if ($pathString == '/api' && $this->getId()) {
                $pathString = $pathString.'/objects/'.$this->getId()->toString();
            } elseif (!$this->getId()) {
                $pathString = '';
            }

            $this->self = $pathString;

            return $this->self;
        }

        $endpoints = $this->getEntity()->getEndpoints();
        foreach ($endpoints as $endpoint) {
            // We need a GET endpoint.
            if (!in_array('get', $endpoint->getMethods())
                && !in_array('GET', $endpoint->getMethods())
                && $endpoint->getMethods() !== []
            ) {
                continue;
            }

            $pathArray = $endpoint->getPath() ?? [];
            $idSet = false;
            $tempPath = '';

            // Skip endpoint if it does not contain id fields.
            if (in_array('id', $pathArray) === false
                && in_array('{id}', $pathArray) === false
                && in_array('uuid', $pathArray) === false
                && in_array('{uuid}', $pathArray) === false
            ) {
                continue;
            }

            // Add path item to self uri
            foreach ($pathArray as $pathItem) {
                if ($pathItem == 'id' || $pathItem == '{id}' || $pathItem == 'uuid' || $pathItem == '{uuid}') {
                    $idSet = true;
                    $tempPath .= '/'.$this->getId()->toString();
                } else {
                    $tempPath .= '/'.$pathItem;
                }
            }

            // If id is set we found a correct endpoint and we can stop the foreach
            if ($idSet == true) {
                $pathStrings[] = $pathString.$tempPath;
            }
        }

        // Sort the array from shortest to longest, discard empty path strings (/api).
        usort($pathStrings, function ($arrayItem1, $arrayItem2) {
            return $arrayItem1 !== '/api' && $arrayItem2 !== '/api' && strlen($arrayItem1) > strlen($arrayItem2);
        });

        $this->self = array_shift($pathStrings);

        return $this->self;
    }

    public function setSelf(?string $self): self
    {
        $this->self = $self;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): self
    {
        $this->application = $application;

        // If we don't have an organization we can pull one from the application
        if (!isset($this->organization) && isset($this->application) && $this->application->getOrganization()) {
            $this->setOrganization($this->application->getOrganization());
        }

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization ?? null;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function setOwner(?string $owner): self
    {
        $this->owner = $owner;

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

    /**
     * Get all the object values.
     *
     * @return Collection|Value[]
     */
    public function getObjectValues(): Collection
    {
        return $this->objectValues;
    }

    /**
     * Sets an entire collection of object values (used in the setid subscriber).
     *
     * @return $this
     */
    public function setObjectValues(Collection $objectValues): self
    {
        $this->objectValues = $objectValues;

        return $this;
    }

    /**
     * Removes all the values from this object.
     *
     * @return $this
     */
    public function clearAllValues(): self
    {
        foreach ($this->objectValues as $value) {
            $this->removeObjectValue($value);
        }

        return $this;
    }

    public function addObjectValue(Value $objectValue): self
    {
        // // Make sure we can only add a child Value if the Entity of this ObjectEntity has the Value->Attribute configured as a possible child Attribute.
        if ($this->entity !== $objectValue->getAttribute()->getEntity()) {
            return $this;
        }

        if (!$this->objectValues->contains($objectValue)) {
            $this->objectValues->add($objectValue);
            $objectValue->setObjectEntity($this);
        }

        return $this;
    }

    public function removeObjectValue(Value $objectValue): self
    {
        if ($this->objectValues->removeElement($objectValue)) {
            // set the owning side to null (unless already changed)
            if ($objectValue->getObjectEntity() === $this) {
                $objectValue->setObjectEntity(null);
            }
        }

        return $this;
    }

    public function getHasErrors(): bool
    {
        return $this->hasErrors;
    }

    public function setHasErrors(bool $hasErrors, int $level = 1): self
    {
        $this->hasErrors = $hasErrors;

        // Do the same for resources above this one if set to true
        if ($hasErrors == true && !$this->getSubresourceOf()->isEmpty() && $level < 5) {
            foreach ($this->getSubresourceOf() as $resource) {
                $resource->getObjectEntity()->setHasErrors($hasErrors, $level + 1);
            }
        }

        return $this;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function setErrors(?array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * Adds ans error to the error stack of this object.
     *
     * @param string $attributeName the atribute that throws the error
     * @param string $error         the error message
     *
     * @return array all of the errors so far
     */
    public function addError(string $attributeName, string $error, $key = null): self
    {
        $errors = $this->getErrors();

        if (!$this->hasErrors) {
            $this->setHasErrors(true);
        }

        // If the key already exisits we need to switch it to an array
        if (array_key_exists($attributeName, $errors) and !is_array($errors[$attributeName])) {
            $errors[$attributeName] = [$errors[$attributeName]];
        }
        // Check if error is already in array?
        if (array_key_exists($attributeName, $errors)) {
            $errors[$attributeName][] = $error;
        } else {
            $errors[$attributeName] = $error;
        }

        return $this->setErrors($errors);
    }

    public function getAllErrors(ArrayCollection $maxDepth = null): ?array
    {
        // Lets keep track of objects we got the errors from, for inversedBy, checking maxDepth 1:
        if (is_null($maxDepth)) {
            $maxDepth = new ArrayCollection();
        }
        $maxDepth->add($this);

        $allErrors = $this->getErrors();
        //$subResources = $this->getSubresources();
        $values = $this->getObjectValues();

        foreach ($values as $value) {
            foreach ($value->getObjects() as $key => $subResource) {
                if (!$maxDepth->contains($subResource)) {
                    if ($value->getAttribute()->getMultiple()) {
                        $key = $subResource->getSubresourceIndex() ?? $key;
                        $key = '['.$key.']';
                    } else {
                        $key = '';
                    }
                    $subErrors = $subResource->getAllErrors($maxDepth);
                    if (!empty($subErrors)) {
                        $allErrors[$value->getAttribute()->getName().$key] = $subErrors;
                    }
                }
            }
        }
        /*
        foreach ($subResources as $subresource) {
            if (!$subresource) continue; // can be null because of subresource/object fields being set to null
            if (get_class($subresource) == ObjectEntity::class) {
                if ($subresource->getHasErrors()) {
                    foreach($subresource->getSubresourceOf() as $subSubResource){
                        $allErrors[$subSubResource->getAttribute()->getName()] = $subSubResource->getAllErrors();
                    }
                }
                continue;
            }
            // If a subresource is a list of subresources (example cc/person->cc/emails)
            foreach ($subresource as $key => $listSubresource) {
                if ($listSubresource->getHasErrors()) {
                    $allErrors[$listSubresource->getSubresourceOf()->getAttribute()->getName()][$key] = $listSubresource->getAllErrors();
                }
            }
        }
        */

        return $allErrors;
    }

    public function getHasPromises(): bool
    {
        return $this->hasErrors;
    }

    public function setHasPromises(bool $hasPromises, int $level = 1): self
    {
        $this->hasPromises = $hasPromises;

        // Do the same for resources above this one if set to true
        if ($hasPromises == true && !$this->getSubresourceOf()->isEmpty() && $level < 5) {
            foreach ($this->getSubresourceOf() as $resource) {
                $resource->getObjectEntity()->setHasPromises($hasPromises, $level + 1);
            }
        }

        return $this;
    }

    public function getPromises(): ?array
    {
        return $this->promises;
    }

    public function setPromises(?array $promises): self
    {
        if (!$this->hasPromises) {
            $this->setHasPromises(true);
        }

        $this->promises = $promises;

        return $this;
    }

    public function addPromise(object $promise): array
    {
        //TODO: check if promise is already in array?
        $this->promises[] = $promise;

        if (!$this->hasPromises) {
            $this->setHasPromises(true);
        }

        return $this->promises;
    }

    public function getAllPromises(): ?array
    {
        $allPromises = [];
        $subResources = $this->getSubresources();
        foreach ($subResources as $subResource) {
            if (get_class($subResource) == ObjectEntity::class) {
                $allPromises = $subResource->getAllPromises();
                continue;
            }
            // If a subresource is a list of subresources (example cc/person->cc/emails)
            foreach ($subResource as $listSubResource) {
                $allPromises = array_merge($allPromises, $listSubResource->getAllPromises());
            }
        }

        return array_merge($allPromises, $this->errors);
    }

    public function getExternalResult(): ?array
    {
        return $this->externalResult;
    }

    public function setExternalResult(?array $externalResult): self
    {
        $this->externalResult = $externalResult;

        return $this;
    }

    /**
     * Gets the value of the Value object based on the attribute string name or attribute object.
     *
     * @param string|Attribute $attribute
     *
     * @return array|bool|string|int|object Returns a Value if its found or false when its not found.
     */
    public function getValue($attribute)
    {
        // If we can find the Value object return the value of the Value object
        $valueObject = $this->getValueObject($attribute);
        if ($valueObject instanceof Value) {
            return $valueObject->getValue();
        }

        // If not return false
        return false;
    }

    /**
     * Gets a Value object based on the attribute string name or attribute object.
     *
     * @param string|Attribute $attribute
     *
     * @return Value|bool Returns a Value if its found or false when its not found.
     */
    public function getValueObject($attribute)
    {
        if (is_string($attribute)) {
            $attribute = $this->getAttributeObject($attribute);
        }

        // If we have a valid Attribute object
        if ($attribute instanceof Attribute) {
            return $this->getValueByAttribute($attribute);
        }

        // If not return false
        return false;
    }

    /**
     * Gets a Attribute object based on the attribute string name.
     *
     * @param string $attributeName
     *
     * @return Attribute|false Returns an Attribute if its found or false when its not found.
     */
    public function getAttributeObject(string $attributeName)
    {
        if (!$this->getEntity()) {
            return false;
        }
        $attribute = $this->getEntity()->getAttributeByName($attributeName);

        // If we have a valid Attribute object
        if ($attribute instanceof Attribute) {
            return $attribute;
        }

        // If not return false
        return false;
    }

    /**
     * Get a value based on an attribute.
     *
     * @param Attribute $attribute the attribute that you are searching for
     *
     * @return Value Either the current value for this attribute or a new value for the attribute if there isnt a current value
     */
    private function getValueByAttribute(Attribute $attribute): Value
    {
        if (!$this->getEntity()->getAttributes()->contains($attribute)) {
            $this->addError($attribute->getName(), 'The entity: '.$this->getEntity()->getName().' does not have this attribute. (intern getValueByAttribute error)');
        }

        // Check if value with this attribute exists for this ObjectEntity
        $criteria = Criteria::create()->andWhere(Criteria::expr()->eq('attribute', $attribute))->setMaxResults(1);

        $values = $this->getObjectValues()->matching($criteria);

        if ($values->isEmpty()) {
            // If no value with this attribute was found
            $value = new Value($attribute, $this);
            $this->addObjectValue($value);

            return $value;
        }

        return $values->first();
    }

    /**
     * Sets a value based on the attribute string name or atribute object.
     *
     * @param string|Attribute $attribute
     * @param                  $value
     * @param bool             $unsafe
     *
     * @throws Exception
     *
     * @return false|Value
     */
    public function setValue($attribute, $value, bool $unsafe = false, ?DateTimeInterface $dateModified = null)
    {
        $valueObject = $this->getValueObject($attribute);
        // If we find the Value object we set the value
        if ($valueObject instanceof Value) {
            return $valueObject->setValue($value, $unsafe, $dateModified);
        }

        // If not return false
        return false;
    }

    public function setDefaultValues(bool $unsafe = false, ?DateTimeInterface $dateModified = null): self
    {
        foreach ($this->getEntity()->getAttributes() as $attribute) {
            $criteria = Criteria::create()->andWhere(Criteria::expr()->eq('attribute', $attribute))->setMaxResults(1);
            $values = $this->getObjectValues()->matching($criteria);
            if ($values->isEmpty() && $attribute->getDefaultValue()) {
                $this->setValue($attribute, $attribute->getDefaultValue(), $unsafe, $dateModified);
            }
        }

        return $this;
    }

    /**
     * All entities should incoprate a from schema function.
     *
     * @param array $schema The object as an array
     *
     * @return $this
     */
    public function fromSchema(array $schema): self
    {
        return $this;
    }

    /**
     * Populate this object with an array of values, where attributes are diffined by key.
     *
     * @param array                  $array        the data to set
     * @param bool                   $unsafe       unset attributes that are not included in the hydrator array
     * @param DateTimeInterface|null $dateModified
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    public function hydrate(array $array, bool $unsafe = false, ?DateTimeInterface $dateModified = null): ObjectEntity
    {
        // Failsafe: we should never continue if an ObjectEntity has no Entity
        if (!$this->entity) {
            throw new Exception("Can't hydrate an ObjectEntity ({$this->id->toString()}) with no Entity");
        }

        $array = $this->includeEmbeddedArray($array);
        $hydratedValues = [];

        // Change Cascade
        if (!$dateModified) {
            $dateModified = new DateTime();
            $this->changeCascade($dateModified);
        }

        // Note down that the object has been hydrated
        $this->hydrated = true;

        foreach ($array as $key => $value) {
            $this->setValue($key, $value, $unsafe, $dateModified);
            $hydratedValues[] = $key;
        }

        if ($unsafe) {
            foreach ($this->getObjectValues() as $value) {
                // Drop values from values
                $value->removeNonHydratedObjects();
                // Drop the value itself
                if (!in_array($value->getAttribute()->getName(), $hydratedValues)) {
                    $this->removeObjectValue($value);
                }
            }
        }

        $this->setDefaultValues($unsafe, $dateModified);

        return $this;
    }

    /**
     * This function will check if the given array has an embedded array, if so it will move all objects from the
     * embedded array to the keys outside this embedded array and (by default) unset the entire embedded array.
     *
     * @param array $array              The array to move and unset embedded array from.
     * @param bool  $unsetEmbeddedArray Default=true. If false the embedded array will not be removed from $array.
     *
     * @return array The updated/changed $array. Or unchanged $array if it did not contain an embedded array.
     */
    public function includeEmbeddedArray(array $array, bool $unsetEmbeddedArray = true): array
    {
        // Check if array has embedded array
        if ($embeddedKey = $this->getEmbeddedKey($array)) {
            $embedded = $this->getEmbeddedArray($array, $embeddedKey);
            foreach ($embedded as $key => $value) {
                // Replace array[$key] with the embedded array value
                $array[$key] = $this->includeEmbeddedArray($value);
            }

            // Unset the embedded array if we want to
            if ($unsetEmbeddedArray) {
                unset($array[$embeddedKey]);
            }
        }

        return $array;
    }

    /**
     * Check if the given array has an embedded array and if so return it.
     *
     * @param array $array       An array to check.
     * @param null  $embeddedKey If we already (used getEmbeddedKey() function or) know what the key is of the embedded array. ('embedded', '@embedded' or '_embedded')
     *
     * @return false|mixed The embedded array found in $array or false if we didn't find an embedded array.
     */
    private function getEmbeddedArray(array $array, $embeddedKey = null)
    {
        $embeddedKey = $embeddedKey ?? $this->getEmbeddedKey($array);

        return $embeddedKey ? $array[$embeddedKey] : false;
    }

    /**
     * Looks for the embedded key, the key used for the embedded array. This is different for json, jsonld and jsonhal.
     *
     * @param array $array The array to check.
     *
     * @return false|string
     */
    private function getEmbeddedKey(array $array)
    {
        return
            isset($array['embedded']) ? 'embedded' : (
                isset($array['@embedded']) ? '@embedded' : (
                    isset($array['_embedded']) ? '_embedded' : false
                )
            );
    }

    /*
     * A recursion save way of getting subresources
     */
    public function getAllSubresources(?ArrayCollection $result, Attribute $attribute = null): ArrayCollection
    {
        $subresources = $this->getSubresources($attribute);

        foreach ($subresources as $subresource) {
            if (!$result->contains($subresource)) {
                $result->add($subresource);
            }
        }

        return $result;
    }

    /**
     * Function to get al the subresources of this object entity.
     *
     * @return ArrayCollection the subresources of this object entity
     */
    public function getSubresources(Attribute $attribute = null): ArrayCollection
    {
        // Get all values of this ObjectEntity with attribute type object
        //$values = $this->getObjectValues()->filter(function (Value $value) {
        //    return $value->getAttribute()->getType() === 'object';
        //});

        /*
        $values = $this->getObjectValues();
        foreach($values as $value){
            foreach ($value->getObjects() as $object) {
                var_dump("found:");
            }
        }
        */
        $subresources = new ArrayCollection();
        foreach ($this->getObjectValues() as $value) {
            if ($attribute && $value->getAttribute() !== $attribute) {
                continue;
            }
            foreach ($value->getObjects() as $objectEntity) {
                // prevent double work and downward recurions
                $subresources->add($objectEntity);
            }
        }

        return $subresources;
    }

    /**
     * Checks conditional logic on values.
     *
     * @return $this
     */
    public function checkConditionlLogic(ArrayCollection $maxDepth = null): self
    {
        // Lets keep track of objects we already checked, for inversedBy, checking maxDepth 1:
        if (is_null($maxDepth)) {
            $maxDepth = new ArrayCollection();
        }
        $maxDepth->add($this);

        // lets cascade
        if (!$this->getSubresources()->isEmpty()) {
            foreach ($this->getSubresources() as $subresource) {
                // Do not call recursive function if we reached maxDepth (if we already checked this object before)
                if (!$maxDepth->contains($subresource)) {
                    $subresource->checkConditionlLogic($maxDepth);
                }
            }
        }

        /* @todo we should only check values that actuale have conditional logic optmimalisation */
        // do the actual chack
        foreach ($this->getObjectValues() as $value) {
            if (count($value->getAttribute()->getRequiredIf()) == 0) {
                continue;
            }
            // Oke loop the conditions
            foreach ($value->getAttribute()->getRequiredIf() as $conditionProperty => $conditionValue) {
                // we only have a problem if the current value is empty and bools might be false when empty
                if ($value->getValue() || ($value->getAttribute()->getType() == 'boolean' && !is_null($value->getValue()))) {
                    $explodedConditionValue = explode('.', $conditionValue);
                    $getValue = $value->getValue() instanceof ObjectEntity ? $value->getValue()->getExternalId() : $value->getValue();
                    if (
                        !$value->getAttribute()->getDefaultValue()
                        || ($value->getAttribute()->getDefaultValue() !== $getValue)
                        || end($explodedConditionValue) != 'noDefaultValue'
                    ) {
                        continue;
                    } else {
                        $conditionValue = implode('.', array_slice($explodedConditionValue, 0, -1));
                    }
                }
                // so lets see if we should have a value
                //var_dump($conditionProperty);
                //var_dump($conditionValue);
                //var_dump($this->getValueByAttribute($this->getEntity()->getAttributeByName($conditionProperty))->getValue());

                if (is_array($conditionValue)) {
                    foreach ($conditionValue as $convar) {
                        // Hacky
                        //if($convar == 'true'  ) {$convar = true;}
                        //if($convar == 'false'  ) {$convar = false;}
                        $checkAgainst = $this->getValue($conditionProperty);
                        if (!is_array($checkAgainst) && $checkAgainst == $convar) {
                            $this->addError($value->getAttribute()->getName(), 'Is required because property '.$conditionProperty.' has the value: '.$convar);
                        } elseif (is_array($checkAgainst) && in_array($convar, $checkAgainst)) {
                            $this->addError($value->getAttribute()->getName(), 'Is required because property '.$conditionProperty.' has the value: '.$convar);
                        }
                    }
                } else {
                    // Hacky
                    //if($conditionValue == 'true'  ) {$conditionValue = true;}
                    //if($conditionValue == 'false'  ) {$conditionValue = false;}
                    $checkAgainst = $this->getValue($conditionProperty);
                    if (!is_array($checkAgainst) && $checkAgainst == $conditionValue) {
                        $this->addError($value->getAttribute()->getName(), 'Is required because property '.$conditionProperty.' has the value: '.$conditionValue);
                    } elseif (is_array($checkAgainst) && in_array($conditionValue, $checkAgainst)) {
                        $this->addError($value->getAttribute()->getName(), 'Is required because property '.$conditionProperty.' has the value: '.$conditionValue);
                    }
                }
            }
            // Oke loop the conditions
            /*
            foreach($value->getAttribute()->getForbiddenIf() as $conditionProperty=>$conditionValue){
                // we only have a problem if the current value is full
                if(!$value->getValue()){continue;}
                // so lets see if we should have a value
                if($this->getEntity()->getAttributeByName($conditionProperty) && $this->getValueByAttribute($this->getEntity()->getAttributeByName($conditionProperty))->getValue() == $conditionValue){
                    $this->addError($value->getAttribute()->getName(), 'Is forbidden because property '.$conditionProperty.' has the value: '.$conditionValue);
                }
            }
            */
        }

        return $this;
    }

    /**
     * Convienance API for throwing an data object and is children into an array.
     *
     * @param array $configuration The configuration for this function
     *
     * @return array the array holding all the data     *
     */
    public function toArray(array $configuration = []): array
    {
        // Let's default the config array
        !isset($configuration['level']) ? $configuration['level'] = 1 : '';
        !isset($configuration['maxdepth']) ? $configuration['maxdepth'] = $this->getEntity()->getMaxDepth() : '';
        !isset($configuration['renderedObjects']) ? $configuration['renderedObjects'] = [] : '';
        !isset($configuration['embedded']) ? $configuration['embedded'] = false : '';
        !isset($configuration['onlyMetadata']) ? $configuration['onlyMetadata'] = false : '';
        !isset($configuration['metadata']) ? $configuration['metadata'] = true : '';

        // Working arrays
        $array = [];
        $currentObjects = [];
        $embedded = [];

        if ($configuration['metadata'] === true) {
            // The new metadata
            $array['_self'] = [
                'id'               => $this->getId() ? $this->getId()->toString() : null,
                'name'             => $this->getName(),
                'self'             => $this->getSelf(),
                'schema'           => [
                    'id'  => $this->getEntity()->getId()->toString(),
                    'name'=> $this->getEntity()->getName(),
                    'ref' => $this->getEntity()->getReference(),
                ],
                'level'            => $configuration['level'],
                'dateCreated'      => $this->getDateCreated() ? $this->getDateCreated()->format('c') : null,
                'dateModified'     => $this->getDateModified() ? $this->getDateModified()->format('c') : null,
                'owner'            => [
                    'id'    => $this->getOwner(),
                    'name'  => isset($configuration['user']) ? $configuration['user']->getName() : $this->getOwner(),
                    'ref'   => isset($configuration['user']) ? $configuration['user']->getReference() : $this->getOwner(),
                ],
                'organization'     => [
                    'id'  => $this->getOrganization() ? $this->getOrganization()->getId()->toString() : null,
                    'name'=> $this->getOrganization() ? $this->getOrganization()->getName() : null,
                    'ref' => $this->getOrganization() ? $this->getOrganization()->getReference() : null,
                ],
                'application'      => [
                    'id'  => $this->getApplication() ? $this->getApplication()->getId()->toString() : null,
                    'name'=> $this->getApplication() ? $this->getApplication()->getName() : null,
                    'ref' => $this->getApplication() ? $this->getApplication()->getReference() : null,
                ],
                'synchronizations' => $this->getReadableSyncDataArray(),
            ];

            // If we don't need the actual object data we can exit here
            if ($configuration['onlyMetadata'] === true) {
                return $array;
            }
        }

        // Let loop trough al the values
        // TODO: we need to clean this up, at least move duplicate code to functions
        foreach ($this->getEntity()->getAttributes() as $attribute) {
            $valueObject = $this->getValueObject($attribute);
            // Subobjects are a bit complicated
            if ($attribute->getType() == 'object') {
                if ($attribute->getObject() === null) {
                    // todo: error or even critical monolog?
                    $array[$attribute->getName()] = "Attribute {$attribute->getId()->toString()} of type 'object' has not Entity connected through Attribute->object";
                } elseif ($valueObject->getValue() == null) {
                    $array[$attribute->getName()] = null;
                } elseif (!$attribute->getMultiple() && $configuration['level'] < $configuration['maxdepth']) {
                    if (count($valueObject->getObjects()) === 0) {
                        $array[$attribute->getName()][] = null;
                    } else {
                        $object = $valueObject->getObjects()->first();
                        $currentObjects[] = $object;
                        // Only add an object if it hasn't bean added yet
                        if (!in_array($object, $configuration['renderedObjects']) && !$attribute->getObject()->isExcluded()) {
                            $config = $configuration;
                            $config['renderedObjects'][] = $object;
                            $config['level'] = $config['level'] + 1;
                            $objectToArray = $object->toArray($config);

                            // Check if we want an embedded array
                            if ($configuration['embedded'] && !$attribute->getInclude()) {
                                switch ($attribute->getFormat()) {
                                    case 'uuid':
                                        $array[$attribute->getName()] = $object->getId()->toString();
                                        $embedded[$attribute->getName()] = $objectToArray;
                                        break;
                                    case 'url':
                                        $array[$attribute->getName()] = $object->getUri();
                                        $embedded[$attribute->getName()] = $objectToArray;
                                        break;
                                    case 'json':
                                        $array[$attribute->getName()] = $objectToArray;
                                        break;
                                    case 'iri':
                                    default:
                                        $array[$attribute->getName()] = $object->getSelf();
                                        $embedded[$attribute->getName()] = $objectToArray;
                                        break;
                                }
                                continue;
                            }
                            $array[$attribute->getName()] = $objectToArray; // getValue will return a single ObjectEntity
                        }
                        // If we don't set the full object then we want to set self
                        else {
                            // $array[$attribute->getName()] = $object->getSelf() ?? ('/api' . ($object->getEntity()->getRoute() ?? $object->getEntity()->getName()) . '/' . $object->getId());
                            $array[$attribute->getName()] = $object->getSelf();
                        }
                    }
                } elseif ($configuration['level'] < $configuration['maxdepth']) {
                    if (count($valueObject->getObjects()) === 0) {
                        $array[$attribute->getName()] = [];
                    } else {
                        $currentObjects[] = $valueObject->getObjects()->toArray();
                        foreach ($valueObject->getObjects() as $object) {
                            // Only add an object if it hasn't bean added yet
                            if (!in_array($object, $configuration['renderedObjects']) && !$attribute->getObject()->isExcluded()) {
                                $config = $configuration;
                                $config['renderedObjects'] = array_merge($configuration['renderedObjects'], $currentObjects);
                                $config['level'] = $config['level'] + 1;
                                $objectToArray = $object->toArray($config);

                                // Check if we want an embedded array
                                if ($configuration['embedded'] && !$attribute->getInclude()) {
                                    switch ($attribute->getFormat()) {
                                        case 'uuid':
                                            $array[$attribute->getName()][] = $object->getId()->toString();
                                            $embedded[$attribute->getName()] = $objectToArray;
                                            break;
                                        case 'url':
                                        case 'uri':
                                            $array[$attribute->getName()][] = $object->getUri();
                                            $embedded[$attribute->getName()] = $objectToArray;
                                            break;
                                        case 'json':
                                            $array[$attribute->getName()][] = $objectToArray;
                                            break;
                                        case 'iri':
                                        default:
                                            $array[$attribute->getName()][] = $object->getSelf();
                                            $embedded[$attribute->getName()][] = $objectToArray;
                                            break;
                                    }
                                    continue;
                                }
                                $array[$attribute->getName()][] = $objectToArray;
                            }
                            // If we don't set the full object then we want to set self
                            else {
                                // $array[$attribute->getName()][] = $object->getSelf() ?? ('/api' . ($object->getEntity()->getRoute() ?? $object->getEntity()->getName()) . '/' . $object->getId());
                                $array[$attribute->getName()][] = $object->getSelf();
                            }
                        }
                    }
                }
                // But normal values are simple
            } else {
                $array[$attribute->getName()] = $valueObject->getValue();
            }
        }

        if (!empty($embedded)) {
            // todo: this should be _embedded
            $array['embedded'] = $embedded;
        }

        return $array;
    }

    /**
     * Adds the most important data of all synchronizations this Object has to an array and returns this array or null if this Object has no Synchronizations.
     *
     * @return array|null
     */
    public function getReadableSyncDataArray(): ?array
    {
        if (!empty($this->getSynchronizations()) && is_countable($this->getSynchronizations()) && count($this->getSynchronizations()) > 0) {
            $synchronizations = [];
            foreach ($this->getSynchronizations() as $synchronization) {
                $synchronizations[] = [
                    'id'      => $synchronization->getId()->toString(),
                    'source' => [
                        'id'            => $synchronization->getSource()->getId()->toString(),
                        'ref'           => $synchronization->getSource()->getReference(),
                        'name'          => $synchronization->getSource()->getName(),
                        'description'   => $synchronization->getSource()->getDescription(),
                        'location'      => $synchronization->getSource()->getLocation(),
                    ],
                    'endpoint'          => $synchronization->getEndpoint(),
                    'sourceId'          => $synchronization->getSourceId(),
                    'dateCreated'       => $synchronization->getDateCreated() ? $synchronization->getDateCreated()->format('c') : null,
                    'dateModified'      => $synchronization->getDateModified() ? $synchronization->getDateModified()->format('c') : null,
                    'lastChecked'       => $synchronization->getLastChecked() ? $synchronization->getLastChecked()->format('c') : null,
                    'lastSynced'        => $synchronization->getLastSynced() ? $synchronization->getLastSynced()->format('c') : null,
                    'sourceLastChanged' => $synchronization->getSourceLastChanged() ? $synchronization->getSourceLastChanged()->format('c') : null,
                ];
            }

            return $synchronizations;
        }

        return null;
    }

    /**
     * @return Collection|Value[]
     */
    public function getSubresourceOf(): Collection
    {
        return $this->subresourceOf;
    }

    /**
     * Try to find a Value this ObjectEntity is a child of. Searching/filtering these values by a specific Attribute.
     *
     * @param Attribute $attribute
     *
     * @return ArrayCollection
     */
    public function findSubresourceOf(Attribute $attribute): ArrayCollection
    {
        $subresourceOfFound = $this->subresourceOf->filter(function ($subresourceOf) use ($attribute) {
            return $subresourceOf->getAttribute() === $attribute;
        });
        if (count($subresourceOfFound) > 0) {
            return $subresourceOfFound;
        }

        return new ArrayCollection();
    }

    public function addSubresourceOf(Value $subresourceOf): self
    {
        // Make sure we can only add a parent Value if the Entity of this ObjectEntity has the Value->Attribute configured as possible parent Attribute.
        if ($this->entity->getUsedIn()->contains($subresourceOf->getAttribute()) === false) {
            return $this;
        }

        // let add this
        if (!$this->subresourceOf->contains($subresourceOf)) {
            $this->subresourceOf->add($subresourceOf);
        }
        // Lets make this twoway
        if (!$subresourceOf->getObjects()->contains($this)) {
            $subresourceOf->addObject($this);
        }

        return $this;
    }

    public function removeSubresourceOf(Value $subresourceOf): self
    {
        if ($this->subresourceOf->contains($subresourceOf)) {
            // SubresourceOf will be removed from this ObjectEntity in this function;
            $subresourceOf->removeObject($this);
        }

        return $this;
    }

    public function getSubresourceIndex(): ?string
    {
        return $this->subresourceIndex;
    }

    public function setSubresourceIndex(?string $subresourceIndex): self
    {
        $this->subresourceIndex = $subresourceIndex;

        return $this;
    }

    public function getLock(): ?string
    {
        return $this->lock;
    }

    public function setLock(?string $lock): self
    {
        $this->lock = $lock;

        return $this;
    }

    /**
     * @return Collection|Synchronization[]
     */
    public function getSynchronizations(): Collection
    {
        return $this->synchronizations;
    }

    public function addSynchronization(Synchronization $synchronization): self
    {
        if (!$this->synchronizations->contains($synchronization)) {
            $this->synchronizations[] = $synchronization;
            if ($synchronization->getObject() !== $this) {
                $synchronization->setObject($this);
            }
        }

        return $this;
    }

    public function removeSynchronization(Synchronization $synchronization): self
    {
        if ($this->synchronizations->removeElement($synchronization)) {
            // set the owning side to null (unless already changed)
            if ($synchronization->getObject() === $this) {
                $synchronization->setObject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Synchronization[]
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
                $attribute->setReference(null);
            }
        }

        return $this;
    }

    public function getHydrated(): bool
    {
        return $this->hydrated;
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
     * Cascades a 'is changed' upwards, with other words notifies objects that us this object has changed so that they to ara changes.
     *
     * @param DateTimeInterface $dateModified
     *
     * @return $this
     */
    public function changeCascade(DateTimeInterface $dateModified): self
    {
        $this->setDateModified($dateModified);

        $values = $this->subresourceOf->filter(
            function(Value $subresource) {
                return $subresource->getAttribute()->getCacheSubObjects();
            }
        );

        // Lets update the date created of parent resources
        foreach ($values as $mainResourceValue) {
            $mainresource = $mainResourceValue->getObjectEntity();
            if ($mainresource->getDateModified() < $this->getDateModified()) {
                $mainresource->changeCascade($dateModified);
            }
        }

        return  $this;
    }

    /**
     * Set name on pre persist.
     *
     * This function makes sure that each and every oject alwys has a name when saved
     *
     * @ORM\PrePersist
     *
     * @ORM\PreUpdate
     */
    public function prePersist(): void
    {
        if (!$this->entity) {
            return;
        }

        // Lets see if the name is configured
        $nameProperties = array_merge(['name', 'title', 'naam', 'titel'], $this->entity->getNameProperties());
        $nameArray = [];
        foreach ($this->getObjectValues() as $value) {
            if (in_array($value->getAttribute()->getName(), $nameProperties)) {
                $nameArray[] = $value->getStringValue();
            }
        }

        if (count($nameArray) > 0 && $name = implode(' ', $nameArray)) {
            $this->setName($name);
        }

        if (!$this->getName() && $this->getId()) {
            $this->setName($this->getId()->toString());
        }

        // Todo: this is an ugly fix, in actuallity we should run a postUpdate subscriber that checks this and repersists the enitity if thsi happens (it can anly happen if we dont have an id on pre persist e.g. new objects)
        // Just in case we endup here
        if (!$this->getName()) {
            $this->setName('No name could be established for this entity');
        }
    }

    /**
     * @return Collection|Synchronization[]
     */
    public function getSourceOfSynchronizations(): Collection
    {
        return $this->sourceOfSynchronizations;
    }

    public function addSourceOfSynchronization(Synchronization $sourceOfSynchronization): self
    {
        if (!$this->sourceOfSynchronizations->contains($sourceOfSynchronization)) {
            $this->sourceOfSynchronizations[] = $sourceOfSynchronization;
            $sourceOfSynchronization->setSourceObject($this);
        }

        return $this;
    }

    public function removeSourceOfSynchronization(Synchronization $sourceOfSynchronization): self
    {
        if ($this->sourceOfSynchronizations->removeElement($sourceOfSynchronization)) {
            // set the owning side to null (unless already changed)
            if ($sourceOfSynchronization->getSourceObject() === $this) {
                $sourceOfSynchronization->setSourceObject(null);
            }
        }

        return $this;
    }
}
