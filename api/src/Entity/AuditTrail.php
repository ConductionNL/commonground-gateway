<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\AuditTrailRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use phpDocumentor\Reflection\Types\Integer;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about an Audit Trail.
 *
 * @ApiResource(
 *  shortName="gateway_audit_trail",
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *     "get"={"path"="/admin/audit_trails/{id}"},
 *     "put"={"path"="/admin/audit_trails/{id}"},
 *     "delete"={"path"="/admin/audit_trails/{id}"}
 *  },
 *  collectionOperations={
 *     "get"={"path"="/admin/audit_trails"},
 *     "post"={"path"="/admin/audit_trails"}
 *  },
 *  attributes={"order"={"creationDate": "DESC"}})
 *
 * @ORM\Entity(repositoryClass=AuditTrailRepository::class)
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class)
 *
 *
 * @ORM\Table(name="`gateway_audit_trail`")
 */
class AuditTrail
{
    /**
     * @var ?UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     *
     * @Groups({"read","read_secure"})
     *
     * @ORM\Id
     *
     * @ORM\Column(type="uuid", unique=true)
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var ?string The source of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $source;

    /**
     * @var ?string The application id of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $applicationId;

    /**
     * @var ?string The application view of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $applicationView;

    /**
     * @var ?string The user id of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $userId;

    /**
     * @var ?string The user view of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $userView;

    /**
     * @var ?string The action of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $action;

    /**
     * @var ?string The action view of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $actionView;

    /**
     * @var ?int The result of the audit trail Status code.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $result;

    /**
     * @var ?string The main object of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $mainObject;

    /**
     * @var ?string The resource of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $resource;

    /**
     * @var ?string The resource url of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $resourceUrl;

    /**
     * @var ?string The explanation of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $explanation;

    /**
     * @var ?string The resource view of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $resourceView;

    /**
     * @var ?DateTime The creation date of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $creationDate;

    /**
     * @var ?array The amendments of the audit trail
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $amendments;

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = Uuid::fromString($id);

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getApplicationId(): ?string
    {
        return $this->applicationId;
    }

    public function setApplicationId(?string $applicationId): self
    {
        $this->applicationId = $applicationId;

        return $this;
    }

    public function getApplicationView(): ?string
    {
        return $this->applicationView;
    }

    public function setApplicationView(?string $applicationView): self
    {
        $this->applicationView = $applicationView;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUserView(): ?string
    {
        return $this->userView;
    }

    public function setUserView(?string $userView): self
    {
        $this->userView = $userView;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getActionView(): ?string
    {
        return $this->actionView;
    }

    public function setActionView(?string $actionView): self
    {
        $this->actionView = $actionView;

        return $this;
    }

    public function getResult(): ?int
    {
        return $this->result;
    }

    public function setResult(?int $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getMainObject(): ?string
    {
        return $this->mainObject;
    }

    public function setMainObject(?string $mainObject): self
    {
        $this->mainObject = $mainObject;

        return $this;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(?string $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    public function getResourceUrl(): ?string
    {
        return $this->resourceUrl;
    }

    public function setResourceUrl(?string $resourceUrl): self
    {
        $this->resourceUrl = $resourceUrl;

        return $this;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): self
    {
        $this->explanation = $explanation;

        return $this;
    }

    public function getResourceView(): ?string
    {
        return $this->resourceView;
    }

    public function setResourceView(?string $resourceView): self
    {
        $this->resourceView = $resourceView;

        return $this;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(?\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getAmendments(): ?array
    {
        return $this->amendments;
    }

    public function setAmendments(?array $amendments): self
    {
        $this->amendments = $amendments;

        return $this;
    }
}
