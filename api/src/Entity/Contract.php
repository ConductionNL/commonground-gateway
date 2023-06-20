<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\ContractRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds a Contract between a User and a Application.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/contracts/{id}"},
 *      "put"={"path"="/admin/contracts/{id}"},
 *      "delete"={"path"="/admin/contracts/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/contracts"},
 *      "post"={"path"="/admin/contracts"}
 *  })
 *
 * @ORM\Entity(repositoryClass=ContractRepository::class)
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class)
 */
class Contract
{
    /**
     * @var UuidInterface The UUID identifier of this Entity.
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
    private UuidInterface $id;

    /**
     * @var Application The Application that has to sign this Contract
     *
     * @Assert\NotNull
     *
     * @Groups({"read", "write"})
     *
     * @ORM\ManyToOne(targetEntity=Application::class, inversedBy="contracts")
     *
     * @ORM\JoinColumn(nullable=false)
     */
    private Application $application;

    /**
     * @var string The User as uuid that has to sign this Contract
     *
     * @Assert\NotNull
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=36)
     */
    private string $signingUser;

    /**
     * @var array The scopes this Contract is about
     *
     * @Assert\NotBlank
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array")
     */
    private array $grants = [];

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\OneToMany(targetEntity=Purpose::class, mappedBy="contract")
     */
    private ?Collection $purposes;

    /**
     * @var DateTimeInterface|null The date the User signed this Contract
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $userSignedDate;

    /**
     * @var DateTimeInterface|null The date the Application signed this Contract
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $appSignedDate;

    public function __construct()
    {
        $this->purposes = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): self
    {
        $this->application = $application;

        return $this;
    }

    public function getSigningUser(): ?string
    {
        return $this->signingUser;
    }

    public function setSigningUser(string $signingUser): self
    {
        $this->signingUser = $signingUser;

        return $this;
    }

    public function getGrants(): ?array
    {
        return $this->grants;
    }

    public function setGrants(array $grants): self
    {
        $this->grants = $grants;

        return $this;
    }

    public function getUserSignedDate(): ?\DateTimeInterface
    {
        return $this->userSignedDate;
    }

    public function setUserSignedDate(?\DateTimeInterface $userSignedDate): self
    {
        $this->userSignedDate = $userSignedDate;

        return $this;
    }

    public function getAppSignedDate(): ?\DateTimeInterface
    {
        return $this->appSignedDate;
    }

    public function setAppSignedDate(?\DateTimeInterface $appSignedDate): self
    {
        $this->appSignedDate = $appSignedDate;

        return $this;
    }

    /**
     * @return Collection|Purpose[]
     */
    public function getPurposes(): Collection
    {
        return $this->purposes;
    }

    public function addPurpose(Purpose $purpose): self
    {
        if (!$this->purposes->contains($purpose)) {
            $this->purposes[] = $purpose;
            $purpose->setContract($this);
        }

        return $this;
    }

    public function removePurpose(Purpose $purpose): self
    {
        if ($this->purposes->removeElement($purpose)) {
            // set the owning side to null (unless already changed)
            if ($purpose->getContract() === $this) {
                $purpose->setContract(null);
            }
        }

        return $this;
    }
}
