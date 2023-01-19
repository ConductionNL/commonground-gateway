<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\MappingRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/mappings/{id}"},
 *      "put"={"path"="/admin/mappings/{id}"},
 *      "delete"={"path"="/admin/mappings/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/mappings"},
 *      "post"={"path"="/admin/mappings"}
 *  })
 * )
 * @ORM\Entity(repositoryClass=MappingRepository::class)
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact"
 * })
 */
class Mapping
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
     * @var string The name of the mapping
     *
     * @Assert\NotNull
     * @Assert\Length(max=255)
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @var string|null The description of the mapping
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @var array The mapping of this mapping object
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="array")
     */
    private array $mapping = [];

    /**
     * @var array|null The unset of this mapping object
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $unset = [];

    /**
     * @var boolean|null The passThrough of this mapping object
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private bool $passTrough;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource was last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

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

    public function getMapping(): ?array
    {
        return $this->mapping;
    }

    public function setMapping(array $mapping): self
    {
        $this->mapping = $mapping;

        return $this;
    }

    public function getUnset(): ?array
    {
        return $this->unset;
    }

    public function setUnset(?array $unset): self
    {
        $this->unset = $unset;

        return $this;
    }

    public function getPassTrough(): ?bool
    {
        return $this->passTrough;
    }

    public function setPassTrough(?bool $passTrough): self
    {
        $this->passTrough = $passTrough;

        return $this;
    }
}
