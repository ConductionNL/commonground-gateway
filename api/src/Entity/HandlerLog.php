<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\HandlerLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass=HandlerLogRepository::class)
 */
class HandlerLog
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Handler::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $handler;

    /**
     * @ORM\ManyToOne(targetEntity=Action::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $action;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $handlerClass;

    /**
     * @ORM\Column(type="array")
     */
    private $configuration = [];

    /**
     * @ORM\Column(type="array")
     */
    private $dataIn = [];

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $dataOut = [];

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $report;

    /**
     * @ORM\Column(type="boolean")
     */
    private $status;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHandler(): ?Handler
    {
        return $this->handler;
    }

    public function setHandler(?Handler $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function getAction(): ?Action
    {
        return $this->action;
    }

    public function setAction(?Action $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getHandlerClass(): ?string
    {
        return $this->handlerClass;
    }

    public function setHandlerClass(string $handlerClass): self
    {
        $this->handlerClass = $handlerClass;

        return $this;
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getDataIn(): ?array
    {
        return $this->dataIn;
    }

    public function setDataIn(array $dataIn): self
    {
        $this->dataIn = $dataIn;

        return $this;
    }

    public function getDataOut(): ?array
    {
        return $this->dataOut;
    }

    public function setDataOut(?array $dataOut): self
    {
        $this->dataOut = $dataOut;

        return $this;
    }

    public function getReport(): ?string
    {
        return $this->report;
    }

    public function setReport(?string $report): self
    {
        $this->report = $report;

        return $this;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;

        return $this;
    }
}
