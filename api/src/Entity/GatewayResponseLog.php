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
use GuzzleHttp\Psr7\Response;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about a ResponseLog.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/response_logs/{id}"},
 *      "put"={"path"="/admin/response_logs/{id}"},
 *      "delete"={"path"="/admin/response_logs/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/response_logs"},
 *      "post"={"path"="/admin/response_logs"}
 *  })
 * )
 * @ORM\Entity(repositoryClass="App\Repository\GatewayResponseLogRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "entity.id": "exact",
 *     "gateway.id": "exact",
 *     "objectEntity.id": "exact",
 * })
 */
class GatewayResponseLog
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
     * @ORM\ManyToOne(targetEntity=Gateway::class, inversedBy="responseLogs")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Source $gateway;

    /**
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="responseLogs")
     */
    private ?Entity $entity;

    /**
     * @Groups({"read"})
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=ObjectEntity::class, inversedBy="responseLogs")
     */
    private ?ObjectEntity $objectEntity;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="integer")
     */
    private ?int $statusCode;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="string", length=255)
     */
    private ?string $reasonPhrase;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="string", length=25, nullable=true)
     */
    private ?string $protocol;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="string", length=5)
     */
    private ?string $protocolVersion;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="array")
     */
    private array $headers = [];

    /**
     * @Groups({"read"})
     * @ORM\Column(type="blob", nullable=true)
     */
    private $content;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $succesfull;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $information;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $redirect;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $clientError;

    /**
     * @Groups({"read"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $serverError;

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

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id): self
    {
        $this->id = $id;

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

    public function getEntity(): ?Entity
    {
        return $this->entity;
    }

    public function setEntity(?Entity $entity): self
    {
        if ($entity->getSource()) {
            $this->setSource($entity->getSource());
        }
        $this->entity = $entity;

        return $this;
    }

    public function getObjectEntity(): ?ObjectEntity
    {
        return $this->objectEntity;
    }

    public function setObjectEntity(?ObjectEntity $objectEntity): self
    {
        if ($objectEntity->getEntity()) {
            $this->setEntity($objectEntity->getEntity());
        }
        $this->objectEntity = $objectEntity;

        return $this;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getReasonPhrase(): ?string
    {
        return $this->reasonPhrase;
    }

    public function setReasonPhrase(string $reasonPhrase): self
    {
        $this->reasonPhrase = $reasonPhrase;

        return $this;
    }

    public function getProtocol(): ?string
    {
        return $this->protocol;
    }

    public function setProtocol(string $protocol): self
    {
        $this->protocol = $protocol;

        return $this;
    }

    public function getProtocolVersion(): ?string
    {
        return $this->protocolVersion;
    }

    public function setProtocolVersion(string $protocolVersion): self
    {
        $this->protocolVersion = $protocolVersion;

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

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getSuccesfull(): ?bool
    {
        return $this->succesfull;
    }

    public function setSuccesfull(bool $succesfull): self
    {
        $this->succesfull = $succesfull;

        return $this;
    }

    public function getInformation(): ?bool
    {
        return $this->information;
    }

    public function setInformation(bool $information): self
    {
        $this->information = $information;

        return $this;
    }

    public function getRedirect(): ?bool
    {
        return $this->redirect;
    }

    public function setRedirect(bool $redirect): self
    {
        $this->redirect = $redirect;

        return $this;
    }

    public function getClientError(): ?bool
    {
        return $this->clientError;
    }

    public function setClientError(bool $clientError): self
    {
        $this->clientError = $clientError;

        return $this;
    }

    public function getServerError(): ?bool
    {
        return $this->serverError;
    }

    public function setServerError(?bool $serverError): self
    {
        $this->serverError = $serverError;

        return $this;
    }

    public function setResponse(Response $response): self
    {
        $this->setStatusCode($response->getStatusCode());
        $this->setReasonPhrase($response->getReasonPhrase());
//        $this->setProtocol($response->getProtocol());
        $this->setProtocolVersion($response->getProtocolVersion());
        $this->setHeaders($response->getHeaders());
        $this->setContent((string) $response->getBody());
//        $this->setSuccesfull($response->isSuccessful()); // true
//        $this->setInformation($response->isInformational());
//        $this->setRedirect($response->isRedirect());
//        $this->setClientError($response->isClientError());
//        $this->setServerError($response->isServerError());
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
