<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\CronjobRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An entity that holds a cronjob.
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *     "get"={"path"="/admin/cronjobs/{id}"},
 *     "put"={"path"="/admin/cronjobs/{id}"},
 *     "delete"={"path"="/admin/cronjobs/{id}"}
 *  },
 *  collectionOperations={
 *     "get"={"path"="/admin/cronjobs"},
 *     "post"={"path"="/admin/cronjobs"}
 *  })
 * @ORM\Entity(repositoryClass=CronjobRepository::class)
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class)
 */
class Cronjob
{
    /**
     * @var UuidInterface The UUID identifier of this Cronjob.
     *
     * @Groups({"read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var string The name of this Cronjob
     *
     * @Gedmo\Versioned
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @var string|null The description of this Cronjob
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
     * @var string The crontab that determines the interval https://crontab.guru/
     *             defaulted at every 5 minutes * / 5  *  *  *  *
     *
     * @Assert\Regex(
     *     pattern="/(^((\*\/)?([0-5]?[0-9])((\,|\-|\/)([0-5]?[0-9]))*|\*)\s+((\*\/)?((2[0-3]|1[0-9]|[0-9]|00))((\,|\-|\/)(2[0-3]|1[0-9]|[0-9]|00))*|\*)\s+((\*\/)?([1-9]|[12][0-9]|3[01])((\,|\-|\/)([1-9]|[12][0-9]|3[01]))*|\*)\s+((\*\/)?([1-9]|1[0-2])((\,|\-|\/)([1-9]|1[0-2]))*|\*|(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|des))\s+((\*\/)?[0-6]((\,|\-|\/)[0-6])*|\*|00|(sun|mon|tue|wed|thu|fri|sat))\s*$)|@(annually|yearly|monthly|weekly|daily|hourly|reboot)/",
     *     match=true,
     *     message="This is an invalid crontab, see https://crontab.guru/ to create an interval"
     * )
     * @Gedmo\Versioned
     * @Groups({"read","write"})
     * @ORM\Column(type="string")
     */
    private string $crontab = '*/5 * * * *';

    /**
     * @var array The actions that put on the stack by the crontab.
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array")
     */
    private array $throws = [];

    /**
     * @var array|null The optional data array of this Cronjob
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $data = [];

    /**
     * @var Datetime The last run of this Cronjob
     *
     * @Groups({"read"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastRun;

    /**
     * @var Datetime The next run of this Cronjob
     *
     * @Groups({"read"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $nextRun;

    /**
     * @var ?bool true if action should be ran
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true, options={"default": true})
     */
    private ?bool $isEnabled = true;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private DateTimeInterface $dateCreated;

    /**
     * @var Datetime The moment this resource was last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private DateTimeInterface $dateModified;

    public function __construct(
        $action = false
    )
    {
        if($action){
            $this->setName($action->getName());
            $this->setDescription($action->getDescription());
            $this->setThrows([reset($action->getListens()->first())]);
        }
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

    public function getCrontab(): ?string
    {
        return $this->crontab;
    }

    public function setCrontab(string $crontab): self
    {
        $this->crontab = $crontab;

        return $this;
    }

    public function getThrows(): ?array
    {
        return $this->throws;
    }

    public function setThrows(array $throws): self
    {
        $this->throws = $throws;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getLastRun(): ?DateTimeInterface
    {
        return $this->lastRun;
    }

    public function setLastRun(?DateTimeInterface $lastRun): self
    {
        $this->lastRun = $lastRun;

        return $this;
    }

    public function getNextRun(): ?DateTimeInterface
    {
        return $this->nextRun;
    }

    public function setNextRun(?DateTimeInterface $nextRun): self
    {
        $this->nextRun = $nextRun;

        return $this;
    }

    public function getIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getDateCreated(): ?DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?DateTimeInterface $dateCreated): self
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
