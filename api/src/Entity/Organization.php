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
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * All properties that the entity Organisation holds.
 *
 * Entity Organisation exists of an id, a name, a description, a kvk number, one or more telephones, one or more addresses, one or more emails, one or more persons and one or more contactLists.
 *
 * @author Ruben van der Linde <ruben@conduction.nl>
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Entity
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/organizations/{id}"},
 *      "put"={"path"="/admin/organizations/{id}"},
 *      "delete"={"path"="/admin/organizations/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/organizations"},
 *      "post"={"path"="/admin/organizations"}
 *  })
 * )
 * @ORM\HasLifecycleCallbacks
 * @ORM\Entity(repositoryClass="App\Repository\OrganizationRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "type": "exact",
 *     "name": "exact"
 *     })
 */
class Organization
{
    /**
     * @var UuidInterface UUID of this organisation
     *
     *
     * @Groups({"read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var string Name of this organisation
     *
     * @example Ajax
     *
     * @Gedmo\Versioned
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255)
     * @Assert\Length(
     *     max = 255
     * )
     * @Assert\NotBlank
     */
    private $name;

    /**
     * @var string Description of this organisation
     *
     * @example Ajax is a dutch soccer club
     *
     * @Gedmo\Versioned
     * @Groups({"read", "write"})
     * @ORM\Column(type="text", length=2550, nullable=true)
     *
     * @Assert\Length(
     *     max = 2550
     * )
     */
    private $description;

    /**
     * @var string Type of this organisation
     *
     * @example Township
     *
     * @Gedmo\Versioned
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length(
     *     max = 255
     * )
     */
    private $type;

    /**
     * @var string Chamber Of Comerce number of this organisation
     *
     * @Gedmo\Versioned
     *
     * @example 123456
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=15, nullable=true)
     * @Assert\Length(
     *     max = 15
     * )
     */
    private $coc;

    /**
     * @var string Value added tax id of this organisation (btw)
     *
     * @Gedmo\Versioned
     *
     * @example 123456
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=15, nullable=true)
     * @Assert\Length(
     *     max = 15
     * )
     */
    private $vatID;

    /**
     * @param Organization $parentOrganization The larger organization that this organization is a subOrganization of.
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization", inversedBy="subOrganizations", cascade={"persist"})
     */
    private $parentOrganization;

    /**
     * @var ArrayCollection|Organization[] The sub-organizations of which this organization is the parent organization.
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity="App\Entity\Organization", mappedBy="parentOrganization", cascade={"persist"})
     */
    private $subOrganizations;

    /**
     * @var Telephone Telephone of this organisation
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @ORM\ManyToMany(targetEntity="App\Entity\Telephone", fetch="EAGER", cascade={"persist"})
     * @MaxDepth(1)
     */
    private $telephones;
    /**
     * @var Address Address of this organisation
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @ORM\ManyToMany(targetEntity="App\Entity\Address", fetch="EAGER", cascade={"persist"})
     * @MaxDepth(1)
     */
    private $addresses;

//    /**
//     * @var Social Socials of this organisation
//     *
//     * @Assert\Valid()
//     *
//     * @Groups({"read", "write"})
//     * @ORM\ManyToMany(targetEntity="App\Entity\Social", fetch="EAGER", cascade={"persist"})
//     * @MaxDepth(1)
//     */
//    private $socials;

    /**
     * @var Email Email of this organisation
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @ORM\ManyToMany(targetEntity="App\Entity\Email", inversedBy="organizations", cascade={"persist"})
     * @MaxDepth(1)
     */
    private $emails;

    /**
     * @var Person Person of this organisation
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @ORM\OneToMany(targetEntity="App\Entity\Person", mappedBy="organization", cascade={"persist"})
     * @MaxDepth(1)
     */
    private $persons;

//    /**
//     * @var ContactList Contact list of this organisation
//     *
//     * @Assert\Valid()
//     *
//     * @ORM\ManyToMany(targetEntity="App\Entity\ContactList", mappedBy="organizations", cascade={"persist"})
//     * @MaxDepth(1)
//     */
//    private $contactLists;

