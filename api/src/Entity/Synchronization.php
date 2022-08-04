<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
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
 * This entity holds the information about the Synchronization.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/synchronization/{id}"},
 *      "put"={"path"="/admin/synchronization/{id}"},
 *      "delete"={"path"="/admin/synchronization/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/synchronization"},
 *      "post"={"path"="/admin/synchronization"}
 *  })
 * )
 * @ORM\Entity(repositoryClass=SynchronizationRepository::class)
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

class Synchronization
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
    private string $id;

    /**
     * @var Entity The entity of this resource
     * 
     * @Groups({"read","write"})
     * @ORM\OneToOne(targetEntity=Entity::class, cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private entity $entity;

    /**
     * @var Entity The object of this resource
     * 
     * @Groups({"read","write"})
     * @ORM\OneToOne(targetEntity=ObjectEntity::class, cascade={"persist", "remove"})
     */
    private entity $object;

    /**
     * @var Entity The action of this resource
     * 
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Action::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private entity $action;

    /**
     * @var String The id of the related source
     * 
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $sourceId;

    /**
     * @var Text The hash of this resource
     * 
     * @Groups({"read","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private text $hash;

    /**
     * @var Datetime The moment this resource was last checked
     * 
     * @Groups({"read","write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private datetime $lastChecked;

    /**
     * @var Datetime The moment this resource was last synced
     * 
     * @Groups({"read","write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private datetime $lastSynced;
                                                                                                                      
    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read","write"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private datetime $dateCreated;

    /**
     * @var Datetime The moment this resource last Modified
     *
     * @Groups({"read","write"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private datetime $dateModified;

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getEntity(): ?Entity
    {
        return $this->entity;
    }

    public function setEntity(Entity $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getObject(): ?ObjectEntity
    {
        return $this->object;
    }

    public function setObject(?ObjectEntity $object): self
    {
        $this->object = $object;

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

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function setSourceId(string $sourceId): self
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function getLastChecked(): ?\DateTimeInterface
    {
        return $this->lastChecked;
    }

    public function setLastChecked(?\DateTimeInterface $lastChecked): self
    {
        $this->lastChecked = $lastChecked;

        return $this;
    }

    public function getLastSynced(): ?\DateTimeInterface
    {
        return $this->lastSynced;
    }

    public function setLastSynced(?\DateTimeInterface $lastSynced): self
    {
        $this->lastSynced = $lastSynced;

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
}
