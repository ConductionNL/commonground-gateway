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
use Doctrine\ORM\Mapping as ORM;
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
 *              }
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

    public function __construct()
    {
        $this->objectValues = new ArrayCollection();
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
        return $this->subresourceOf;
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

    public function setHasErrors(bool $hasErrors): self
    {
        $this->hasErrors = $hasErrors;

        // Do the same for resources above this one if set to true
        if ($hasErrors == true && $this->getSubresourceOf()) {
            $this->getSubresourceOf()->getObjectEntity()->setHasErrors($hasErrors);
        }
        // Do the same for resources under this one if set to false
        /* @todo wilco de opdracht was upward waarom downward? kost snelheid is er een argument?
        /*
        elseif ($hasErrors == false) {
            $subResources = $this->getSubresources();
            foreach ($subResources as $subResource) {
                if (get_class($subResource) == ObjectEntity::class) {
                    $subResource->setHasErrors($hasErrors);
                    continue;
                }
                // If a subresource is a list of subresources (example cc/person->cc/emails)
                foreach ($subResource as $listSubResource) {
                    $listSubResource->setHasErrors($hasErrors);
                }
            }
        }
        */

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
        /* @todo gelaagd teruggeven */
        $allErrors = [];
        $subResources = $this->getSubresources();
        foreach ($subResources as $subresource) {
            if (!$subresource) continue; // can be null because of subresource/object fields being set to null
            if (get_class($subresource) == ObjectEntity::class) {
                if ($subresource->hasErrors) {
                    $allErrors = $subresource->getAllErrors();
                }
                continue;
            }
            // If a subresource is a list of subresources (example cc/person->cc/emails)
            foreach ($subresource as $listSubresource) {
                if ($listSubresource->hasErrors) {
                    $allErrors = array_merge($allErrors, $listSubresource->getAllErrors());
                }
            }
        }
        return array_merge($allErrors, $this->getErrors());
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


    public function getValueByAttribute(Attribute $attribute): Value
    {
        // Check if value with this attribute exists for this ObjectEntity
        /* @todo dit gaat nu fout op put uiteraard */
        /*
        $value = $this->getObjectValues()->filter(function (Value $value) use ($attribute) {
            return $value->getAttribute() === $attribute;
        });

        if (count($value) > 0) {
            return $value[0];
        }
        */

        // If no value with this attribute was found
        $value = new Value();
        $value->setAttribute($attribute);
        $value->setObjectEntity($this);
        $this->addObjectValue($value);

        return $value;
    }

    public function getSubresources()
    {
        // Get all values of this ObjectEntity with attribute type object
        $values = $this->getObjectValues()->filter(function (Value $value) {
            return $value->getAttribute()->getType() === 'object';
        });

        $subresources = new ArrayCollection();
        foreach ($values as $value) {
            $subresource = $value->getValue();
            $subresources->add($subresource);
        }
        return $subresources;
    }
}