    /**
     * @var string The WRC url of the organization that owns this group
     *
     * @example 002851234
     *
     * @Gedmo\Versioned
     * @Assert\Url
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length(
     *     max = 255
     * )
     *
     * @ApiFilter(SearchFilter::class, strategy="exact")
     */
    private $sourceOrganization;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    public function __construct()
    {
        $this->telephones = new ArrayCollection();
        $this->addresses = new ArrayCollection();
        $this->persons = new ArrayCollection();
//        $this->contactLists = new ArrayCollection();
        $this->emails = new ArrayCollection();
//        $this->socials = new ArrayCollection();
        $this->subOrganizations = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId(Uuid $id): self
    {
        $this->id = $id;

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

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCoc(): ?string
    {
        return $this->coc;
    }

    public function setCoc(?string $coc): self
    {
        $this->coc = $coc;

        return $this;
    }

    public function getVatID(): ?string
    {
        return $this->vatID;
    }

    public function setVatID(?string $vatID): self
    {
        $this->vatID = $vatID;

        return $this;
    }

    public function getParentOrganization(): ?self
    {
        return $this->parentOrganization;
    }

    public function setParentOrganization(?self $parentOrganization): self
    {
        $this->parentOrganization = $parentOrganization;

        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getSubOrganizations(): Collection
    {
        return $this->subOrganizations;
    }

    public function addSubOrganization(self $subOrganization): self
    {
        if (!$this->subOrganizations->contains($subOrganization)) {
            $this->subOrganizations[] = $subOrganization;
            $subOrganization->setParentOrganization($this);
        }

        return $this;
    }

    public function removeSubOrganization(self $subOrganization): self
    {
        if ($this->subOrganizations->contains($subOrganization)) {
            $this->subOrganizations->removeElement($subOrganization);
            // set the owning side to null (unless already changed)
            if ($subOrganization->getParentOrganization() === $this) {
                $subOrganization->setParentOrganization(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Telephone[]
     */
    public function getTelephones()
    {
        return $this->telephones;
    }

    public function addTelephone(Telephone $telephone): self
    {
        if (!$this->telephones->contains($telephone)) {
            $this->telephones[] = $telephone;
        }

        return $this;
    }

    public function removeTelephone(Telephone $telephone): self
    {
        if ($this->telephones->contains($telephone)) {
            $this->telephones->removeElement($telephone);
        }

        return $this;
    }

    /**
     * @return Collection|Address[]
     */
    public function getAddresses()
    {
        return $this->addresses;
    }

    public function addAddress(Address $address): self
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses[] = $address;
        }

        return $this;
    }

    public function removeAddress(Address $address): self
    {
        if ($this->addresses->contains($address)) {
            $this->addresses->removeElement($address);
        }

        return $this;
    }

//    /**
//     * @return Collection|Social[]
//     */
//    public function getSocials(): Collection
//    {
//        return $this->socials;
//    }
//
//    public function addSocial(Social $social): self
//    {
//        if (!$this->socials->contains($social)) {
//            $this->socials[] = $social;
//        }
//
//        return $this;
//    }
//
//    public function removeSocial(Social $social): self
//    {
//        if ($this->socials->contains($social)) {
//            $this->socials->removeElement($social);
//        }
//
//        return $this;
//    }

    /**
     * @return Collection|Email[]
     */
    public function getEmails()
    {
        return $this->emails;
    }

    public function addEmail(Email $email): self
    {
        if (!$this->emails->contains($email)) {
            $this->emails[] = $email;
        }

        return $this;
    }

    public function removeEmail(Email $email): self
    {
        if ($this->emails->contains($email)) {
            $this->emails->removeElement($email);
        }

        return $this;
    }

    /**
     * @return Collection|Person[]
     */
    public function getPersons()
    {
        return $this->persons;
    }

    public function addPerson(Person $person): self
    {
        if (!$this->persons->contains($person)) {
            $this->persons[] = $person;
            $person->setOrganization($this);
        }

        return $this;
    }

    public function removePerson(Person $person): self
    {
        if ($this->persons->contains($person)) {
            $this->persons->removeElement($person);
            // set the owning side to null (unless already changed)
            if ($person->getOrganization() === $this) {
                $person->setOrganization(null);
            }
        }

        return $this;
    }

//    /**
//     * @return Collection|ContactList[]
//     */
//    public function getContactLists()
//    {
//        return $this->contactLists;
//    }
//
//    public function addContactList(ContactList $contactList): self
//    {
//        if (!$this->contactLists->contains($contactList)) {
//            $this->contactLists[] = $contactList;
//            $contactList->addOrganization($this);
//        }
//
//        return $this;
//    }
//
//    public function removeContactList(ContactList $contactList): self
//    {
//        if ($this->contactLists->contains($contactList)) {
//            $this->contactLists->removeElement($contactList);
//            $contactList->removeOrganization($this);
//        }
//
//        return $this;
//    }

    public function getSourceOrganization(): ?string
    {
        return $this->sourceOrganization;
    }

    public function setSourceOrganization(?string $sourceOrganization): self
    {
        $this->sourceOrganization = $sourceOrganization;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(\DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateModified(): ?\DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(\DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }
}
