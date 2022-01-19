<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about a Logs.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/logs/{id}"},
 *      "put"={"path"="/admin/logs/{id}"},
 *      "delete"={"path"="/admin/logs/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/logs"},
 *      "post"={"path"="/admin/logs"}
 *  })
 * )
 * @ORM\Entity(repositoryClass="App\Repository\LogRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "entity.id": "exact",
 *     "endpoint.id": "exact",
 *     "sources.id": "exact",
 *     "handler.id": "exact"
 * })
 */
class Log
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
     * @var string The type of this Log.
     *
     * @Assert\NotNull
     * @Assert\Length(
     *      max = 255
     * )
     * @Assert\Choice({"in", "out"})
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "enum"={"in", "out"},
     *             "example"="in"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * @var UuidInterface The call id of this Log.
     *
     * @Assert\NotNull
     * @Groups({"read","write"})
     * @ORM\Column(type="uuid", unique=true)
     */
    private $callId;

    /**
     * @var string The request method of this Log.
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $requestMethod;

    /**
     * @var array The request headers of this Log.
     *
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="array")
     */
    private $requestHeaders = [];

    /**
     * @var array The request query of this Log.
     *
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="array")
     */
    private $requestQuery = [];

    /**
     * @var string The request path info of this Log.
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $requestPathInfo;

    /**
     * @var array The request languages of this Log.
     *
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="array")
     */
    private $requestLanguages;

    /**
     * @var array The request server of this Log.
     *
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="array")
     */
    private $requestServer = [];

    /**
     * @var string The request content for this Log.
     *
     * @Assert\NotNull
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"
     *         }
     *     }
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="text")
     */
    private $requestContent;

    /**
     * @var string The response status of this Log.
     *
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $responseStatus;

    /**
     * @var int The response status code of this Log.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $responseStatusCode;

    /**
     * @var array The response headers of this Log.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $responseHeaders = [];

    /**
     * @var string The response content of this Log.
     *
     * @Assert\Length(
     *     max = 2555
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=2555, nullable=true)
     */
    private $responseContent;

    /**
     * @var string The session of this Log.
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $session;

    /**
     * @var array The session values of this Log.
     *
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="array")
     */
    private $sessionValues = [];

    // /**
    //  * @var ?object The endpoint of this Log.
    //  *
    //  * @Groups({"read", "write"})
    //  * @ORM\Column(type="object", nullable=true)
    //  */
    // private $endpoint;

    // /**
    //  * @var ?object The handler of this Log.
    //  *
    //  * @Groups({"read", "write"})
    //  * @ORM\Column(type="object", nullable=true)
    //  */
    // private $handler;

    // /**
    //  * @var ?object The entity of this Log.
    //  *
    //  * @Groups({"read", "write"})
    //  * @ORM\Column(type="object", nullable=true)
    //  */
    // private $entity;

    // /**
    //  * @var ?object The source of this Log.
    //  *
    //  * @Groups({"read", "write"})
    //  * @ORM\Column(type="object", nullable=true)
    //  */
    // private $source;

    /**
     * @var int The endpoint of this Log.
     *
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer")
     */
    private $responseTime;

    /**
     * @var Datetime The moment this log was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @var string The route name of this Log.
     *
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $routeName;

    /**
     * @var array The route parameters of this Log.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $routeParameters;

    public function getId()
    {
        return $this->id;
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

    public function getCallId()
    {
        return $this->callId;
    }

    public function setCallId($callId): self
    {
        $this->callId = $callId;

        return $this;
    }

    public function getRequestMethod(): ?string
    {
        return $this->requestMethod;
    }

    public function setRequestMethod(string $requestMethod): self
    {
        $this->requestMethod = $requestMethod;

        return $this;
    }

    public function getRequestHeaders(): ?array
    {
        return $this->requestHeaders;
    }

    public function setRequestHeaders(array $requestHeaders): self
    {
        $this->requestHeaders = $requestHeaders;

        return $this;
    }

    public function getRequestQuery(): ?array
    {
        return $this->requestQuery;
    }

    public function setRequestQuery(array $requestQuery): self
    {
        $this->requestQuery = $requestQuery;

        return $this;
    }

    public function getRequestPathInfo(): ?string
    {
        return $this->requestPathInfo;
    }

    public function setRequestPathInfo(string $requestPathInfo): self
    {
        $this->requestPathInfo = $requestPathInfo;

        return $this;
    }

    public function getRequestLanguages(): ?array
    {
        return $this->requestLanguages;
    }

    public function setRequestLanguages(array $requestLanguages): self
    {
        $this->requestLanguages = $requestLanguages;

        return $this;
    }

    public function getRequestServer(): ?array
    {
        return $this->requestServer;
    }

    public function setRequestServer(array $requestServer): self
    {
        $this->requestServer = $requestServer;

        return $this;
    }

    public function getRequestContent(): ?string
    {
        return $this->requestContent;
    }

    public function setRequestContent(string $requestContent): self
    {
        $this->requestContent = $requestContent;

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

    public function setResponseStatusCode(int $responseStatusCode): self
    {
        $this->responseStatusCode = $responseStatusCode;

        return $this;
    }

    public function getResponseHeaders(): ?array
    {
        return $this->responseHeaders;
    }

    public function setResponseHeaders(array $responseHeaders): self
    {
        $this->responseHeaders = $responseHeaders;

        return $this;
    }

    public function getResponseContent(): ?string
    {
        return $this->responseContent;
    }

    public function setResponseContent(string $responseContent): self
    {
        $this->responseContent = $responseContent;

        return $this;
    }

    public function getSession(): ?string
    {
        return $this->session;
    }

    public function setSession(string $session): self
    {
        $this->session = $session;

        return $this;
    }

    public function getSessionValues(): ?array
    {
        return $this->sessionValues;
    }

    public function setSessionValues(array $sessionValues): self
    {
        $this->sessionValues = $sessionValues;

        return $this;
    }

    // public function getEndpoint(): ?object
    // {
    //     return $this->endpoint;
    // }

    // public function setEndpoint(?object $endpoint): self
    // {
    //     $this->endpoint = $endpoint;

    //     return $this;
    // }

    // public function getHandler(): ?object
    // {
    //     return $this->handler;
    // }

    // public function setHandler(?object $handler): self
    // {
    //     $this->handler = $handler;

    //     return $this;
    // }

    // public function getEntity(): ?object
    // {
    //     return $this->entity;
    // }

    // public function setEntity(?object $entity): self
    // {
    //     $this->entity = $entity;

    //     return $this;
    // }

    // public function getSource(): ?object
    // {
    //     return $this->source;
    // }

    // public function setSource(?object $source): self
    // {
    //     $this->source = $source;

    //     return $this;
    // }

    public function getResponseTime(): int
    {
        return $this->responseTime;
    }

    public function setResponseTime(int $responseTime): self
    {
        $this->responseTime = $responseTime;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function setRouteName(?string $routeName): self
    {
        $this->routeName = $routeName;

        return $this;
    }

    public function getRouteParameters(): ?array
    {
        return $this->routeParameters;
    }

    public function setRouteParameters(?array $routeParameters): self
    {
        $this->routeParameters = $routeParameters;

        return $this;
    }
}
