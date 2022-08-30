<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An entity that defines triggers for when to send an email.
 * todo: move this to an email plugin (see EmailService.php).
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *     "get"={"path"="/admin/email_triggers/{id}"},
 *     "put"={"path"="/admin/email_triggers/{id}"},
 *     "delete"={"path"="/admin/email_triggers/{id}"}
 *  },
 *  collectionOperations={
 *     "get"={"path"="/admin/email_triggers"},
 *     "post"={"path"="/admin/email_triggers"}
 *  })
 * @ORM\Entity(repositoryClass="App\Repository\EmailTriggerRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact",
 *     "endpoints.id": "exact"
 * })
 */
class EmailTrigger
{
    /**
     * @var UuidInterface The UUID identifier of this Entity.
     *
     * @Groups({"read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private UuidInterface $id;

    /**
     * @var string The name of this EmailTrigger.
     *
     * @Gedmo\Versioned
     * @Assert\Length(
     *     max = 255
     * )
     * @Assert\NotNull
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @var ?string The description of this EmailTrigger.
     *
     * @Gedmo\Versioned
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $description;

    /**
     * @var ?array Optional requirements from the api-call request for when this EmailTrigger should go off (and sends one or more emails).
     *
     * @Gedmo\Versioned
     * @Groups({"read","write"})
     * @ORM\Column(type="array", nullable=true, options={"default":null})
     */
    private ?array $request = [];

    /**
     * @var array The EndpointTriggeredEvent hooks for when this EmailTrigger should go off (and sends one or more emails).
     *
     * @Gedmo\Versioned
     * @Groups({"read","write"})
     * @ORM\Column(type="array")
     */
    private array $hooks = ['DEFAULT'];

    /**
     * The endpoints on which this EmailTrigger should go off (and sends one or more emails).
     *
     * @Groups({"read", "write"})
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=Endpoint::class)
     */
    private Collection $endpoints;

    /**
     * The EmailTemplates for the emails to send when this EmailTrigger is triggered.
     *
     * @Groups({"read", "write"})
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=EmailTemplate::class, mappedBy="triggers")
     */
    private Collection $templates;

    public function __construct()
    {
        $this->endpoints = new ArrayCollection();
        $this->templates = new ArrayCollection();
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

    public function getRequest(): ?array
    {
        return $this->request;
    }

    public function setRequest(?array $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getHooks(): ?array
    {
        return $this->hooks;
    }

    public function setHooks(array $hooks): self
    {
        $this->hooks = $hooks;

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
     * @return Collection|EmailTemplate[]
     */
    public function getTemplates(): Collection
    {
        return $this->templates;
    }

    public function addTemplate(EmailTemplate $template): self
    {
        if (!$this->templates->contains($template)) {
            $this->templates[] = $template;
            $template->addTrigger($this);
        }

        return $this;
    }

    public function removeTemplate(EmailTemplate $template): self
    {
        if ($this->templates->removeElement($template)) {
            $template->removeTrigger($this);
        }

        return $this;
    }
}
