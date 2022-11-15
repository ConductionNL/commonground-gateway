<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Exception\GatewayException;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use EasyRdf\Literal\Boolean;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An entity that functions a an object template for objects that might be stored in the EAV database.
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *     "get"={"path"="/admin/entities/{id}"},
 *     "get_sync"={
 *          "method"="GET",
 *          "path"="/admin/entities/{id}/sync"
 *      },
 *     "get_object"={
 *          "method"="GET",
 *          "path"="/admin/schemas/{id}/objects/{objectId}"
 *      },
 *     "put"={"path"="/admin/entities/{id}"},
 *     "put_object"={
 *          "method"="PUT",
 *          "read"=false,
 *          "validate"=false,
 *          "path"="/admin/schemas/{id}/objects/{objectId}"
 *      },
 *     "delete"={"path"="/admin/entities/{id}"},
 *     "delete_object"={
 *          "method"="DELETE",
 *          "path"="/admin/schemas/{id}/objects/{objectId}"
 *      }
 *  },
 *  collectionOperations={
 *     "get"={"path"="/admin/entities"},
 *     "get_objects"={
 *          "method"="GET",
 *          "path"="/admin/schemas/{id}/objects"
 *      },
 *     "post"={"path"="/admin/entities"},
 *     "post_objects"={
 *          "method"="POST",
 *          "read"=false,
 *          "validate"=false,
 *          "path"="/admin/schemas/{id}/objects"
 *      },
 *  })
 * @ORM\Entity(repositoryClass="App\Repository\EntityRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact"
 * })
 */
class Entity
{
    /**
     * @var UuidInterface The UUID identifier of this Entity.
     *
     * @Groups({"read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Gateway::class, fetch="EAGER")
     * @ORM\JoinColumn(nullable=true)
     * @MaxDepth(1)
     */
    private ?Gateway $gateway;

    /**
     * @var string The type of this Entity
     *
     * @Gedmo\Versioned
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $endpoint;

    /**
     * @Groups({"read","write"})
     * @ORM\OneToOne(targetEntity=Soap::class, fetch="EAGER", mappedBy="fromEntity")
     * @MaxDepth(1)
     */
    private ?soap $toSoap;

    /**
     * @ORM\OneToMany(targetEntity=Soap::class, mappedBy="toEntity", orphanRemoval=true)
     */
    private $fromSoap;

    /**
     * @var string The name of this Entity
     *
     * @Gedmo\Versioned
     * @Assert\Length(
     *     max = 255
     * )
     * @Assert\NotNull
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @var string The description of this Entity
     *
     * @Gedmo\Versioned
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @var string The function of this Entity. This is used for making specific entity types/functions work differently
     *
     * @example organization
     *
     * @Assert\Choice({"noFunction","organization", "person", "user", "userGroup", "processingLog"})
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", options={"default":"noFunction"})
     */
    private string $function = 'noFunction';

    /**
     * @var bool whether the properties of the original object are automatically include.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $extend = false;

    /**
     * Whether objects created from this entity should be available to child organisations.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $inherited = false;

    /**
     * The attributes of this Entity.
     *
     * @Groups({"read","write"})
     * @ORM\OneToMany(targetEntity=Attribute::class, mappedBy="entity", cascade={"persist", "remove"}, fetch="EAGER")
     * @MaxDepth(1)
     */
    private Collection $attributes;

    /**
     * @var Collection|null The attributes allowed to partial search on using the search query parameter.
     *
     * @Groups({"read","write"})
     * @ORM\OneToMany(targetEntity=Attribute::class, mappedBy="searchPartial", fetch="EAGER")
     * @MaxDepth(1)
     */
    private ?Collection $searchPartial;

    /**
     * @Groups({"write"})
     * @ORM\OneToMany(targetEntity=ObjectEntity::class, mappedBy="entity", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"dateCreated" = "DESC"})
     * @MaxDepth(1)
     */
    private Collection $objectEntities;

    /**
     * @Groups({"write"})
     * @ORM\OneToMany(targetEntity=Attribute::class, mappedBy="object", fetch="EXTRA_LAZY")
     * @MaxDepth(1)
     */
    private Collection $usedIn;

