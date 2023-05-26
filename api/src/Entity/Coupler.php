<?php

namespace App\Entity;

use App\Repository\CouplerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=CouplerRepository::class)
 */
class Coupler
{
    /**
     * @var UuidInterface The UUID identifier of this Entity.
     *
     * @ORM\Id
     *
     * @ORM\Column(type="uuid", unique=true)
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private UuidInterface $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $objectId = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $entity = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTime $dateCreated = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTime $dateModified = null;

    /**
     * @ORM\ManyToMany(targetEntity=Value::class, mappedBy="objects")
     */
    private Collection $subresourceOf;

    public function __construct(?object $object = null)
    {
        $this->subresourceOf = new ArrayCollection();

        if($object !== null) {
            $this->setEntity(get_class($object));
            $this->setObjectId($object->getId());
        }
    }

    public function getId(): ?UuidInterface
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

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?\DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateModified(): ?\DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(?\DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }

    public function getSubresourceOf(): ?Collection
    {
        return $this->subresourceOf;
    }

    public function addSubresourceOf(Value $subresourceOf): self
    {
        if (!$this->subresourceOf->contains($subresourceOf)) {
            $this->subresourceOf[] = $subresourceOf;
        }

        return $this;
    }

    public function removeSubresourceOf(Value $subresourceOf): self
    {
        $this->subresourceOf->removeElement($subresourceOf);

        return $this;
    }
}
