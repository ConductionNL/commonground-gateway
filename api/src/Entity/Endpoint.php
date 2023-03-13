<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Gateway as Source;
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
 *
 * @ORM\Entity(repositoryClass="App\Repository\EndpointRepository")
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact",
 *     "operationType": "exact",
 *     "pathRegex": "ipartial",
 *     "entities.id": "exact"
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
     *
     * @Groups({"read","read_secure"})
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
     * @var string The name of this Endpoint.
     *
     * @Gedmo\Versioned
     *
     * @Assert\Length(
     *     max = 255
     * )
     *
     * @Assert\NotNull
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @var string|null A description of this Endpoint.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="text", nullable=true, options={"default":null})
     */
    private ?string $description = null;

    /**
     * @var string|null A regex description of this path.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", nullable=true, options={"default":null})
     */
    private ?string $pathRegex = null;

    /**
     * @var string|null The method.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", nullable=true, options={"default":null})
     */
    private ?string $method = null;

    /**
     * @var string|null The (OAS) tag of this Endpoint.
     *
     * @Groups({"read", "write"})
     *
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
     *
     * @ORM\Column(type="array")
     */
    private ?array $path = [];

    /**
     * @var array Everything we do *not* want to log when logging errors on this endpoint, defaults to only the authorization header. See the entity RequestLog for the possible options. For headers an array of headers can be given, if you only want to filter out specific headers.
     *
     * @example ["statusCode", "status", "headers" => ["authorization", "accept"]]
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private array $loggingConfig = ['headers' => ['authorization']];

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=Application::class, mappedBy="endpoints")
     */
    private $applications;

    /**
     * @var ?Collection The collections of this Endpoint
     *
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToMany(targetEntity=CollectionEntity::class, mappedBy="endpoints")
     *
     * @ORM\OrderBy({"dateCreated" = "DESC"})
     */
    private ?Collection $collections;

    /**
     * @var ?string The operation type calls must be that are requested through this Endpoint
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default": null})
     */
    private ?string $operationType = null;

    /**
     * @var ?array (OAS) tags to identify this Endpoint
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $tags = [];

    /**
     * @var ?string Array of the path if this Endpoint has parameters and/or subpaths
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $pathArray = [];

    /**
     * @var ?array needs to be refined
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $methods = [];

    /**
     * @var ?array needs to be refined
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $throws = [];

    /**
     * @var ?bool needs to be refined
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $status = null;

    /**
     * @var Collection|null Properties of this Endpoint
     *
     * @MaxDepth(1)
     *
     * @Groups({"read", "write"})
     *
     * @ORM\OneToMany(targetEntity=Property::class, mappedBy="endpoint")
     */
    private ?Collection $properties;

    /**
     * @var Collection|null Handlers of this Endpoint
     *
     * @MaxDepth(1)
     *
     * @Groups({"read", "write"})
     *
     * @ORM\ManyToMany(targetEntity=Handler::class, mappedBy="endpoints")
     */
    private ?Collection $handlers;

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
     * @var string|null The default content type of the endpoint
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $defaultContentType = 'application/json';

    /**
     * @deprecated
     *
     * @ORM\ManyToOne(targetEntity=Entity::class)
     */
    private $entity;

    /**
     * The Entities of this Endpoint.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\ManyToMany(targetEntity=Entity::class, inversedBy="endpoints")
     */
    private $entities;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\ManyToOne(targetEntity=Gateway::class, inversedBy="proxies")
     */
    private $proxy;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $reference = null;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $version = null;

    /**
     * Constructor for creating an Endpoint. Use $entity to create an Endpoint for an Entity or
     * use $source to create an Endpoint for a source, a proxy Endpoint.
     *
     * @param Entity|null  $entity        An entity to create an Endpoint for.
     * @param Gateway|null $source        A source to create an Endpoint for. Will only work if $entity = null.
     * @param array|null   $configuration A configuration array used to correctly create an Endpoint. The following keys are supported:
     *                                    'path' => a path can be used to set the Path and PathRegex for this Endpoint. Default = $entity->getName() or $source->getName().
     *                                    'methods' => the allowed methods for this Endpoint, default = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
     */
    public function __construct(?Entity $entity = null, ?Source $source = null, ?array $configuration = [])
    {
        $this->handlers = new ArrayCollection();
        $this->applications = new ArrayCollection();
        $this->collections = new ArrayCollection();
        $this->properties = new ArrayCollection();
        $this->entities = new ArrayCollection();

        if (!$entity && !$source) {
            return;
        }

        // Create simple endpoint(s) for entity
        if ($entity) {
            $default = $this->constructEntityEndpoint($entity);
        }
        // Create simple endpoint(s) for source (proxy)
        else {
            $default = $this->constructProxyEndpoint($source);
        }

        if ($configuration) {
            $this->fromSchema($configuration, $default);
        }
    }

    /**
     * Uses given $configuration array to set the properties of this Endpoint.
     * $configuration or $default array must contain the key 'path'!
     * And either $configuration array must contain the key 'pathRegex' or the $default array must contain the key 'pathRegexEnd'.
     *
     * @param array $schema  The schema to load.
     * @param array $default An array with data. The default values used for setting properties. Can contain the following keys:
     *                       'path' => If $configuration array has no key 'path' this value is used to set the Path. (and pathRegex if $configuration has no 'pathRegex' key)
     *                       'pathRegexEnd' => A string added to the end of the pathRegex.
     *                       'pathArrayEnd' => The final item in the path array, 'id' for an Entity Endpoint and {route} for a proxy Endpoint.
     *
     * @return void
     */
    public function fromSchema(array $schema, array $default = [])
    {
        // Basic stuff
        if (array_key_exists('$id', $schema)) {
            $this->setReference($schema['$id']);
        }
        if (array_key_exists('version', $schema)) {
            $this->setVersion($schema['version']);
        }

        // Lets make a path & add prefix to this path if it is needed.
        $path = array_key_exists('path', $schema) ? $schema['path'] : $default['path'];

        // Make sure we never have a starting / for PathRegex.
        // todo: make sure all bundles create endpoints with a path that does not start with a slash!
        $path = ltrim($path, '/');

        $entity = (array_key_exists('entities', $schema) && is_array($schema['entities']) && !empty($schema['entities']))
            ? $schema['entities'][0] : ($this->entities->first() ?? $this->entity);

        $criteria = Criteria::create()->orderBy(['date_created' => Criteria::DESC]);
        if ($entity instanceof Entity && !$entity->getCollections()->isEmpty() &&
            $entity->getCollections()->matching($criteria)->first()->getPrefix()) {
            $path = $entity->getCollections()->matching($criteria)->first()->getPrefix().'/'.$path;
        }

        // Set the pathRegex
        $pathRegex = array_key_exists('pathRegex', $schema) ? $schema['pathRegex'] : "^$path/{$default['pathRegexEnd']}$";
        $this->setPathRegex($pathRegex);

        // Create Path array (add default pathArrayEnd to this, different depending on if we create en Endpoint for $entity or $source.)
        $explodedPath = explode('/', $path);
        array_key_exists('pathArrayEnd', $default) && $explodedPath[] = $default['pathArrayEnd'];
        $this->setPath($explodedPath);
        $this->setMethods(array_key_exists('methods', $schema) && $schema['methods'] ? $schema['methods'] : ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

        array_key_exists('title', $schema) ? $this->setName($schema['title']) :
            (array_key_exists('name', $schema) ? $this->setName($schema['name']) : '');
        array_key_exists('description', $schema) ? $this->setDescription($schema['description']) : '';
        // etc^...

        /*@depricated kept here for lagacy */
        $this->setMethod(array_key_exists('method', $schema) ? $schema['method'] : 'GET');
        $this->setOperationType(array_key_exists('operationType', $schema) ? $schema['operationType'] : 'GET');

        $this->setThrows($schema['throws'] ?? []);
    }

    /**
     * Convert this Gateway to a schema.
     *
     * @return array Schema array.
     */
    public function toSchema(): array
    {
        $entities = [];
        foreach ($this->entities as $entity) {
            $entities[] = $entity->toSchema();
        }

        return [
            '$id'                            => $this->getReference(), //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'                        => 'https://docs.commongateway.nl/schemas/Endpoint.schema.json',
            'title'                          => $this->getName(),
            'description'                    => $this->getDescription(),
            'version'                        => $this->getVersion(),
            'name'                           => $this->getName(),
            'pathRegex'                      => $this->getPathRegex(),
            'path'                           => $this->getPath(),
            'methods'                        => $this->getMethods(),
            'method'                         => $this->getMethod(),
            'throws'                         => $this->getThrows(),
            'tag'                            => $this->getTag(),
            'tags'                           => $this->getTags(),
            'proxy'                          => $this->getProxy()->toSchema(),
            'entities'                       => $entities,
        ];
    }

    public function __toString()
    {
        return $this->getName();
    }

    /**
     * Use the given Entity data to set some values during constructor when creating an Endpoint for an Entity.
     *
     * @param Entity $entity The Entity
     *
     * @return array Returns default values for path and pathRegex if we are creating and Endpoint for an Entity.
     */
    private function constructEntityEndpoint(Entity $entity): array
    {
        $this->addEntity($entity);
        $this->setEntity($entity);
        $this->setName($entity->getName());
        $this->setDescription($entity->getDescription());

        // Default path, pathArray(end) & pathRegex(end) for $entity
        return [
            'path'         => mb_strtolower(str_replace(' ', '_', $entity->getName())),
            'pathArrayEnd' => 'id',
            'pathRegexEnd' => '?([a-z0-9-]+)?',
        ];
    }

    /**
     * Use the given Source data to set some values during constructor when creating an Endpoint for a Source (a proxy Endpoint).
     *
     * @param Source $source The Source
     *
     * @return array Returns default values for path and pathRegex if we are creating and Endpoint for a Source.
     */
    private function constructProxyEndpoint(Source $source): array
    {
        $this->setProxy($source);
        $this->setName("{$source->getName()} proxy endpoint");
        $this->setDescription($source->getDescription());

        // Default path, pathArray(end) & pathRegex(end) for $source
        return [
            'path'         => mb_strtolower(str_replace(' ', '_', $source->getName())),
            'pathArrayEnd' => '{route}',
            'pathRegexEnd' => '[^.*]*',
        ];
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

    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

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

    public function setOperationType(?string $operationType): self
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
        return $this->entity;
    }

    public function setEntity(?Entity $entity): self
    {
        // Also put it in the array
        if ($entity === null) {
            foreach ($this->getEntities() as $removeEntity) {
                $this->removeEntity($removeEntity);
            }
        } else {
            $this->addEntity($entity);
        }

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

    public function getProxy(): ?Gateway
    {
        return $this->proxy;
    }

    public function setProxy(?Gateway $proxy): self
    {
        $this->proxy = $proxy;

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

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }
}
