<?php

namespace App\Entity;

use App\Repository\TranslationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TranslationRepository::class)
 */
class Translation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $translationTable;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $translateFrom;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $translateTo;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $language;

    public function getId(): ?int
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

    public function getTranslateFrom(): ?string
    {
        return $this->translateFrom;
    }

    public function setTranslateFrom(string $translateFrom): self
    {
        $this->translateFrom = $translateFrom;

        return $this;
    }

    public function getTranslateTo(): ?string
    {
        return $this->translateTo;
    }

    public function setTranslateTo(string $translateTo): self
    {
        $this->translateTo = $translateTo;

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
