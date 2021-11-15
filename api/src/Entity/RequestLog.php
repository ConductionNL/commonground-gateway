<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
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
 * @ApiFilter(SearchFilter::class)
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
     * @ORM\Column(type="string", length=255, nullable=true)
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
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $requestBody = [];

    /**
     * @var array The response body of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="json")
     */
    private array $responseBody = [];

    /**
     * @var string The error message of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255)
     */
    private string $message;

    /**
     * @var string The error type of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255)
     */
    private string $type;

    /**
     * @var string The error path of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255)
     */
    private string $path;

    /**
     * @var array The error data of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="json")
     */
    private array $data = [];

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
    private ?Gateway $gateway;

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
     * @ORM\Column(type="string", length=255)
     */
    private array $headers = [];

    /**
     * @var array The query parameters of this RequestLog.
     *
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255)
     */
    private array $queryParams = [];

    //TODO: https://symfony.com/doc/current/components/http_foundation.html#accessing-request-data
    //cookies
    //files

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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

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

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

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

    public function getGateway(): ?Gateway
    {
        return $this->gateway;
    }

    public function setGateway(?Gateway $gateway): self
    {
        $this->gateway = $gateway;

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
}
