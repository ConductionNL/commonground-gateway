<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\CallLogRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/logs/calllogs/{id}"},
 *      "put"={"path"="/admin/logs/calllogs/{id}"},
 *      "delete"={"path"="/admin/logs/calllogs/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/logs/calllogs"},
 *      "post"={"path"="/admin/logs/calllogs"}
 *  },
 *  order={"dateCreated": "DESC"}
 *  )
 *
 * @ORM\HasLifecycleCallbacks
 * @ORM\Entity(repositoryClass=CallLogRepository::class)
 * @ApiFilter(SearchFilter::class, properties={
 *     "source.id": "exact"
 * })
 */
class CallLog
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
    private UuidInterface $id;

    /**
     * @var Gateway The source that is requested
     *
     * @Groups({"read","read_secure"})
     * @ORM\ManyToOne(targetEntity=Gateway::class, cascade={"persist"}, inversedBy="callLogs")
     * @ORM\JoinColumn(nullable=false)
     */
    private Gateway $source;

    /**
     * @var string the endpoint on the source that is requested
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="string", length=255)
     */
    private string $endpoint = '';

    /**
     * @var array the configuration array of the request
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="array")
     */
    private array $config = [];

    /**
     * @var string The method of the request
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="string", length=255)
     */
    private string $method = '';

    /**
     * @var ?string The body of the request
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $requestBody = '';

    /**
     * @var ?array The headers of the response
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $requestHeaders = [];

    /**
     * @var string the response status of the request
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="string", length=255)
     */
    private string $responseStatus = '';

    /**
     * @var int the response status code
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private int $responseStatusCode = 0;

    /**
     * @var ?string The body of the response
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $responseBody = '';

    /**
     * @var array The headers of the response
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $responseHeaders = [];

    /**
     * @var int the runtime of the request
     *
     * @Groups({"read","read_secure"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $responseTime = 0;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private DateTimeInterface $dateCreated;

    /**
     * @var Datetime The moment this resource was last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private DateTimeInterface $dateModified;

    /**
     *  @ORM\PrePersist
     *  @ORM\PreUpdate
     */
    public function prePersist()
    {
        $this->source->setLastCall(new DateTime());
        $status = $this->source->getStatus();
        // If we only have a status code set code if we have code and status text set both
        isset($this->responseStatusCode) && !empty($this->responseStatusCode) && $status = $this->responseStatusCode;
        isset($this->responseStatusCode, $this->responseStatus) && !empty($this->responseStatusCode) && !empty($this->responseStatus) && $status = $this->responseStatusCode.' '.$this->responseStatus;
        $this->source->setStatus($status);
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getSource(): ?Gateway
    {
        return $this->source;
    }

    public function setSource(Gateway $source): self
    {
        $this->source = $source;

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

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

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

    public function getRequestBody(): ?string
    {
        return $this->requestBody;
    }

    public function setRequestBody(?string $requestBody): self
    {
        $this->requestBody = $requestBody;

        return $this;
    }

    public function getRequestHeaders(): ?array
    {
        return $this->requestHeaders;
    }

    public function setRequestHeaders(?array $requestHeaders): self
    {
        $this->requestHeaders = $requestHeaders;

        return $this;
    }

    public function getResponseStatus(): ?string
    {
        return $this->responseStatus;
    }

    public function setResponseStatus(string $responseStatus): self
    {
        $this->responseStatus = $responseStatus;

        return $this;
    }

    public function getResponseStatusCode(): ?int
    {
        return $this->responseStatusCode;
    }

    public function setResponseStatusCode(?int $responseStatusCode): self
    {
        $this->responseStatusCode = $responseStatusCode;

        return $this;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function setResponseBody(?string $responseBody): self
    {
        $this->responseBody = $responseBody;

        return $this;
    }

    public function getResponseHeaders(): ?array
    {
        return $this->responseHeaders;
    }

    public function setResponseHeaders(?array $responseHeaders): self
    {
        $this->responseHeaders = $responseHeaders;

        return $this;
    }

    public function getDateCreated(): ?DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?DateTimeInterface $dateCreated): self
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

    public function getResponseTime(): int
    {
        return $this->responseTime;
    }

    public function setResponseTime(int $responseTime): void
    {
        $this->responseTime = $responseTime;
    }
}