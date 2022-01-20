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
 *      "get"={"path"="/admin/object_entities/{id}"},
 *      "put"={"path"="/admin/object_entities/{id}"},
 *      "delete"={"path"="/admin/object_entities/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/object_entities"},
 *      "post"={"path"="/admin/object_entities"}
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
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=Value::class, inversedBy="objects", cascade={"persist"})
     */
    private $subresourceOf;

    /**
     * @var Datetime The moment this request was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this request last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    /**
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity=RequestLog::class, mappedBy="objectEntity", fetch="EXTRA_LAZY", cascade={"remove"})
     */
    private Collection $requestLogs;

    public function __construct()
    {
        $this->objectValues = new ArrayCollection();
        $this->responseLogs = new ArrayCollection();
        $this->subresourceOf = new ArrayCollection();
        $this->requestLogs = new ArrayCollection();

        //TODO: better way of defaulting dateCreated & dateModified with orm?
        // (options CURRENT_TIMESTAMP or 0 does not work)
        $now = new DateTime();
        $this->setDateCreated($now);
        $this->setDateModified($now);
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
        //TODO: check if error is already in array?
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
                if ($value->getAttribute()->getMultiple()) {
                    $key = '['.$key.']';
                } else {
                    $key = '';
                }
                if (!$maxDepth->contains($subResource)) {
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
            $value = new Value();
            $value->setAttribute($attribute);
            $value->setObjectEntity($this);
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
            foreach ($value->getAttribute()->getRequiredIf() as $conditionProperty=>$conditionValue) {
                // we only have a problem if the current value is empty and bools might be false when empty
                if ($value->getValue() || ($value->getAttribute()->getType() == 'boolean' && !is_null($value->getValue()))) {
                    continue;
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
    public function toArray(int $level = 1): array
    {
        $array = [];
        $array['id'] = (string) $this->getId();
        foreach ($this->getObjectValues() as $value) {
            if (!$value->getObjects()->isEmpty() && $level < 5) {
                foreach ($value->getObjects() as $object) {
                    $array[$value->getAttribute()->getName()] = $object->toArray($level + 1);
                }
            } else {
                $array[$value->getAttribute()->getName()] = $value->getValue();
            }
        }

        return $array;
    }

    /**
     * @return Collection|Value[]
     */
    public function getSubresourceOf(): Collection
    {
        return $this->subresourceOf;
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
        $this->subresourceOf->removeElement($subresourceOf);

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
}
