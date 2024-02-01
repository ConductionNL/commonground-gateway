<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
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
 * A handler. This Entity is DEPRECATED! We should remove it.
 *
 * @deprecated
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/handlers/{id}"},
 *      "put"={"path"="/admin/handlers/{id}"},
 *      "delete"={"path"="/admin/handlers/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/handlers"},
 *      "post"={"path"="/admin/handlers"}
 *  })
 *
 * @ORM\Entity(repositoryClass="App\Repository\HandlerRepository")
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact",
 *     "endpoints.id": "exact",
 *     "entity.id": "exact"
 * })
 */
class Handler
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
    private string $id;

    /**
     * @var string The name of this Handler.
     *
     * @Assert\NotNull
     *
     * @Assert\Length(
     *     max = 255
     * )
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="string", length=255)
     * @deprecated
     */
    private string $name;

    /**
     * @var string|null The description of this Handler.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="text", nullable=true)
     * @deprecated
     */
    private ?string $description = null;

    /**
     * @Assert\Choice({"*", "GET", "POST", "PUT", "PATCH", "DELETE", "get", "post", "put", "patch", "delete"}, multiple=true)
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array")
     * @deprecated
     */
    private $methods = [];

    /**
     * @var int The order of how the JSON conditions will be tested.
     *
     * @Assert\NotNull
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="integer")
     * @deprecated
     */
    private int $sequence;

    /**
     * @var array The JSON conditions of this Handler.
     *
     * @Assert\Json
     *
     * @Assert\NotNull
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", options={"default": "{}"})
     * @deprecated
     */
    private string $conditions;

    /**
     * @var array|null The translations of this Handler.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     * @deprecated
     */
    private ?array $translationsIn = [];

    /**
     * @var array|null The mapping of this Handler.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     * @deprecated
     */
    private ?array $mappingIn = [];

    /**
     * @var array|null The mapping of this Handler.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     * @deprecated
     */
    private ?array $skeletonIn = [];

    // /**
    //  * @var Entity The entity of this Handler.
    //  *
    //  * @MaxDepth(1)
    //  * @Groups({"read", "write"})
    //  * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="handlers")
    //  */
    // private ?Entity $entity = null;

    /**
     * @var array|null The skeleton of this Handler.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     * @deprecated
     */
    private ?array $skeletonOut = [];

    /**
     * @var array|null The mappingOut of this Handler.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     * @deprecated
     */
    private ?array $mappingOut = [];

    /**
     * @var array|null The translationsOut of this Handler.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     * @deprecated
     */
    private ?array $translationsOut = [];

    /**
     * @var string|null The template type of this Handler.
     *
     * @Assert\Length(
     *     max = 255
     * )
     *
     * @Groups({"read", "write"})
     *
     * @Assert\Choice({"twig", "markdown", "restructuredText"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @deprecated
     */
    private ?string $templateType;

    /**
     * @var string|null The template of this Handler.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="text", nullable=true)
     * @deprecated
     */
    private ?string $template;

    /**
     * @var Entity The entity of this Handler.
     *
     * @MaxDepth(1)
     *
     * @Groups({"read", "write"})
     *
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="handlers")
     * @deprecated
     */
    private ?Entity $entity = null;

    /**
     * @var Collection|null The entity of this Handler.
     *
     * @MaxDepth(1)
     *
     * @Groups({"read", "write"})
     *
     * @ORM\ManyToMany(targetEntity=Endpoint::class, inversedBy="handlers", cascade={"persist"})
     * @deprecated
     */
    private ?Collection $endpoints;

    /**
     * @var string|null The gateway to proxy to
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @deprecated
     */
    private ?string $proxyGateway = null;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=true)
     * @deprecated
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
     * @deprecated
     */
    private $dateModified;

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     * @deprecated
     */
    private $methodOverrides = [];

    /**
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @deprecated
     */
    private $prefix;

    /**
     * @ORM\OneToMany(targetEntity=Log::class, mappedBy="handler", fetch="EXTRA_LAZY", cascade={"remove"}, orphanRemoval=true)
     *
     * @MaxDepth(1)
     * @deprecated
     */
    private Collection $logs;

    public function __construct()
    {
        $this->endpoints = new ArrayCollection();
        $this->logs = new ArrayCollection();
    }

    public function getId()
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

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(string $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function getTranslationsIn(): ?array
    {
        return $this->translationsIn;
    }

    public function setTranslationsIn(?array $translationsIn): self
    {
        $this->translationsIn = $translationsIn;

        return $this;
    }

    public function getMappingIn(): ?array
    {
        return $this->mappingIn;
    }

    public function setMappingIn(?array $mappingIn): self
    {
        $this->mappingIn = $mappingIn;

        return $this;
    }

    public function getSkeletonIn(): ?array
    {
        return $this->skeletonIn;
    }

    public function setSkeletonIn(?array $skeletonIn): self
    {
        $this->skeletonIn = $skeletonIn;

        return $this;
    }

    public function getSkeletonOut(): ?array
    {
        return $this->skeletonOut;
    }

    public function setSkeletonOut(?array $skeletonOut): self
    {
        $this->skeletonOut = $skeletonOut;

        return $this;
    }

    public function getMappingOut(): ?array
    {
        return $this->mappingOut;
    }

    public function setMappingOut(?array $mappingOut): self
    {
        $this->mappingOut = $mappingOut;

        return $this;
    }

    public function getTranslationsOut(): ?array
    {
        return $this->translationsOut;
    }

    public function setTranslationsOut(?array $translationsOut): self
    {
        $this->translationsOut = $translationsOut;

        return $this;
    }

    public function getTemplateType(): ?string
    {
        return $this->templateType;
    }

    public function setTemplateType(?string $templateType): self
    {
        $this->templateType = $templateType;

        return $this;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getEntity(): ?Entity
    {
        return $this->entity;
    }

    public function setEntity(?Entity $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getMethods(): ?array
    {
        return $this->methods;
    }

    public function setMethods(array $methods): self
    {
        $this->methods = $methods;

        return $this;
    }

    /**
     * @return Collection|Endpoint[]
     */
    public function getEndpoints(): Collection
    {
        return $this->endpoints;
    }

    public function addEndpoint(Endpoint $endpoint): self
    {
        if (!$this->endpoints->contains($endpoint)) {
            $this->endpoints[] = $endpoint;
        }

        return $this;
    }

    public function removeEndpoint(Endpoint $endpoint): self
    {
        $this->endpoints->removeElement($endpoint);

        return $this;
    }

    /**
     * @return Collection|Log[]
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(Log $log): self
    {
        if (!$this->logs->contains($log)) {
            $this->logs[] = $log;
        }

        return $this;
    }

    public function removeLog(Log $log): self
    {
        $this->logs->removeElement($log);

        return $this;
    }

    public function getProxyGateway(): ?string
    {
        return $this->proxyGateway;
    }

    public function setProxyGateway(string $proxyGateway): self
    {
        $this->proxyGateway = $proxyGateway;

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

    public function getMethodOverrides(): ?array
    {
        return $this->methodOverrides;
    }

    public function setMethodOverrides(?array $methodOverrides): self
    {
        $this->methodOverrides = $methodOverrides;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function setPrefix(?string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }
}
