<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\ActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about an Application.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/actions/{id}"},
 *      "put"={"path"="/admin/actions/{id}"},
 *      "delete"={"path"="/admin/actions/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/actions"},
 *      "post"={"path"="/admin/actions"}
 *  })
 * )
 * @ORM\HasLifecycleCallbacks
 * @ORM\Entity(repositoryClass=ActionRepository::class)
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact"
 * })
 */
class Action
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
     * @var string The name of the action
     *
     * @Assert\NotNull
     * @Assert\Length(max=255)
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @var string|null The description of the action
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @var array The event names the action should listen to
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="simple_array")
     */
    private array $listens;

    /**
     * @var array The event names the action should trigger
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="simple_array", nullable=true)
     */
    private array $throws = [];

    /**
     * @var array The conditions that the data object should match for the action to be triggered
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private array $conditions = [];

    /**
     * @var string|null The class that should be run when the action is triggered
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $class = null;

    /**
     * @var int The priority of the action
     *
     * @Assert\NotNull
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="integer")
     */
    private int $priority;

    /**
     * @var bool Whether the action should be run asynchronous
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="boolean")
     */
    private bool $async = false;

    /**
     * @var array The configuration of the action
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private array $configuration = [];

    /**
     * @ORM\OneToMany(targetEntity=ActionLog::class, mappedBy="action", orphanRemoval=true)
     */
    private $actionLogs;

    public function __construct()
    {
        $this->actionLogs = new ArrayCollection();
    }

    public function getId(): ?UuidInterface
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

    public function getListens(): ?array
    {
        return $this->listens;
    }

    public function setListens(?array $listens): self
    {
        $this->listens = $listens;

        return $this;
    }

    public function getThrows(): ?array
    {
        return $this->throws;
    }

    public function setThrows(?array $throws): self
    {
        $this->throws = $throws;

        return $this;
    }

    public function getConditions(): ?array
    {
        return $this->conditions;
    }

    public function setConditions(?array $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(?string $class): self
    {
        $this->class = $class;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getAsync(): ?bool
    {
        return $this->async;
    }

    public function setAsync(bool $async): self
    {
        $this->async = $async;

        return $this;
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(?array $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    /**
     * @return Collection|ActionLog[]
     */
    public function getActionLogs(): Collection
    {
        return $this->actionLogs;
    }

    public function addActionLog(ActionLog $actionLog): self
    {
        if (!$this->actionLogs->contains($actionLog)) {
            $this->actionLogs[] = $actionLog;
            $actionLog->setAction($this);
        }

        return $this;
    }

    public function removeActionLog(ActionLog $actionLog): self
    {
        if ($this->actionLogs->removeElement($actionLog)) {
            // set the owning side to null (unless already changed)
            if ($actionLog->getAction() === $this) {
                $actionLog->setAction(null);
            }
        }

        return $this;
    }
}
