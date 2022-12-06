<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Gateway as Source;
use App\Repository\SubscriberRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An subscriber checks JSON conditions and executes translating and mapping on a outgoing call.
 *
 * @category Entity
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/subscribers/{id}"},
 *      "put"={"path"="/admin/subscribers/{id}"},
 *      "delete"={"path"="/admin/subscribers/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/subscribers"},
 *      "post"={"path"="/admin/subscribers"}
 *  })
 * @ORM\Entity(repositoryClass=SubscriberRepository::class)
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "entity.id": "exact"
 * })
 */
class Subscriber
{
    /**
     * @var UuidInterface The UUID identifier of this Entity.
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
     * @Assert\NotNull
     * @Assert\Type("string")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name;

    /**
     * @Assert\Type("string")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $description;

    /**
     * @var string The method that triggers this subscriber.
     *
     * @Assert\Type("string")
     * @Assert\Choice({"GET", "POST", "PUT", "PATCH"})
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, options={"default": "POST"})
     */
    private string $method = 'POST';

    /**
     * @var string The type of this subscriber. externSource will result in a call outside the gateway and internGateway will result in a new object inside the gateway.
     *
     * @example string
     *
     * @Assert\NotBlank
     * @Assert\Length(max = 255)
     * @Assert\Choice({"externSource", "internGateway"})
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $type = 'externSource';

    /**
     * @Assert\Type("integer")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true, options={"default": 0})
     */
    private ?int $runOrder = 0;

    /**
     * @Assert\Type("string")
     * @Assert\Json()
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $conditions;

    /**
     * @Assert\Type("bool")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private bool $asynchronous = true;

    /**
     * @Assert\Type("bool")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private bool $blocking = false;

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private array $headers = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private array $queryParameters = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $translationsIn = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $translationsOut = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private array $mappingIn = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private array $mappingOut = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $skeletonIn = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $skeletonOut = [];

    /**
     * @var Entity|null The entity that triggers this Subscriber.
     *
     * @MaxDepth(1)
     * @Groups({"read", "write"})
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="subscribers")
     */
    private ?Entity $entity = null;

    /**
     * @var Entity|null The entity for which a new object is created when this subscriber is triggered. (if type is internGateway)
     *
     * @MaxDepth(1)
     * @Groups({"read", "write"})
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="subscriberOut", cascade={"persist", "remove"})
     */
    private ?Entity $entityOut = null;

    /**
     * @var Endpoint|null An endpoint for the output of this Subscriber. (?)
     *
     * @Groups({"read", "write"})
     * @ORM\OneToOne(targetEntity=Endpoint::class, inversedBy="subscriber", cascade={"persist", "remove"})
     * @MaxDepth(1)
     */
    private ?Endpoint $endpoint;

    /**
     * @var ?Source The Source of this Subscriber
     *
     * @Groups({"read", "write"})
     * @ORM\ManyToOne(targetEntity=Gateway::class, inversedBy="subscribers")
     * @MaxDepth(1)
     */
    private ?Source $gateway;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    public function getId()
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
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

    public function getRunOrder(): ?int
    {
        return $this->runOrder;
    }

    public function setRunOrder(?int $runOrder): self
    {
        $this->runOrder = $runOrder;

        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function getAsynchronous(): ?bool
    {
        return $this->asynchronous;
    }

    public function setAsynchronous(bool $asynchronous): self
    {
        $this->asynchronous = $asynchronous;

        return $this;
    }

    public function getBlocking(): ?bool
    {
        return $this->blocking;
    }

    public function setBlocking(?bool $blocking): self
    {
        $this->blocking = $blocking;

        return $this;
    }

    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function setHeaders(?array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function getQueryParameters(): ?array
    {
        return $this->queryParameters;
    }

    public function setQueryParameters(?array $queryParameters): self
    {
        $this->queryParameters = $queryParameters;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTranslationsIn(): ?array
    {
        return $this->translationsIn;
    }

    public function setTranslationsIn(?array $translationsIn): self
    {
        $this->translationsIn = $translationsIn;

        return $this;
    }

    public function getTranslationsOut(): ?array
    {
        return $this->translationsOut;
    }

    public function setTranslationsOut(?array $translationsOut): self
    {
        $this->translationsOut = $translationsOut;

        return $this;
    }

    public function getMappingIn(): ?array
    {
        return $this->mappingIn;
    }

    public function setMappingIn(?array $mappingIn): self
    {
        $this->mappingIn = $mappingIn;

        return $this;
    }

    public function getMappingOut(): ?array
    {
        return $this->mappingOut;
    }

    public function setMappingOut(?array $mappingOut): self
    {
        $this->mappingOut = $mappingOut;

        return $this;
    }

    public function getSkeletonIn(): ?array
    {
        return $this->skeletonIn;
    }

    public function setSkeletonIn(?array $skeletonIn): self
    {
        $this->skeletonIn = $skeletonIn;

        return $this;
    }

    public function getSkeletonOut(): ?array
    {
        return $this->skeletonOut;
    }

    public function setSkeletonOut(?array $skeletonOut): self
    {
        $this->skeletonOut = $skeletonOut;

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

    public function getEntityOut(): ?Entity
    {
        return $this->entityOut;
    }

    public function setEntityOut(?Entity $entityOut): self
    {
        $this->entityOut = $entityOut;

        return $this;
    }

    public function getEndpoint(): ?Endpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(?Endpoint $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getSource(): ?Source
    {
        return $this->gateway;
    }

    public function setSource(?Source $source): self
    {
        $this->gateway = $source;

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
