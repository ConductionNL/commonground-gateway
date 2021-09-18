<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Description
 *
 * @category Entity
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *          "get",
 *          "put",
 *          "delete",
 *          "get_eav_object"={
 *              "method"="GET",
 *              "path"="/eav/data/{entity}/{id}",
 *              "swagger_context" = {
 *                  "summary"="Get object with objectEntity id",
 *               "description"="Returns the object"
 *              }
 *          },
 *          "put_eav_object"={
 *              "method"="PUT",
 *              "path"="/eav/data/{entity}/{id}",
 *              "swagger_context" = {
 *                  "summary"="Put object",
 *                  "description"="Returns the updated object"
 *              }
 *          },
 *          "delete_eav_object"={
 *              "method"="DELETE",
 *              "path"="/eav/data/{entity}/{id}",
 *              "swagger_context" = {
 *                  "summary"="delete object",
 *                  "description"="Returns the updated object"
 *              },
 *              "read"  = false,
 *              "controller" = "App\Controller\EavController::deleteAction"
 *          },
 *     },
 *  collectionOperations={
 *      "get",
 *      "post",
 *      "get_eav_objects"={
 *          "method"="GET",
 *          "path"="/eav/data/{entity}",
 *          "swagger_context" = {
 *              "summary"="Get object with objectEntity uri",
 *              "description"="Returns the object"
 *          }
 *      },
 *      "post_eav_objects"={
 *          "method"="POST",
 *          "path"="/eav/data/{entity}",
 *          "swagger_context" = {
 *              "summary"="Post object",
 *              "description"="Returns the created object"
 *          }
 *      },
*          "get_eav_object"={
*              "method"="GET",
*              "path"="/eav/data/{entity}/{id}",
*              "swagger_context" = {
*                  "summary"="Get object with objectEntity id",
*               "description"="Returns the object"
*              }
*          },
 *  })
 * @ORM\Entity(repositoryClass="App\Repository\ObjectEntityRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "uri": "ipartial",
 *     "entity.type": "iexact"
 * })
 */
class ObjectEntity
{
    /**
     * @var UuidInterface UUID of this person
     *
     * @Groups({"read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

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
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="objectEntities", fetch="EAGER")
     * @MaxDepth(1)
     */
    private ?Entity $entity = null;

    /**
     * @Groups({"read", "write"})
     * @ORM\OneToMany(targetEntity=Value::class, mappedBy="objectEntity", cascade={"all"})
     * @MaxDepth(1)
     */
    private $objectValues;

    /**
     * @Groups({"read", "write"})
     * @ORM\ManyToOne(targetEntity=Value::class, fetch="EAGER", inversedBy="objects", cascade={"persist"})
     *
     * @JoinColumn(onDelete="CASCADE")
     * @ORM\JoinColumn(nullable=true)
     * @MaxDepth(1)
     */
    private ?Value $subresourceOf = null;

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
     * @ORM\OneToMany(targetEntity=GatewayResponceLog::class, mappedBy="objectEntity", fetch="EXTRA_LAZY")
     */
    private $responceLogs;

