<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\SubscriberRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Json;

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
    private $name;

    /**
     * @Assert\Type("string")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @Assert\Type("string")
     * @Assert\Choice({"GET", "POST", "PUT", "PATCH"})
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, options={"default": "POST"})
     */
    private $method = 'POST';

    /**
     * @Assert\Type("integer")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true, options={"default": 0})
     */
    private $runOrder = 0;

    /**
     * @Assert\Type("string")
     * @Assert\Json()
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", nullable=true)
     */
    private $conditions;

    /**
     * @Assert\Type("bool")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $asynchronous = true;

    /**
     * @Assert\Type("bool")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $blocking = false;

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private $headers = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private $queryParameters = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $translationsIn = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $translationsOut = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private $mappingIn = [];

    /**
     * @Assert\Type("array")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="json", nullable=true)
     */
    private $mappingOut = [];

    /**
     * @var Entity|null The entity of this Subscriber.
     *
     * @MaxDepth(1)
     * @Groups({"read", "write"})
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="subscribers")
     */
    private ?Entity $entity = null;

    /**
     * @Groups({"read", "write"})
     * @ORM\OneToOne(targetEntity=Gateway::class, inversedBy="subscriber", cascade={"persist", "remove"})
     * @MaxDepth(1)
     */
    private ?gateway $gateway;

    /**
     * @Groups({"read", "write"})
     * @ORM\OneToOne(targetEntity=Endpoint::class, inversedBy="subscriber", cascade={"persist", "remove"})
     * @MaxDepth(1)
     */
    private ?endpoint $endpoint;

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

    public function getEntity(): ?Entity
    {
        return $this->entity;
    }

    public function setEntity(?Entity $entity): self
    {
        $this->entity = $entity;

        return $this;
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

    public function getEndpoint(): ?Endpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(?Endpoint $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }
}
