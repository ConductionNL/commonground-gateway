<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\ReadRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass=ReadRepository::class)
 */
class Read
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=2550)
     */
    private $objectId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $userId;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateRead;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }

    public function setObjectId(string $objectId): self
    {
        $this->objectId = $objectId;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getDateRead(): ?\DateTimeInterface
    {
        return $this->dateRead;
    }

    public function setDateRead(?\DateTimeInterface $dateRead): self
    {
        $this->dateRead = $dateRead;

        return $this;
    }
}