    public function __construct()
    {
        $this->objectValues = new ArrayCollection();
        $this->responceLogs = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId(Uuid $id): self
    {
        $this->id = $id;

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
            $this->objectValues[] = $objectValue;
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

    public function getSubresourceOf(): ?Value
    {
        // Lets prevent upwards recursion
        if(! $this->getSubresources()->contains($this->subresourceOf)){
            return $this->subresourceOf;
        }
        return null;
    }

    public function setSubresourceOf(?Value $subresourceOf): self
    {
        $this->subresourceOf = $subresourceOf;

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
        if ($hasErrors == true && $this->getSubresourceOf() && $level <0 5 && !$this->getSubresources()->contains($this->getSubresourceOf())) {
            $this->getSubresourceOf()->getObjectEntity()->setHasErrors($hasErrors, $level + 1);
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

    public function addError(string $attributeName, string $error): array
    {
        if (!$this->hasErrors) {
            $this->setHasErrors(true);
        }

        //TODO: check if error is already in array?
        $this->errors[$attributeName] = $error;

        return $this->errors;
    }

    public function getAllErrors(): ?array
    {
        $allErrors = $this->getErrors();
        $subResources = $this->getSubresources();
        foreach ($subResources as $subresource) {
            if (!$subresource) continue; // can be null because of subresource/object fields being set to null
            if (get_class($subresource) == ObjectEntity::class) {
                if ($subresource->getHasErrors()) {
                    $allErrors[$subresource->getSubresourceOf()->getAttribute()->getName()] = $subresource->getAllErrors();
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
        return $allErrors;
    }


    public function getHasPromises(): bool
    {
        return $this->hasErrors;
    }

    public function setHasPromises(bool $hasPromises): self
    {
        $this->hasPromises = $hasPromises;

        // Do the same for resources above this one if set to true
        if ($hasPromises == true && $this->getSubresourceOf()) {
            $this->getSubresourceOf()->getObjectEntity()->setHasPromises($hasPromises);
        }

        return $this;
    }

    public function getPromises(): ?array
    {
        return $this->promises;
    }

    public function setPromises(?array $promises): self
    {
        if(!$this->hasPromises) {
            $this->setHasPromises(true);
        }

        $this->promises = $promises;

        return $this;
    }

    public function addPromise(object $promise): array
    {
        //TODO: check if promise is already in array?
        $this->promises[] = $promise;

        if(!$this->hasPromises) {
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
     * Get an value based on a attribut
     *
     * @param Attribute $attribute the attribute that you are searching for
     * @return Value Iether the current value for this attribute or a new value for the attribute if there isnt a current value
     *
     */
    public function getValueByAttribute(Attribute $attribute): Value
    {
        // Check if value with this attribute exists for this ObjectEntity
        $criteria = Criteria::create()->andWhere(Criteria::expr()->eq('attribute', $attribute))->setMaxResults(1);

        $values = $this->getObjectValues()->matching($criteria);

        if($values->isEmpty()){
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
    public function getAllSubresources(?ArrayCollection $result):ArrayCollection
    {
        $subresources = $this->getSubresources($result);

        foreach ($subresources as $subresource){
            if(!$result->contains($subresource)){
                $result->add($subresource);
            }
        }

        return $result;
    }

    public function getSubresources(?ArrayCollection $result): ArrayCollection
    {
        // Get all values of this ObjectEntity with attribute type object
        $values = $this->getObjectValues()->filter(function (Value $value) {
            return $value->getAttribute()->getType() === 'object';
        });

        $subresources = new ArrayCollection();
        foreach ($values as $value) {
            $subresource = $value->getValue();

            // We do not want to nest a parent object .... To prevent recursion
            if(!$subresource == $this->getSubresourceOf()){
                $subresources->add($subresource);
            }
        }
        return $subresources;
    }

    /**
     * @return Collection|GatewayResponceLog[]
     */
    public function getResponceLogs(): Collection
    {
        return $this->responceLogs;
    }

    public function addResponceLog(GatewayResponceLog $responceLog): self
    {
        if (!$this->responceLogs->contains($responceLog)) {
            $this->responceLogs[] = $responceLog;
            $responceLog->setObjectEntity($this);
        }

        return $this;
    }

    public function removeResponceLog(GatewayResponceLog $responceLog): self
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
     * Checks conditional logic on values
     *
     * @return $this
     */
    public function checkConditionlLogic(): self
    {
        // lets cascade
        foreach($this->getSubresources() as $subresource){
            $subresource->checkConditionlLogic();
        }

        /* @todo we should only check values that actuale have conditional logic optmimalisation */
        // do the actual chack
        foreach($this->getObjectValues() as $value){
            if(empty($value->getAttribute()->getRequiredIf())){
                continue;
            }
            // Oke loop the conditions
            foreach($value->getAttribute()->getRequiredIf() as $conditionProperty=>$conditionValue){
                // we only have a problem if the current value is empty
                if($value->getValue()){continue;}
                // so lets see if we should have a value
                if($this->getValueByAttribute($this->getEntity()->getAttributeByName($conditionProperty))->getValue() == $conditionValue){
                    $this->addError($value->getAttribute()->getName(), 'Is required becouse property '.$conditionProperty.' has the value: '.$conditionValue);
                }
            }
        }


        return $this;
    }

}
