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
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about an Endpoint.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/endpoints/{id}"},
 *      "put"={"path"="/admin/endpoints/{id}"},
 *      "delete"={"path"="/admin/endpoints/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/endpoints"},
 *      "post"={"path"="/admin/endpoints"}
 *  })
 * )
 * @ORM\Entity(repositoryClass="App\Repository\EndpointRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact",
 *     "operationType": "exact",
 *     "pathRegex": "ipartial"
 * })
 */
class Endpoint
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     * @Groups({"read","read_secure"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var string The name of this Endpoint.
     *
     * @Gedmo\Versioned
     * @Assert\Length(
     *     max = 255
     * )
     * @Assert\NotNull
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @var string|null A description of this Endpoint.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="text", nullable=true, options={"default":null})
     */
    private ?string $description = null;

    /**
     * @var string|null A regex description of this path.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", nullable=true, options={"default":null})
     */
    private ?string $pathRegex = null;

    /**
     * @var string|null The method.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", nullable=true, options={"default":null})
     */
    private ?string $method = null;

    /**
     * @var string|null The (OAS) tag of this Endpoint.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", nullable=true, options={"default":null})
     */
    private ?string $tag = null;

    // @TODO remove totally?
    // /**
    //  * @var string The type of this Endpoint.
    //  *
    //  * @Assert\NotNull
    //  * @Assert\Choice({"gateway-endpoint", "entity-route", "entity-endpoint", "documentation-endpoint"})
    //  *
    //  * @Groups({"read", "write"})
    //  * @ORM\Column(type="string")
    //  */
    // private string $type;

    /**
     * @var array|null The path of this Endpoint.
     *
     * @Assert\NotNull
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array")
     */
    private ?array $path;

    /**
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity=RequestLog::class, mappedBy="endpoint", fetch="EXTRA_LAZY", cascade={"remove"})
     */
    private Collection $requestLogs;

    /**
     * @var array Everything we do *not* want to log when logging errors on this endpoint, defaults to only the authorization header. See the entity RequestLog for the possible options. For headers an array of headers can be given, if you only want to filter out specific headers.
     *
     * @example ["statusCode", "status", "headers" => ["authorization", "accept"]]
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $loggingConfig = ['headers' => ['authorization']];

    /**
     * @Groups({"read", "write"})
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=Application::class, mappedBy="endpoints")
     */
    private $applications;

    /**
     * @Groups({"read", "write"})
     * @ORM\OneToOne(targetEntity=Subscriber::class, mappedBy="endpoint", cascade={"persist", "remove"})
     * @MaxDepth(1)
     */
    private ?Subscriber $subscriber;

    /**
     * @var ?Collection The collections of this Endpoint
     *
     * @Groups({"read", "write"})
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=CollectionEntity::class, mappedBy="endpoints")
     */
    private ?Collection $collections;

    /**
     * @var ?string The operation type calls must be that are requested through this Endpoint
     *
     * @Assert\Choice({"item", "collection"})
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $operationType;

    /**
     * @var ?array (OAS) tags to identify this Endpoint
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $tags = [];

    /**
     * @var ?string Array of the path if this Endpoint has parameters and/or subpaths
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $pathArray = [];

    /**
     * @var ?array needs to be refined
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $methods = [];

    /**
     * @var ?array needs to be refined
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $throws = [];

    /**
     * @var ?bool needs to be refined
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $status = null;

    /**
     * @var Collection|null Properties of this Endpoint
     *
     * @MaxDepth(1)
     * @Groups({"read", "write"})
     * @ORM\OneToMany(targetEntity=Property::class, mappedBy="endpoint")
     */
    private ?Collection $properties;

    /**
     * @var Collection|null Handlers of this Endpoint
     *
     * @MaxDepth(1)
     * @Groups({"read", "write"})
     * @ORM\ManyToMany(targetEntity=Handler::class, mappedBy="endpoints")
     */
    private ?Collection $handlers;

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

    /**
     * @var string|null The default content type of the endpoint
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $defaultContentType = 'application/json';

    /**
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="endpoints")
     */
    private $Entity;

    /**
     * @ORM\ManyToMany(targetEntity=Entity::class, inversedBy="endpoints")
     */
    private $entities;

    public function __construct(?Entity $entity = null)
    {
        $this->requestLogs = new ArrayCollection();
        $this->handlers = new ArrayCollection();
        $this->applications = new ArrayCollection();
        $this->collections = new ArrayCollection();
        $this->properties = new ArrayCollection();

        // Create simple endpoints for entities
        if ($entity) {
            $this->setEntity($entity);
            $this->setName($entity->getName());
            $this->setDescription($entity->getDescription());
            $this->setMethods(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            $this->setPathRegex('^'.mb_strtolower(str_replace(' ', '_', $entity->getName())).'/?([a-z0-9-]+)?$');
            $this->setPath([1=>'id']);

            /*@depricated kept here for lagacy */
            $this->setOperationType('GET');
        }
        $this->entities = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): self
    {
        $this->method = $method;

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

    public function getPathRegex(): ?string
    {
        return $this->pathRegex;
    }

    public function setPathRegex(?string $pathRegex): self
    {
        $this->pathRegex = $pathRegex;

        return $this;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    // public function getType(): ?string
    // {
    //     return $this->type;
    // }

    // public function setType(string $type): self
    // {
    //     $this->type = $type;

    //     return $this;
    // }

    public function getPath(): ?array
    {
        return $this->path;
    }

    public function setPath(array $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getMethods(): ?array
    {
        return $this->methods;
    }

    public function setMethods(?array $methods): self
    {
        $this->methods = $methods;

        return $this;
    }

    public function getThrows(): ?array
    {
        return $this->throws;
    }

    public function setThrows(?array $throws): self
    {
        $this->throws = $throws;

        return $this;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(?bool $status): self
    {
        $this->status = $status;

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
            $requestLog->setEndpoint($this);
        }

        return $this;
    }

    public function removeRequestLog(RequestLog $requestLog): self
    {
        if ($this->requestLogs->removeElement($requestLog)) {
            // set the owning side to null (unless already changed)
            if ($requestLog->getEndpoint() === $this) {
                $requestLog->setEndpoint(null);
            }
        }

        return $this;
    }

    public function getLoggingConfig(): ?array
    {
        return $this->loggingConfig;
    }

    public function setLoggingConfig(array $loggingConfig): self
    {
        $this->loggingConfig = $loggingConfig;

        return $this;
    }

    /**
     * @return Collection|Application[]
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function addApplication(Application $application): self
    {
        if (!$this->applications->contains($application)) {
            $this->applications[] = $application;
            $application->addEndpoint($this);
        }

        return $this;
    }

    public function removeApplication(Application $application): self
    {
        if ($this->applications->removeElement($application)) {
            $application->removeEndpoint($this);
        }

        return $this;
    }

    public function getSubscriber(): ?Subscriber
    {
        return $this->subscriber;
    }

    public function setSubscriber(?Subscriber $subscriber): self
    {
        // unset the owning side of the relation if necessary
        if ($subscriber === null && $this->subscriber !== null) {
            $this->subscriber->setEndpoint(null);
        }

        // set the owning side of the relation if necessary
        if ($subscriber !== null && $subscriber->getEndpoint() !== $this) {
            $subscriber->setEndpoint($this);
        }

        $this->subscriber = $subscriber;

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
            $collection->addEndpoint($this);
        }

        return $this;
    }

    public function removeCollection(CollectionEntity $collection): self
    {
        if ($this->collections->removeElement($collection)) {
            $collection->removeEndpoint($this);
        }

        return $this;
    }

    public function getOperationType(): ?string
    {
        return $this->operationType;
    }

    public function setOperationType(string $operationType): self
    {
        $this->operationType = $operationType;

        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function getPathArray(): ?array
    {
        return $this->pathArray;
    }

    public function setPathArray(?array $pathArray): self
    {
        $this->pathArray = $pathArray;

        return $this;
    }

    /**
     * @return Collection|Property[]
     */
    public function getProperties(): Collection
    {
        return $this->properties;
    }

    public function addProperty(Property $property): self
    {
        if (!$this->properties->contains($property)) {
            $this->properties[] = $property;
            $property->setEndpoint($this);
        }

        return $this;
    }

    public function removeProperty(Property $property): self
    {
        if ($this->properties->removeElement($property)) {
            // set the owning side to null (unless already changed)
            if ($property->getEndpoint() === $this) {
                $property->setEndpoint(null);
            }
        }

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
            $handler->addEndpoint($this);
        }

        return $this;
    }

    public function removeHandler(Handler $handler): self
    {
        if ($this->handlers->removeElement($handler)) {
            $handler->removeEndpoint($this);
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

    public function getDefaultContentType(): ?string
    {
        return $this->defaultContentType;
    }

    public function setDefaultContentType(?string $defaultContentType): self
    {
        $this->defaultContentType = $defaultContentType;

        return $this;
    }

    public function getEntity(): ?Entity
    {
        return $this->Entity;
    }

    public function setEntity(?Entity $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * @return Collection|Entity[]
     */
    public function getEntities(): Collection
    {
        return $this->entities;
    }

    public function addEntity(Entity $entity): self
    {
        if (!$this->entities->contains($entity)) {
            $this->entities[] = $entity;
        }

        return $this;
    }

    public function removeEntity(Entity $entity): self
    {
        $this->entities->removeElement($entity);

        return $this;
    }
}
