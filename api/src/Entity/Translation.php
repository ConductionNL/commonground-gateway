<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\TranslationRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass=TranslationRepository::class)
 */
class Translation
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
     * @var string The table of this Translation.
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $translationTable;

    /**
     * @var string From
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $comesFrom;

    /**
     * @var string To
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $goesTo;

    /**
     * @var string Language (ISO Code)
     *
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $language;

    public function getId()
    {
        return $this->id;
    }

    public function getTranslationTable(): ?string
    {
        return $this->translationTable;
    }

    public function setTranslationTable(string $translationTable): self
    {
        $this->translationTable = $translationTable;

        return $this;
    }

    public function getComesFrom(): ?string
    {
        return $this->comesFrom;
    }

    public function setComesFrom(string $comesFrom): self
    {
        $this->comesFrom = $comesFrom;

        return $this;
    }

    public function getGoesTo(): ?string
    {
        return $this->goesTo;
    }

    public function setGetsTo(string $goesTo): self
    {
        $this->goesTo = $goesTo;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $language;

        return $this;
    }
}
