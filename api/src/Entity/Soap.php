<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\SoapRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass=SoapRepository::class)
 */
class Soap
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="soap")
     * @ORM\JoinColumn(nullable=false)
     */
    private $entity;

    /**
     * @ORM\Column(type="array")
     */
    private $requestSkeleton = [];

    /**
     * @ORM\Column(type="array")
     */
    private $responceSkeleton = [];

    /**
     * An array containing an request to entity translation in dot notation e.g. contact.firstname => person.name
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private $requestHydration = [];

    /**
     * An array containing an entity to reponce transaltion in dot notation e.g. person.name => contact.firstname
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private $responceHydration = [];

    public function getId(): ?int
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

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

    public function getRequestSkeleton(): ?array
    {
        return $this->requestSkeleton;
    }

    public function setRequestSkeleton(array $requestSkeleton): self
    {
        $this->requestSkeleton = $requestSkeleton;

        return $this;
    }

    public function getResponceSkeleton(): ?array
    {
        return $this->responceSkeleton;
    }

    public function setResponceSkeleton(array $responceSkeleton): self
    {
        $this->responceSkeleton = $responceSkeleton;

        return $this;
    }

    public function getRequestHydration(): ?array
    {
        return $this->requestHydration;
    }

    public function setRequestHydration(?array $requestHydration): self
    {
        $this->requestHydration = $requestHydration;

        return $this;
    }

    public function getResponceHydration(): ?array
    {
        return $this->responceHydration;
    }

    public function setResponceHydration(?array $responceHydration): self
    {
        $this->responceHydration = $responceHydration;

        return $this;
    }
}
