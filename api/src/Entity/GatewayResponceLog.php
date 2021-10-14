<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\GatewayResponceLogRepository;
use Doctrine\ORM\Mapping as ORM;
use GuzzleHttp\Psr7\Response;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass=GatewayResponceLogRepository::class)
 */
class GatewayResponceLog
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Gateway::class, inversedBy="responceLogs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $gateway;

    /**
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="responceLogs")
     */
    private $entity;

    /**
     * @ORM\ManyToMany(targetEntity=ObjectEntity::class, inversedBy="responceLogs")
     */
    private $objectEntity;

    /**
     * @ORM\Column(type="integer")
     */
    private $statusCode;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $reasonPhrase;

    /**
     * @ORM\Column(type="string", length=25, nullable=true)
     */
    private $protocol;

    /**
     * @ORM\Column(type="string", length=5)
     */
    private $protocolVersion;

    /**
     * @ORM\Column(type="array")
     */
    private $headers = [];

    /**
     * @ORM\Column(type="blob", nullable=true)
     */
    private $content;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $succesfull;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $information;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $redirect;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $clientError;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $serverError;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEntity(): ?Entity
    {
        return $this->entity;
    }

    public function setEntity(?Entity $entity): self
    {
        if ($entity->getGateway()) {
            $this->setGateway($entity->getGateway());
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

    public function setResponce(Response $response): self
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
}