    /**
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $transformations = [];

    /**
     * @var string|null The route this entity can be found easier
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $route = null;

    /**
     * @var array|null The properties available for this entity (for all CRUD calls) if null all properties will be used. This affects which properties are written to / retrieved from external api's.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $availableProperties;

    /**
     * @var array|null The properties used for this entity (for all CRUD calls) if null all properties will be used. This affects which properties will be written / shown.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $usedProperties;

    /**
     * @ORM\OneToMany(targetEntity=GatewayResponseLog::class, mappedBy="entity", fetch="EXTRA_LAZY")
     */
    private $responseLogs;

    /**
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity=RequestLog::class, mappedBy="entity", fetch="EXTRA_LAZY", cascade={"remove"})
     */
    private Collection $requestLogs;

    /**
     * @var array Used for ConvertToGatewayService. Config to translate specific calls to a different method or endpoint. When changing the endpoint, if you want, you can use {id} to specify the location of the id in the endpoint.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $translationConfig = [];

    /**
     * @var array Used for ConvertToGatewayService. Config for getting the results out of a get collection on this endpoint (results and id are required!). "results" for where to find all items, "envelope" for where to find a single item in results, "id" for where to find the id of in a single item and "paginationPages" for where to find the total amount of pages or a reference to the last page (from root). (both envelope and id are from the root of results! So if id is in the envelope example: envelope = instance, id = instance.id)
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $collectionConfig = ['results' => 'hydra:member', 'id' => 'id', 'paginationPages' => 'hydra:view.hydra:last'];

    /**
     * @var array Used for ConvertToGatewayService. Config for getting the body out of a get item on this endpoint. "envelope" for where to find the body. example: envelope => result.instance
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $itemConfig = [];

    /**
     * @var array|null Used for ConvertToGatewayService. The mapping in from extern source to gateway.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $externMappingIn = [];

    /**
     * @var array|null Used for ConvertToGatewayService. The mapping out from gateway to extern source.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $externMappingOut = [];

    /**
     * @var array|null The handlers used for this entity.
     *
     * @MaxDepth(1)
     * @Groups({"write"})
     * @ORM\OneToMany(targetEntity=Handler::class, mappedBy="entity", fetch="EXTRA_LAZY")
     */
    private Collection $handlers;

    /**
     * @var array|null The subscribers used for this entity.
     *
     * @MaxDepth(1)
     * @Groups({"write"})
     * @ORM\OneToMany(targetEntity=Subscriber::class, mappedBy="entity", fetch="EXTRA_LAZY")
     */
    private Collection $subscribers;

    /**
     * @Groups({"write"})
     * @ORM\OneToMany(targetEntity=Subscriber::class, mappedBy="entityOut", fetch="EXTRA_LAZY")
     * @MaxDepth(1)
     */
    private Collection $subscriberOut;

    /**
     * @var ?Collection The collections of this Entity
     *
     * @Groups({"write"})
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=CollectionEntity::class, mappedBy="entities", fetch="EXTRA_LAZY")
     */
    private ?Collection $collections;

    /**
     * @var ?string The uri to a schema.org object
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true, options={"default":null})
     */
    private ?string $schema = null;

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

    public function __construct()
    {
        $this->attributes = new ArrayCollection();
        $this->searchPartial = new ArrayCollection();
        $this->objectEntities = new ArrayCollection();
        $this->usedIn = new ArrayCollection();
        $this->responseLogs = new ArrayCollection();
        $this->requestLogs = new ArrayCollection();
        $this->soap = new ArrayCollection();
        $this->handlers = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
        $this->subscriberOut = new ArrayCollection();
        $this->collections = new ArrayCollection();
    }

