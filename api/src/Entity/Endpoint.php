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
 * @ApiFilter(SearchFilter::class)
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
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

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
     * @var string The path of this Endpoint.
     *
     * @Assert\NotNull
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string")
     */
    private string $path;

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
     * @var array|null The handlers used for this entity.
     *
     * @MaxDepth(1)
     * @Groups({"read", "write"})
     * @ORM\OneToMany(targetEntity=Handler::class, mappedBy="endpoint", orphanRemoval=true)
     */
    private Collection $handlers;

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

    public function __construct()
    {
        $this->requestLogs = new ArrayCollection();
        $this->handlers = new ArrayCollection();
        $this->applications = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

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
            $handler->setObject($this);
        }

        return $this;
    }

    public function removeHandler(Handler $handler): self
    {
        if ($this->handlers->removeElement($handler)) {
            // set the owning side to null (unless already changed)
            if ($handler->getObject() === $this) {
                $handler->setObject(null);
            }
        }

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
}
