<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\HandlerRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Json;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass=HandlerRepository::class)
 */
class Handler
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
    private $id;

    /**
     * @var string The name of this Handler.
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @var ?string The description of this Handler.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @var int The sequence of this Handler.
     *
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer")
     */
    private $sequence;

    /**
     * @var json The conditions of this Handler.
     *
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="json")
     */
    private $conditions = [];

    /**
     * @var ?array The translations in.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $translationsIn = [];

    /**
     * @var ?array The mapping in.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $mappingIn = [];

    /**
     * @var ?array The skeleton in.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $skeletonIn = [];

    /**
     * @var Entity The object of this Handler.
     *
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="handlers")
     */
    private $object;

    /**
     * @var ?array The skeleton out.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $skeletonOut = [];

    /**
     * @var ?array The mapping out.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $mappingOut = [];

    /**
     * @var ?array The translations out.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $translationsOut = [];

    /**
     * @var string The tamplate type of this Handler.
     *
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $templateType;

    /**
     * @var ?string The template of this Handler.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private $template;

    /**
     * @var Endpoint The endpoint of this Handler.
     *
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Endpoint::class, inversedBy="handlers")
     * @ORM\JoinColumn(nullable=false)
     */
    private $endpoint;

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

    public function getConditions(): ?array
    {
        return $this->conditions;
    }

    public function setConditions(array $conditions): self
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

    public function getObject(): ?Entity
    {
        return $this->object;
    }

    public function setObject(?Entity $object): self
    {
        $this->object = $object;

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

    public function getEndpoint(): ?Endpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(?Endpoint $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }
}