    public function export()
    {
        if ($this->getGateway() !== null) {
            $gateway = $this->getGateway()->getId()->toString();
            $gateway = '@'.$gateway;
        } else {
            $gateway = null;
        }

        $data = [
            'gateway'             => $gateway,
            'endpoint'            => $this->getEndpoint(),
            'name'                => $this->getName(),
            'description'         => $this->getDescription(),
            'extend'              => $this->getExtend(),
            'transformations'     => $this->getTransformations(),
            'route'               => $this->getRoute(),
            'availableProperties' => $this->getAvailableProperties(),
            'usedProperties'      => $this->getUsedProperties(),
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

    public function getId()
    {
        return $this->id;
    }

    public function getGateway(): ?Gateway
    {
        return $this->gateway;
    }

    public function setGateway(?Gateway $gateway): self
    {
        $this->gateway = $gateway;

        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getToSoap(): ?Soap
    {
        return $this->toSoap;
    }

    public function setToSoap(?Soap $toSoap): self
    {
        $this->toSoap = $toSoap;

        return $this;
    }

    /**
     * @return Collection|Soap[]
     */
    public function getFromSoap(): Collection
    {
        return $this->fromSoap;
    }

    public function addFromSoap(Soap $fromSoap): self
    {
        if (!$this->fromSoap->contains($fromSoap)) {
            $this->fromSoap[] = $fromSoap;
            $fromSoap->setToEntity($this);
        }

        return $this;
    }

    public function removeFromSoap(Soap $fromSoap): self
    {
        if ($this->fromSoap->removeElement($fromSoap)) {
            // set the owning side to null (unless already changed)
            if ($fromSoap->getToEntity() === $this) {
                $fromSoap->setToEntity(null);
            }
        }

        return $this;
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

    public function getFunction(): ?string
    {
        return $this->function;
    }

    public function setFunction(?string $function): self
    {
        $this->function = $function;

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
    public function getAttributeByName(string $name)
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

    public function getTransformations(): ?array
    {
        return $this->transformations;
    }

    public function setTransformations(array $transformations): self
    {
        $this->transformations = $transformations;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getAvailableProperties(): ?array
    {
        return $this->availableProperties;
    }

    public function setAvailableProperties(?array $availableProperties): self
    {
        $this->availableProperties = $availableProperties;

        return $this;
    }

    public function getUsedProperties(): ?array
    {
        return $this->usedProperties;
    }

    public function setUsedProperties(?array $usedProperties): self
    {
        $this->usedProperties = $usedProperties;

        return $this;
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
            $this->responseLogs[] = $responseLog;
            $responseLog->setEntity($this);
        }

        return $this;
    }

    public function removeResponseLog(GatewayResponseLog $responseLog): self
    {
        if ($this->responseLogs->removeElement($responseLog)) {
            // set the owning side to null (unless already changed)
            if ($responseLog->getEntity() === $this) {
                $responseLog->setEntity(null);
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
            $requestLog->setEntity($this);
        }

        return $this;
    }

    public function removeRequestLog(RequestLog $requestLog): self
    {
        if ($this->requestLogs->removeElement($requestLog)) {
            // set the owning side to null (unless already changed)
            if ($requestLog->getEntity() === $this) {
                $requestLog->setEntity(null);
            }
        }

        return $this;
    }

    public function getTranslationConfig(): ?array
    {
        return $this->translationConfig;
    }

    public function setTranslationConfig(?array $translationConfig): self
    {
        $this->translationConfig = $translationConfig;

        return $this;
    }

    public function getCollectionConfig(): ?array
    {
        return $this->collectionConfig;
    }

    public function setCollectionConfig(?array $collectionConfig): self
    {
        $this->collectionConfig = $collectionConfig;

        return $this;
    }

    public function getItemConfig(): ?array
    {
        return $this->itemConfig;
    }

    public function setItemConfig(?array $itemConfig): self
    {
        $this->itemConfig = $itemConfig;

        return $this;
    }

    public function getExternMappingIn(): ?array
    {
        return $this->externMappingIn;
    }

    public function setExternMappingIn(?array $externMappingIn): self
    {
        $this->externMappingIn = $externMappingIn;

        return $this;
    }

    public function getExternMappingOut(): ?array
    {
        return $this->externMappingOut;
    }

    public function setExternMappingOut(?array $externMappingOut): self
    {
        $this->externMappingOut = $externMappingOut;

        return $this;
    }

    /**
     * @return Collection|Soap[]
     */
    public function getSoap(): Collection
    {
        return $this->soap;
    }

    public function addSoap(Soap $soap): self
    {
        if (!$this->soap->contains($soap)) {
            $this->soap[] = $soap;
            $soap->setEntity($this);
        }

        return $this;
    }

    public function removeSoap(Soap $soap): self
    {
        if ($this->soap->removeElement($soap)) {
            // set the owning side to null (unless already changed)
            if ($soap->getEntity() === $this) {
                $soap->setEntity(null);
            }
        }

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
     * @return Collection|Handler[]
     */
    public function getHandlers(): Collection
    {
        return $this->handlers;
    }

    public function addHandler(Handler $handler): self
    {
        if (!$this->handlers->contains($handler)) {
            $this->handlers[] = $handler;
            $handler->setEntity($this);
        }

        return $this;
    }

    public function removeHandler(Handler $handler): self
    {
        if ($this->handlers->removeElement($handler)) {
            // set the owning side to null (unless already changed)
            if ($handler->getEntity() === $this) {
                $handler->setEntity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Subscriber[]
     */
    public function getSubscribers(): Collection
    {
        return $this->subscribers;
    }

    public function addSubscribers(Subscriber $subscriber): self
    {
        if (!$this->subscribers->contains($subscriber)) {
            $this->subscribers[] = $subscriber;
            $subscriber->setEntity($this);
        }

        return $this;
    }

    public function removeSubscribers(Subscriber $subscriber): self
    {
        if ($this->subscribers->removeElement($subscriber)) {
            // set the owning side to null (unless already changed)
            if ($subscriber->getEntity() === $this) {
                $subscriber->setEntity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Subscriber[]
     */
    public function getSubscriberOut(): Collection
    {
        return $this->subscriberOut;
    }

    public function addSubscriberOut(Subscriber $subscriberOut): self
    {
        if (!$this->subscriberOut->contains($subscriberOut)) {
            $this->subscriberOut[] = $subscriberOut;
            $subscriberOut->setEntityOut($this);
        }

        return $this;
    }

    public function removeSubscriberOut(Subscriber $subscriberOut): self
    {
        if ($this->subscriberOut->removeElement($subscriberOut)) {
            // set the owning side to null (unless already changed)
            if ($subscriberOut->getEntityOut() === $this) {
                $subscriberOut->setEntityOut(null);
            }
        }

        return $this;
    }

    public function setSubscriberOut(?Subscriber $subscriberOut): self
    {
        // unset the owning side of the relation if necessary
        if ($subscriberOut === null && $this->subscriberOut !== null) {
            $this->subscriberOut->setEntityOut(null);
        }

        // set the owning side of the relation if necessary
        if ($subscriberOut !== null && $subscriberOut->getEntityOut() !== $this) {
            $subscriberOut->setEntityOut($this);
        }

        $this->subscriberOut = $subscriberOut;

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

    public function getSchema(): ?string
    {
        return $this->schema;
    }

    public function setSchema(?string $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @throws GatewayException
     */
    public function toSchema(?ObjectEntity $objectEntity): array
    {
        $schema = [
            '$id'          => 'https://example.com/person.schema.json', //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'      => 'https://json-schema.org/draft/2020-12/schema',
            'title'        => $this->getName(),
            'required'     => [],
            'properties'   => [],
        ];

        if ($objectEntity && $objectEntity->getEntity() !== $this) {
            throw new GatewayException('The given objectEntity has not have the same entity as this entity');
        }

        foreach ($this->getAttributes() as $attribute) {
            // Zetten van required
            if ($attribute->getRequired()) {
                $schema['required'][] = $attribute->getName();
            }

            $property = [];

            // Aanmaken property
            // @todo ik laad dit nu in als array maar eigenlijk wil je testen en alleen zetten als er waardes in zitten

            $attribute->getType() && $property['type'] = $attribute->getType();
            $attribute->getFormat() && $property['format'] = $attribute->getFormat();
            $attribute->getDescription() && $property['description'] = $attribute->getDescription();
            $attribute->getExample() && $property['example'] = $attribute->getExample();

            // What if we have an $object entity
            if ($objectEntity) {
                $property['value'] = $objectEntity->getValue($attribute);
            }

            // Zetten van de property
            $schema['properties'][$attribute->getName()] = $property;

            // Add the validators
            foreach ($attribute->getValidations() as $validator => $validation) {
                if (!array_key_exists($validator, Entity::SUPPORTED_VALIDATORS) && $validation != null) {
                    $schema['properties'][$attribute->getName()][$validator] = $validation;
                }
            }
        }

        return $schema;
    }
}
