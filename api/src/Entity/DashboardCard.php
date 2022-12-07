<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\DashboardCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use phpDocumentor\Reflection\Types\Integer;
use phpDocumentor\Reflection\Types\This;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *     "get"={"path"="/admin/dashboardCards/{id}"},
 *     "put"={"path"="/admin/dashboardCards/{id}"},
 *     "delete"={"path"="/admin/dashboardCards/{id}"}
 *  },
 *  collectionOperations={
 *     "get"={"path"="/admin/dashboardCards"},
 *     "post"={"path"="/admin/dashboardCards"}
 *  })
 * @ORM\Entity(repositoryClass=DashboardCardRepository::class)
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class)
 */
class DashboardCard
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
    private $id;

    /**
     * The name of the dashboard card.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * The description of the dashboard.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description;

    /**
     * The type of the card.
     *
     * @todo enum on schema etc
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $type;

    /**
     * The entity of the schema e.g. Gateway.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $entity;

    /**
     * The actual object that the card refers to, is loaded by subscriber on get requests.
     *
     * @Groups({"read"})
     */
    private $object;

    /**
     * The UUID of the object stored in Dashboard Card.
     *
     * @Groups({"read","write"})
     * @ORM\Column(type="uuid")
     */
    private Uuid $entityId;

    /**
     * @Groups({"read","write"})
     * @ORM\Column(type="integer")
     */
    private int $ordering = 1;

    public function __construct($object = null)
    {
       if($object && is_object($object)){
           //(method_exists($object,"getName" ) ?  : '')
           //(method_exists($object,"getDescription") ? ) : '');
           $this->setName($object->getName());
           $this->setDescription($object->getDescription());
           $this->setEntityId($object->getId());
           $class = get_class($object);
           $this->setEntity($class);

           // Lets set the type
           switch ($class) {
            case 'App\Entity\Entity':
                $this->setType('schema');
                break;
            default:
                echo "i equals 2";
                break;
            }
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getObject()
    {
        return $this->object;
    }

    public function setObject($object): self
    {
        $this->object = $object;

        return $this;
    }

    public function getEntityId(): Uuid
    {
        return $this->entityId;
    }

    public function setEntityId($entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getOrdering(): ?int
    {
        return $this->ordering;
    }

    public function setOrdering(int $ordering): self
    {
        $this->ordering = $ordering;

        return $this;
    }
}
