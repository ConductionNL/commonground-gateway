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
use EasyRdf\Literal\Boolean;
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
 *      "get"={"path"="/admin/entities/{id}"},
 *      "put"={"path"="/admin/entities/{id}"},
 *      "delete"={"path"="/admin/entities/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/entities"},
 *      "post"={"path"="/admin/entities"}
 *  })
 * @ORM\Entity(repositoryClass="App\Repository\EntityRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "type": "exact"
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
     * @Assert\Choice({"organization", "user", "userGroup"})
     * @Groups({"read", "write"})
     * @ORM\Column(type="string")
     */
    private $function = 'string';

    /**
     * wheter or not the properties of the original object are automaticly include.
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
     * @Groups({"read","write"})
     * @ORM\OneToMany(targetEntity=Attribute::class, mappedBy="entity", cascade={"persist", "remove"}, fetch="EAGER")
     * @MaxDepth(1)
     */
    private Collection $attributes;

    /**
     * @Groups({"write"})
     * @ORM\OneToMany(targetEntity=ObjectEntity::class, mappedBy="entity", cascade={"remove"})
     * @MaxDepth(1)
     */
    private Collection $objectEntities;

    /**
     * @Groups({"write"})
     * @ORM\OneToMany(targetEntity=Attribute::class, mappedBy="object")
     * @MaxDepth(1)
     */
    private Collection $usedIn;

    /**
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $transformations = [];

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
     * @var array Config to translate specific calls to a different method or endpoint. When changing the endpoint, if you want, you can use {id} to specify the location of the id in the endpoint.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $translationConfig = [];

    /**
     * @var array Config for getting the results out of a get collection on this endpoint (results and id are required!). "results" for where to find all items, "envelope" for where to find a single item in results, "id" for where to find the id of in a single item and "paginationNext" for where to find the next page if pagination (from root). (both envelope and id are from the root of results! So if id is in the envelope example: envelope = instance, id = instance.id)
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $collectionConfig = ['results' => 'hydra:member', 'id' => 'id', 'paginationNext' => 'hydra:view.hydra:next'];

    /**
     * @var array|null The handlers used for this entity.
     *
     * @MaxDepth(1)
     * @Groups({"read", "write"})
     * @ORM\OneToMany(targetEntity=Handler::class, mappedBy="entity")
     */
    private Collection $handlers;

    /**
     * @var array|null The subscribers used for this entity.
     *
     * @MaxDepth(1)
     * @Groups({"read", "write"})
     * @ORM\OneToMany(targetEntity=Subscriber::class, mappedBy="entity")
     */
    private Collection $subscribers;

    public function __construct()
    {
        $this->attributes = new ArrayCollection();
        $this->objectEntities = new ArrayCollection();
        $this->usedIn = new ArrayCollection();
        $this->responseLogs = new ArrayCollection();
        $this->requestLogs = new ArrayCollection();
        $this->soap = new ArrayCollection();
        $this->handlers = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
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
        // lets make sure this name is slugable
        $name = trim($name); //removes whitespace at begin and ending
        $name = preg_replace('/\s+/', '_', $name); // replaces other whitespaces with _
        $name = strtolower($name);

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

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(\DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateModified(): ?\DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(\DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

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
}
