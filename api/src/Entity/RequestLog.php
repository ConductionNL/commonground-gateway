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
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about a RequestLog.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/request_logs/{id}"},
 *      "put"={"path"="/admin/request_logs/{id}"},
 *      "delete"={"path"="/admin/request_logs/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/request_logs"},
 *      "post"={"path"="/admin/request_logs"}
 *  })
 * )
 * @ORM\Entity(repositoryClass="App\Repository\RequestLogRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "entity.id": "exact",
 *     "endpoint.id": "exact",
 *     "gateway.id": "exact",
 *     "application.id": "exact",
 *     "objectEntity.id": "exact"
 * })
 */
class RequestLog
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
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity=Endpoint::class, inversedBy="requestLogs")
     */
    private ?Endpoint $endpoint;

    /**
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity=Application::class, inversedBy="requestLogs")
     */
    private ?Application $application;

    /**
     * @var string|null An uuid or uri of an organization for this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $organization;

    /**
     * @var string|null An uuid or uri of a user for this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255, nullable=true, name="user_column")
     */
    private ?string $user;

    /**
     * @var string A status code of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255)
     */
    private string $statusCode;

    /**
     * @var string A status of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255)
     */
    private string $status;

    /**
     * @var array|null The request body of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $requestBody = [];

    /**
     * @var array The response body of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="array")
     */
    private array $responseBody = [];

    /**
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity=ObjectEntity::class, inversedBy="requestLogs")
     */
    private ?ObjectEntity $objectEntity;

    /**
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="requestLogs")
     */
    private ?Entity $entity;

    /**
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity=Document::class, inversedBy="requestLogs")
     */
    private ?Document $document;

    /**
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity=File::class, inversedBy="requestLogs")
     */
    private ?File $file;

    /**
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity=Gateway::class, inversedBy="requestLogs")
     */
    private ?Source $gateway;

    /**
     * @var string The method of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255)
     */
    private string $method;

    /**
     * @var array The headers of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="array")
     */
    private array $headers = [];

    /**
     * @var array The query parameters of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="array")
     */
    private array $queryParams = [];

    //TODO: https://symfony.com/doc/current/components/http_foundation.html#accessing-request-data
    //cookies
    //files

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

    public function __construct()
    {
        //TODO: better way of defaulting dateCreated & dateModified with orm?
        // (options CURRENT_TIMESTAMP or 0 does not work)
        $now = new DateTime();
        $this->setDateCreated($now);
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

    public function getEndpoint(): ?Endpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(?Endpoint $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): self
    {
        $this->application = $application;

        return $this;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function setOrganization(?string $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function setUser(?string $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    public function setStatusCode(string $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getRequestBody(): ?array
    {
        return $this->requestBody;
    }

    public function setRequestBody(?array $requestBody): self
    {
        $this->requestBody = $requestBody;

        return $this;
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    public function setResponseBody(array $responseBody): self
    {
        $this->responseBody = $responseBody;

        return $this;
    }

    public function getObjectEntity(): ?ObjectEntity
    {
        return $this->objectEntity;
    }

    public function setObjectEntity(?ObjectEntity $objectEntity): self
    {
        $this->objectEntity = $objectEntity;

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

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): self
    {
        $this->document = $document;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): self
    {
        $this->file = $file;

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

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function getQueryParams(): ?array
    {
        return $this->queryParams;
    }

    public function setQueryParams(array $queryParams): self
    {
        $this->queryParams = $queryParams;

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
