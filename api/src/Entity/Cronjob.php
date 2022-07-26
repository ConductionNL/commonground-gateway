<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Cron\CronExpression;
use Setono\CronExpressionBundle\Doctrine\DBAL\Types\CronExpressionType;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use App\Repository\CronjobRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
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
     * @var CronExpression The crontab that determines the interval https://crontab.guru/
     * defaulted at  every 5 minutes * / 5  *  *  *  *
     *
     * @Gedmo\Versioned
     * @Groups({"read","write"})
     * @ORM\Column(type="cron_expression")
     */
    private CronExpression $crontab;

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
     * @ORM\Column(type="datetime")
     */
    private $lastRun;

    /**
     * @var Datetime The next run of this Cronjob
     *
     * @Groups({"read"})
     * @ORM\Column(type="datetime")
     */
    private $nextRun;

//    public function __construct()
//    {
//        $this->crontab = CronExpression::factory("@daily");
//    }

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

    /**
     * @return CronExpression|null
     */
    public function getCrontab(): ?CronExpression
    {
        return $this->crontab;
    }

    /**
     * @param CronExpression $crontab
     * @return Cronjob
     */
    public function setCrontab(CronExpression $crontab): self
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

    public function setLastRun(DateTimeInterface $lastRun): self
    {
        $this->lastRun = $lastRun;

        return $this;
    }

    public function getNextRun(): ?DateTimeInterface
    {
        return $this->nextRun;
    }

    public function setNextRun(DateTimeInterface $nextRun): self
    {
        $this->nextRun = $nextRun;

        return $this;
    }
}
