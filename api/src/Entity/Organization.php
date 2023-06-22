<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\OrganizationRepository;
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
 * This entity holds the information about an Organisation.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/organisations/{id}"},
 *      "put"={"path"="/admin/organisations/{id}"},
 *      "delete"={"path"="/admin/organisations/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/organisations"},
 *      "post"={"path"="/admin/organisations"}
 *  })
 * )
 *
 * @ORM\HasLifecycleCallbacks
 *
 * @ORM\Entity(repositoryClass=OrganizationRepository::class)
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact",
 *     "reference": "exact"
 * })
 */
class Organization
{
    /**
     * @var UuidInterface The UUID identifier of this resource
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
     * @var string The name of this Organization.
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
     * @var string|null A description of this Organization.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default": null})
     */
    private ?string $reference = null;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default": null})
     */
    private ?string $version = null;

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\OneToMany(targetEntity=User::class, mappedBy="organisation", orphanRemoval=true)
     */
    private $users;

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\OneToMany(targetEntity=Application::class, mappedBy="organization", orphanRemoval=true)
     */
    private $applications;

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\OneToMany(targetEntity=ObjectEntity::class, mappedBy="organization", orphanRemoval=true)
     */
    private $objectEntities;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource was last Modified
     *
     * @Groups({"read"})
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    /**
     * @ORM\OneToMany(targetEntity=Template::class, mappedBy="organization", orphanRemoval=true)
     */
    private $templates;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->templates = new ArrayCollection();
    }

    /**
     * Create or update this Organization from an external schema array.
     *
     * This function is used to update and create organizations form organization.json objects.
     *
     * @param array $schema The schema to load.
     *
     * @return $this This Organization.
     */
    public function fromSchema(array $schema): self
    {
        // Basic stuff
        if (array_key_exists('$id', $schema)) {
            $this->setReference($schema['$id']);
        }
        if (array_key_exists('version', $schema)) {
            $this->setVersion($schema['version']);
        }

        array_key_exists('title', $schema) ? $this->setName($schema['title']) : '';
        array_key_exists('description', $schema) ? $this->setDescription($schema['description']) : '';

        return $this;
    }

    /**
     * Convert this Organization to a schema.
     *
     * @return array Schema array.
     */
    public function toSchema(): array
    {
        return [
            '$id'                            => $this->getReference(), //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'                        => 'https://docs.commongateway.nl/schemas/Gateway.schema.json',
            'title'                          => $this->getName(),
            'description'                    => $this->getDescription(),
            'version'                        => $this->getVersion(),
            'name'                           => $this->getName(),
        ];
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = Uuid::fromString($id);

        return $this;
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return Collection|Application[]
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function addApplication(Application $application): self
    {
        if (!$this->applications->contains($application)) {
            $this->applications[] = $application;
            $application->setOrganization($this);
        }

        return $this;
    }

    public function removeApplication(Application $application): self
    {
        if ($this->applications->removeElement($application)) {
            // set the owning side to null (unless already changed)
            if ($application->getOrganization() === $this) {
                $application->setOrganization(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->setOrganisation($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getOrganisation() === $this) {
                $user->setOrganisation(null);
            }
        }

        return $this;
    }

    public function getDateCreated(): ?DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateModified(): ?DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }

    /**
     * @return Collection|Template[]
     */
    public function getTemplates(): Collection
    {
        return $this->templates;
    }

    public function addTemplate(Template $template): self
    {
        if (!$this->templates->contains($template)) {
            $this->templates[] = $template;
            $template->setOrganization($this);
        }

        return $this;
    }

    public function removeTemplate(Template $template): self
    {
        if ($this->templates->removeElement($template)) {
            // set the owning side to null (unless already changed)
            if ($template->getOrganization() === $this) {
                $template->setOrganization(null);
            }
        }

        return $this;
    }
}
