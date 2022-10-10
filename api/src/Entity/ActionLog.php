<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\ActionLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass=ActionLogRepository::class)
 */
class ActionLog
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Log::class, inversedBy="actionLogs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $log;

    /**
     * @ORM\ManyToOne(targetEntity=Action::class, inversedBy="actionLogs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $action;

    /**
     * @ORM\Column(type="array")
     */
    private $dataIn = [];

    /**
     * @ORM\Column(type="array")
     */
    private $dataOut = [];

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $class;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLog(): ?Log
    {
        return $this->log;
    }

    public function setLog(?Log $log): self
    {
        $this->log = $log;

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

    public function setDataOut(array $dataOut): self
    {
        $this->dataOut = $dataOut;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }
}
