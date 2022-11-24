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
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about the Synchronization.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/synchronizations/{id}"},
 *      "put"={"path"="/admin/synchronizations/{id}"},
 *      "delete"={"path"="/admin/synchronizations/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/synchronizations"},
 *      "post"={"path"="/admin/synchronizations"}
 *  })
 * @ORM\Entity(repositoryClass="App\Repository\SynchronizationRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "entity.id": "exact",
 *     "gateway.id": "exact",
 *     "object.id": "exact",
 *     "sourceId": "exact"
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
    private UuidInterface $id;

    /**
     * @var Entity The entity of this resource
     *
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Entity::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private Entity $entity;

    /**
     * @var ?ObjectEntity The object of this resource
     *
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=ObjectEntity::class, inversedBy="synchronizations", fetch="EAGER")
     */
    private ?ObjectEntity $object = null;

    /**
     * @var Action|null The action of this resource
     *
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Action::class)
     */
    private ?Action $action = null;

    /**
     * @var Source The Soruce of this resource
     *
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Gateway::class, cascade={"persist"}, inversedBy="synchronizations")
     * @ORM\JoinColumn(nullable=false)
     */
    private Source $gateway;

    /**
     * @var string|null
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $endpoint = null;

    /**
     * @var string The id of object in the related source
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $sourceId;

    /**
     * @var ?string The hash of this resource
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $hash = '';

    /**
     * @var ?DateTimeInterface The moment the source of this resource was last changed
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $sourceLastChanged = null;

    /**
     * @var ?DateTimeInterface The moment this resource was last checked
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $lastChecked = null;

    /**
     * @var ?DateTimeInterface The moment this resource was last synced
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $lastSynced = null;

    /**
     * @var ?DateTimeInterface The moment this resource was created
     *
     * @Groups({"read","write"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $dateCreated;

    /**
     * @var ?DateTimeInterface The moment this resource last Modified
     *
     * @Groups({"read","write"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $dateModified;

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

    public function getSource(): ?Source
    {
        return $this->gateway;
    }

    public function setSource(?Source $source): self
    {
        $this->gateway = $source;

        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): self
    {
        $this->endpoint = $endpoint;

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

    public function getSourceLastChanged(): ?\DateTimeInterface
    {
        return $this->sourceLastChanged;
    }

    public function setSourceLastChanged(?\DateTimeInterface $sourceLastChanged): self
    {
        $this->sourceLastChanged = $sourceLastChanged;

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
        isset($this->gateway) && $this->gateway->setLastSync($lastSynced);

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
