<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
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
 * All properties that the entity Person holds.
 *
 * Entity Person exists of an id, a givenName, a additionalName, a familyName, one or more telephones, one or more addresses, one or more emails, one or more organisations and one or more contactLists.
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
 *      "get"={"path"="/admin/people/{id}"},
 *      "put"={"path"="/admin/people/{id}"},
 *      "delete"={"path"="/admin/people/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/people"},
 *      "post"={"path"="/admin/people"}
 *  })
 * )
 * @ORM\Entity(repositoryClass="App\Repository\PersonRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class)
 */
class Person
{
    /**
     * @var UuidInterface UUID of this person
     *
     * @Groups({"read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var string A specific commonground resource
     *
     * @example https://wrc.zaakonline.nl/organisations/16353702-4614-42ff-92af-7dd11c8eef9f
     *
     * @Gedmo\Versioned
     * @Assert\Url
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length(
     *     max = 255
     * )
     */
    private $resource;

    /**
     * @var string The full name of a person consisting of given and fammily name
     *
     * @example John Do
     *
     * @Groups({"read"})
     */
    private $name;

    /**
     * @var string The full name of a person consisting of fammily and given name
     *
     * @example Do, John
     *
     * @Groups({"read"})
     */
    private $formalName;

    /**
     * @var string Given name of this person
     *
     * @example John
     *
     * @Gedmo\Versioned
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255)
     * @Assert\Length(
     *     max = 255
     * )
     * @Assert\NotBlank
     */
    private $givenName;

    /**
     * @var string Additional name of this person
     *
     * @example von
     *
     * @Gedmo\Versioned
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length (
     *     max = 255
     * )
     */
    private $additionalName;

    /**
     * @var string Family name of this person
     *
     * @example Do
     *
     * @Gedmo\Versioned
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length (
     *     max = 255
     * )
     */
    private $familyName;

    /**
     * @var string Date of birth of this person
     *
     * @example 15-03-2000
     *
     * @Gedmo\Versioned
     * @Groups({"read", "write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $birthday;

    /**
     * @var string TIN, CIF, NIF or BSN
     *
     * @example 999994670
     *
     * @Gedmo\Versioned
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length (
     *     max = 255
     * )
     */
    private $taxID;

    /**
     * @var string Information about this person
     *
     * @example I like to dance !
     *
     * @Gedmo\Versioned
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length (
     *     max = 255
     * )
     */
    private $aboutMe;

    /**
     * @var Telephone Telephone of this person
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @ORM\ManyToMany(targetEntity="App\Entity\Telephone", fetch="EAGER", cascade={"persist"})
     * @MaxDepth(1)
     */
    private $telephones;

    /**
     * @var Address Addresses of this person
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @ORM\ManyToMany(targetEntity="App\Entity\Address", fetch="EAGER", cascade={"persist"})
     * @MaxDepth(1)
     */
    private $addresses;

//    /**
//     * @var Social Socials of this person
//     *
//     * @Assert\Valid()
//     *
//     * @Groups({"read", "write"})
//     * @ORM\ManyToMany(targetEntity="App\Entity\Social", fetch="EAGER", cascade={"persist"})
//     * @MaxDepth(1)
//     */
//    private $socials;

    /**
     * @var Email Emails of this person
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @ORM\ManyToMany(targetEntity="App\Entity\Email", inversedBy="people", cascade={"persist"})
     * @MaxDepth(1)
     */
    private $emails;

    /**
     * @var Organization Organisations of this person
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization", inversedBy="people", fetch="EAGER", cascade={"persist"})
     * @MaxDepth(1)
     */
    private $organization;

//    /**
//     * @var ContactList the contact lists this person owns
//     *
//     * @Assert\Valid()
//     *
//     * @Groups({"read", "write"})
//     * @ORM\OneToMany(targetEntity=ContactList::class, mappedBy="owner", cascade={"persist", "remove"})
//     * @MaxDepth(1)
//     */
//    private $ownedContactLists;
//
//    /**
//     * @var ContactList the contact lists this person is on
//     *
//     * @Assert\Valid()
//     *
//     * @Groups({"read", "write"})
//     * @ORM\ManyToMany(targetEntity=ContactList::class, mappedBy="people", cascade={"persist"})
//     * @MaxDepth(1)
//     */
//    private $contactLists;

    /**
     * @var string Base64 of the image
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private $personalPhoto;

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
     * @ApiFilter(SearchFilter::class, strategy="exact")
     */
    private $sourceOrganization;

    /**
     * @var string The gender of the person. **Male**, **Female**
     *
     * @example Male
     *
     * @Gedmo\Versioned
     * @Assert\Choice(
     *      {"Male","Female","X","Other"}
     * )
     *
     * @Assert\Length(
     *     max = 255
     * )
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"read","write"})
     */
    private $gender;

    /**
     * @var Address Birthplace of this person
     *
     * @Assert\Valid()
     *
     * @Groups({"read", "write"})
     * @ORM\OneToOne(targetEntity=Address::class, cascade={"persist", "remove"})
     * @MaxDepth(1)
     */
    private $birthplace;

    /**
     * @var string The marital status of the person. **MARRIED_PARTNER**, **SINGLE**, **DIVORCED**, **WIDOW**
     *
     * @example Married
     *
     * @Gedmo\Versioned
     * @Assert\Choice(
     *      {"MARRIED_PARTNER","SINGLE","DIVORCED","WIDOW"}
     * )
     *
     * @Assert\Length(
     *     max = 255
     * )
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"read","write"})
     */
    private $maritalStatus;

    /**
     * @var string The primary language of the person.
     *
     * @example Dutch
     *
     * @Gedmo\Versioned
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     */
    private $primaryLanguage;

    /**
     * @var array The speaking languages of the person.
     *
     * @example "English", "NLD"
     *
     * @Gedmo\Versioned
     * @ORM\Column(type="array", nullable=true)
     * @Groups({"read","write"})
     */
    private $speakingLanguages = [];

    /**
     * @var string The contact preference of the person.
     *
     * @example Whatsapp
     *
     * @Gedmo\Versioned
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"read","write"})
     * @Assert\Length(
     *     max = 255
     * )
     */
    private $contactPreference;

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
//        $this->contactLists = new ArrayCollection();
//        $this->ownedContactLists = new ArrayCollection();
        $this->emails = new ArrayCollection();
//        $this->socials = new ArrayCollection();
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

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(?string $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    public function getName(): ?string
    {
        if ($this->getAdditionalName()) {
            return $this->givenName.' '.$this->additionalName.' '.$this->familyName;
        }

        return $this->givenName.' '.$this->familyName;
    }

    public function getFormalName(): ?string
    {
        if ($this->getAdditionalName()) {
            return $this->familyName.', '.$this->givenName.' '.$this->additionalName;
        }

        return $this->familyName.', '.$this->givenName;
    }

    public function getGivenName(): ?string
    {
        return $this->givenName;
    }

    public function setGivenName(?string $givenName): self
    {
        $this->givenName = $givenName;

        return $this;
    }

    public function getAdditionalName(): ?string
    {
        return $this->additionalName;
    }

    public function setAdditionalName(?string $additionalName): self
    {
        $this->additionalName = $additionalName;

        return $this;
    }

    public function getFamilyName(): ?string
    {
        return $this->familyName;
    }

    public function setFamilyName(?string $familyName): self
    {
        $this->familyName = $familyName;

        return $this;
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeInterface $birthday): self
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getTaxID(): ?string
    {
        return $this->taxID;
    }

    public function setTaxID(?string $taxID): self
    {
        $this->taxID = $taxID;

        return $this;
    }

    public function getAboutMe(): ?string
    {
        return $this->aboutMe;
    }

    public function setAboutMe(?string $aboutMe): self
    {
        $this->aboutMe = $aboutMe;

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

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

//    /**
//     * @return Collection|ContactList[]
//     */
//    public function getOwnedContactLists(): Collection
//    {
//        return $this->ownedContactLists;
//    }
//
//    public function addOwnedContactList(ContactList $ownedContactList): self
//    {
//        if (!$this->ownedContactLists->contains($ownedContactList)) {
//            $this->ownedContactLists[] = $ownedContactList;
//            $ownedContactList->setOwner($this);
//        }
//
//        return $this;
//    }
//
//    public function removeOwnedContactList(ContactList $ownedContactList): self
//    {
//        if ($this->ownedContactLists->removeElement($ownedContactList)) {
//            // set the owning side to null (unless already changed)
//            if ($ownedContactList->getOwner() === $this) {
//                $ownedContactList->setOwner(null);
//            }
//        }
//
//        return $this;
//    }
//
//    /**
//     * @return Collection|ContactList[]
//     */
//    public function getContactLists(): Collection
//    {
//        return $this->contactLists;
//    }
//
//    public function addContactList(ContactList $contactList): self
//    {
//        if (!$this->contactLists->contains($contactList)) {
//            $this->contactLists[] = $contactList;
//            $contactList->addPerson($this);
//        }
//
//        return $this;
//    }
//
//    public function removeContactList(ContactList $contactList): self
//    {
//        if ($this->contactLists->removeElement($contactList)) {
//            $contactList->removePerson($this);
//        }
//
//        return $this;
//    }

    public function getPersonalPhoto(): ?string
    {
        return $this->personalPhoto;
    }

    public function setPersonalPhoto(?string $personalPhoto): self
    {
        $this->personalPhoto = $personalPhoto;

        return $this;
    }

    public function getSourceOrganization(): ?string
    {
        return $this->sourceOrganization;
    }

    public function setSourceOrganization(string $sourceOrganization): self
    {
        $this->sourceOrganization = $sourceOrganization;

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function getBirthplace(): ?Address
    {
        return $this->birthplace;
    }

    public function setBirthplace(?Address $birthplace): self
    {
        $this->birthplace = $birthplace;

        return $this;
    }

    public function getMaritalStatus(): ?string
    {
        return $this->maritalStatus;
    }

    public function setMaritalStatus(?string $maritalStatus): self
    {
        $this->maritalStatus = $maritalStatus;

        return $this;
    }

    public function getPrimaryLanguage(): ?string
    {
        return $this->primaryLanguage;
    }

    public function setPrimaryLanguage(?string $primaryLanguage): self
    {
        $this->primaryLanguage = $primaryLanguage;

        return $this;
    }

    public function getSpeakingLanguages(): ?array
    {
        return $this->speakingLanguages;
    }

    public function setSpeakingLanguages(?array $speakingLanguages): self
    {
        $this->speakingLanguages = $speakingLanguages;

        return $this;
    }

    public function getContactPreference(): ?string
    {
        return $this->contactPreference;
    }

    public function setContactPreference(?string $contactPreference): self
    {
        $this->contactPreference = $contactPreference;

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
}
