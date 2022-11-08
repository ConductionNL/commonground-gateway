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
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Description.
 *
 * @category Entity
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *     "get"={"path"="/admin/object_entities/{id}"},
 *     "get_sync"={
 *          "method"="GET",
 *          "path"="/admin/object_entities/{id}/sync"
 *      },
 *     "get_object"={
 *          "method"="GET",
 *          "path"="/admin/objects/{id}"
 *      },
 *     "put"={"path"="/admin/object_entities/{id}"},
 *     "put_object"={
 *          "method"="PUT",
 *          "path"="/admin/objects/{id}"
 *      },
 *     "delete"={"path"="/admin/object_entities/{id}"},
 *     "delete_object"={
 *          "method"="DELETE",
 *          "path"="/admin/objects/{id}"
 *      }
 *  },
 *  collectionOperations={
 *     "get"={"path"="/admin/object_entities"},
 *     "get_objects"={
 *          "method"="GET",
 *          "path"="/admin/objects"
 *      },
 *     "get_objects_schema"={
 *          "method"="GET",
 *          "path"="/admin/objects/schema/{schemaId}"
 *      },
 *     "post"={"path"="/admin/object_entities"},
 *     "post_objects_schema"={
 *          "method"="POST",
 *          "path"="/admin/objects/schema/{schemaId}"
 *      },
 *  })
 * @ORM\Entity(repositoryClass="App\Repository\ObjectEntityRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "uri": "ipartial",
 *     "entity.id": "exact"
 * })
 */
