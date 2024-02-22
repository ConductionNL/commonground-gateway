<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\DashboardCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dashboard cards for display on dashboards.
 */
#[
    ApiResource(
        operations: [
            new Get("/admin/dashboardCards/{id}"),
            new Put("/admin/dashboardCards/{id}"),
            new Delete("/admin/dashboardCards/{id}"),
            new GetCollection("/admin/dashboardCards"),
            new Post("/admin/dashboardCards")
        ],
        normalizationContext: [
            'groups' => ['read'],
            'enable_max_depth' => true
        ],
        denormalizationContext: [
            'groups' => ['write'],
            'enable_max_depth' => true
        ],
    ),
    ORM\Entity(repositoryClass: DashboardCardRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(SearchFilter::class, properties: ['name' => 'exact'])
]
class DashboardCard
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     */
    #[
        Assert\Uuid,
        Groups(['read', 'write']),
        ORM\Id,
        ORM\Column(
            type: 'uuid',
            unique: true
        ),
        ORM\GeneratedValue(strategy: 'CUSTOM'),

        ORM\CustomIdGenerator(class: UuidGenerator::class)
    ]
    private UuidInterface $id;

    /**
     * @var string The name of this Application.
     */
    #[
        Assert\Length(max: 255),
        Assert\NotNull,
        Groups(['read', 'write']),
        ORM\Column(
            type: 'string',
            length: 255
        ),
        Gedmo\Versioned
    ]
    private string $name;

    /**
     * @var string|null A description of this Application.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'text',
            nullable: true
        )
    ]
    private ?string $description = null;

    /**
     * @var string The type of the card.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'string',
            length: 255
        )
    ]
    private string $type;

    /**
     * @var string The entity of the schema e.g. Gateway.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'string',
            length: 255
        )
    ]
    private string $entity;

    /**
     * @var mixed The actual object that the card refers to, is loaded by subscriber on get requests.
     */
    #[
        Groups(['read'])
    ]
    private mixed $object;

    /**
     * @var UuidInterface The UUID of the object stored in Dashboard Card.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'uuid'
        )
    ]
    private UuidInterface $entityId;

    /**
     * @var int priority in the ordering of dashboard cards.
     */
    #[
        Groups(['read', 'write']),
        ORM\Column(
            type: 'integer'
        )
    ]
    private int $ordering = 1;

    public function __construct($object = null)
    {
        if ($object && is_object($object)) {
            //(method_exists($object,"getName" ) ?  : '')
            //(method_exists($object,"getDescription") ? ) : '');
            $this->setName($object->getName());
            $this->setDescription($object->getDescription());
            $this->setEntityId($object->getId());
            $class = get_class($object);
            $this->setEntity($class);

            // Let's set the type
            switch ($class) {
                case 'App\Entity\Action':
                    $this->setType('action');
                    break;
                case 'App\Entity\Application':
                    $this->setType('application');
                    break;
                case 'App\Entity\CollectionEntity':
                    $this->setType('collection');
                    break;
                case 'App\Entity\Cronjob':
                    $this->setType('cronjob');
                    break;
                case 'App\Entity\Endpoint':
                    $this->setType('endpoint');
                    break;
                case 'App\Entity\Entity':
                    $this->setType('schema');
                    break;
                case 'App\Entity\Gateway':
                    $this->setType('gateway');
                    break;
                case 'App\Entity\Mapping':
                    $this->setType('mapping');
                    break;
                case 'App\Entity\ObjectEntity':
                    $this->setType('object');
                    break;
                case 'App\Entity\Organization':
                    $this->setType('organization');
                    break;
//                case 'App\Entity\SecurityGroup':
//                    $this->setType('securityGroup');
//                    break;
//                case 'App\Entity\User':
//                    $this->setType('user');
//                    break;
                default:
                    $this->setType('Unknown class type: '.$class);
                    break;
            }
        }
    }

    public function __toString()
    {
        return $this->getName();
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
