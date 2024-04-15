<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\PurposeRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/purposes/{id}"},
 *      "put"={"path"="/admin/purposes/{id}"},
 *      "delete"={"path"="/admin/purposes/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/purposes"},
 *      "post"={"path"="/admin/purposes"}
 *  }
 * )
 *
 * @ORM\Entity(repositoryClass=PurposeRepository::class)
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class)
 */
class Purpose
{
    /**
     * @var UuidInterface The UUID identifier of this Entity.
     *
     * @Groups({"read"})
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
     * @var string The name of this Entity
     *
     * @Gedmo\Versioned
     *
     * @Assert\Length(
     *     max = 255
     * )
     *
     * @Assert\NotNull
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @var ?string The description of this Entity
     *
     * @Gedmo\Versioned
     *
     * @Assert\Length(
     *     max = 255
     * )
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $description = null;

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\ManyToOne(targetEntity=Contract::class, inversedBy="purposes")
     */
    private ?Contract $contract;

    public function getId(): UuidInterface
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

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): self
    {
        $this->contract = $contract;

        return $this;
    }
}
