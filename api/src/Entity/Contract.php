<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\ContractRepository;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
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
 * @ORM\Entity(repositoryClass=ContractRepository::class)
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
   * @Groups({"read","read_secure"})
   * @ORM\Id
   * @ORM\Column(type="uuid", unique=true)
   * @ORM\GeneratedValue(strategy="CUSTOM")
   * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
   */
  private UuidInterface $id;

    /**
     * @var Application The Application that has to sign this Contract
     * 
     * @Assert\NotNull
     * 
     * @Groups({"read", "write"})
     * @ORM\ManyToOne(targetEntity=Application::class, inversedBy="contracts")
     * @ORM\JoinColumn(nullable=false)
     */
    private Application $application;

    /**
     * @var string The User as uuid that has to sign this Contract
     * 
     * @Assert\NotNull
     * @Assert\Type("string")
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=36)
     */
    private string $user;

    /**
     * @var array The scopes this Contract is about
     * 
     * @Assert\NotBlank
     * 
     * @Groups({"read", "write"})
     * @ORM\Column(type="array")
     */
    private array $grants = [];

    /**
     * @var DateTimeInterface|null The date the User signed this Contract
     * 
     * @Assert\DateTime
     * 
     * @Groups({"read", "write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $userSignedDate;

    /**
     * @var DateTimeInterface|null The date the Application signed this Contract
     * 
     * @Assert\DateTime
     * 
     * @Groups({"read", "write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $appSignedDate;

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

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function setUser(string $user): self
    {
        $this->user = $user;

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
}