class ObjectEntity
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

    /**
     * @var string The {at sign}id or self->href of this Object.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $self;

    /**
     * @var string UUID of the external object of this ObjectEntity
     *
     * @Assert\Uuid
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $externalId;

    /**
     * @var string An uri
     *
     * @Assert\Url
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uri;

    /**
     * @Groups({"read", "write"})
     * @ORM\ManyToOne(targetEntity=Application::class, inversedBy="objectEntities")
     * @MaxDepth(1)
     */
    private ?Application $application = null;

    /**
     * @var string An uuid or uri of an organization
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $organization;

    /**
     * @var string An uuid or uri of an owner
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $owner;

    /**
     * @Groups({"read", "write"})
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="objectEntities", fetch="EAGER")
     * @MaxDepth(1)
     */
    private ?Entity $entity = null;

    /**
     * @Groups({"read", "write"})
     * @ORM\OneToMany(targetEntity=Value::class, mappedBy="objectEntity", cascade={"persist","remove"})
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
     * @ORM\ManyToMany(targetEntity=GatewayResponseLog::class, mappedBy="objectEntity", fetch="EXTRA_LAZY")
     */
    private $responseLogs;

    /*
     * recursion stack
     *
     * the point of the recursion stack is to prevent the loadinf of objects that are already loaded
     */
    private Collection $recursionStack;

    /**
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=Value::class, inversedBy="objects", cascade={"persist"})
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
     * @ORM\OneToMany(targetEntity=RequestLog::class, mappedBy="objectEntity", fetch="EXTRA_LAZY", cascade={"remove"})
     */
    private Collection $requestLogs;

    /**
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity=Synchronization::class, mappedBy="object", fetch="EXTRA_LAZY", cascade={"remove"})
     */
    private Collection $synchronizations;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource was last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    public function __construct(?Entity $entity = null)
    {
        $this->objectValues = new ArrayCollection();
        $this->responseLogs = new ArrayCollection();
        $this->subresourceOf = new ArrayCollection();
        $this->requestLogs = new ArrayCollection();
        $this->synchronizations = new ArrayCollection();

        if ($entity) {
            $this->setEntity($entity);
        }
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getSelf(): ?string
    {
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

        return $this;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function setOrganization(?string $organization): self
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
     * @return Collection|Value[]
     */
    public function getObjectValues(): Collection
    {
        return $this->objectValues;
    }

    public function addObjectValue(Value $objectValue): self
    {
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
     * @return array|bool|string|int|object Returns a Value if its found or false when its not found
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
     * @return Value|bool Returns a Value if its found or false when its not found
     */
    public function getValueObject($attribute)
    {
        if (is_string($attribute)) {
            $attribute = $this->getEntity()->getAttributeByName($attribute);
        }

        if (!$attribute instanceof Attribute) {
            return false;
        }

        return $this->getValueByAttribute($attribute);
    }

    /**
     * Sets a value based on the attribute string name or atribute object.
     *
     * @param string|Attribute $attribute
     *
     * @throws Exception
     *
     * @return false|Value
     */
    public function setValue($attribute, $value)
    {
        $valueObject = $this->getValueObject($attribute);
        // If we find the Value object we set the value
        if ($valueObject instanceof Value) {
            return $valueObject->setValue($value);
        }

        // If not return false
        return false;
    }

    /**
     * Populate this object with an array of values, where attributes are diffined by key.
     *
     * @param array $array
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    public function hydrate(array $array): ObjectEntity
    {
        foreach ($array as $key => $value) {
            $this->setValue($key, $value);
        }

        return $this;
    }

    /**
     * Get an value based on a attribut.
     *
     * @param Attribute $attribute the attribute that you are searching for
     *
     * @return Value Iether the current value for this attribute or a new value for the attribute if there isnt a current value
     */
    public function getValueByAttribute(Attribute $attribute): Value
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

    /*
     * A recursion save way of getting subresources
     */
    public function getAllSubresources(?ArrayCollection $result): ArrayCollection
    {
        $subresources = $this->getSubresources();

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
    public function getSubresources(): ArrayCollection
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
            foreach ($value->getObjects() as $objectEntity) {
                // prevent double work and downward recurions
                $subresources->add($objectEntity);
            }
        }

        return $subresources;
    }

    /**
     * @return Collection|GatewayResponseLog[]
     */
    public function getResponseLogs(): Collection
    {
        return $this->responseLogs;
    }

    public function addResponseLog(GatewayResponseLog $responseLog): self
    {
        if (!$this->responseLogs->contains($responseLog)) {
            $this->responseLogs->add($responceLog);
            $responceLog->setObjectEntity($this);
        }

        return $this;
    }

    public function removeResponseLog(GatewayResponseLog $responceLog): self
    {
        if ($this->responceLogs->removeElement($responceLog)) {
            // set the owning side to null (unless already changed)
            if ($responceLog->getObjectEntity() === $this) {
                $responceLog->setObjectEntity(null);
            }
        }

        return $this;
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
                        $checkAgainst = $this->getValueByAttribute($this->getEntity()->getAttributeByName($conditionProperty))->getValue();
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
                    $checkAgainst = $this->getValueByAttribute($this->getEntity()->getAttributeByName($conditionProperty))->getValue();
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
     * @return array the array holding all the data     *
     */
    public function toArray(int $level = 1, array $extend = ['id']): array
    {
        $array = [];
        in_array('id', $extend) && $array['id'] = (string) $this->getId();
        in_array('self', $extend) && $array['x-commongateway-metadata']['self'] = $this->getSelf(); //todo? $this->getSelf() ?? $this->setSelf(???->createSelf($this))->getSelf()
        in_array('synchronizations', $extend) && $array['x-commongateway-metadata']['synchronizations'] = $this->getReadableSyncDataArray();
        foreach ($this->getEntity()->getAttributes() as $attribute) {
            $valueObject = $this->getValueByAttribute($attribute);
            if ($attribute->getType() == 'object') {
                if ($valueObject->getValue() == null) {
                    $array[$attribute->getName()] = null;
                } elseif (!$attribute->getMultiple() && $level < 5) {
                    $array[$attribute->getName()] = $valueObject->getObjects()->first()->toArray($level + 1, $extend);
                } elseif ($level < 5) {
                    foreach ($valueObject->getObjects() as $object) {
                        $array[$attribute->getName()][] = $object->toArray($level + 1, $extend); // getValue will return a single ObjectEntity
                    }
                }
            } else {
                $array[$attribute->getName()] = $valueObject->getValue();
            }
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
                    'gateway' => [
                        'id'       => $synchronization->getGateway()->getId()->toString(),
                        'name'     => $synchronization->getGateway()->getName(),
                        'location' => $synchronization->getGateway()->getLocation(),
                    ],
                    'endpoint'          => $synchronization->getEndpoint(),
                    'sourceId'          => $synchronization->getSourceId(),
                    'dateCreated'       => $synchronization->getDateCreated(),
                    'dateModified'      => $synchronization->getDateModified(),
                    'lastChecked'       => $synchronization->getLastChecked(),
                    'lastSynced'        => $synchronization->getLastSynced(),
                    'sourceLastChanged' => $synchronization->getSourceLastChanged(),
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

    /**
     * @return Collection|RequestLog[]
     */
    public function getRequestLogs(): Collection
    {
        return $this->requestLogs;
    }

    public function addRequestLog(RequestLog $requestLog): self
    {
        if (!$this->requestLogs->contains($requestLog)) {
            $this->requestLogs[] = $requestLog;
            $requestLog->setObjectEntity($this);
        }

        return $this;
    }

    public function removeRequestLog(RequestLog $requestLog): self
    {
        if ($this->requestLogs->removeElement($requestLog)) {
            // set the owning side to null (unless already changed)
            if ($requestLog->getObjectEntity() === $this) {
                $requestLog->setObjectEntity(null);
            }
        }

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
            $synchronization->setObject($this);
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
