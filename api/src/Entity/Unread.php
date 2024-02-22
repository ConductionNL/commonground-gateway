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
use App\Repository\UnreadRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity is used for marking objects as unread. When the query param dateRead=true is used this table is checked.
 * When an object + userId of the current user is present in this table the value dateRead in the response will be null.
 */
#[
    ApiResource(
        operations: [
            new Get(          "/admin/unreads/{id}"),
            new Put(          "/admin/unreads/{id}"),
            new Delete(       "/admin/unreads/{id}"),
            new GetCollection("/admin/unreads"),
            new Post(         "/admin/unreads")
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
    ORM\Entity(repositoryClass: UnreadRepository::class),
    ApiFilter(BooleanFilter::class),
    ApiFilter(OrderFilter::class),
    ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL),
    ApiFilter(
        SearchFilter::class,
        properties: [
            'userId'    => 'exact',
            'object.id' => 'exact'
        ]
    ),
]
class Unread
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     */
    #[
        Groups(['read', 'write']),
        Assert\Uuid,
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
     * @var string|null The userId of the user that did the request.
     */
    #[
        Groups(['read', 'write']),
        Assert\Length(max: 255),
        ORM\Column(
            type: 'string',
            length: 255,
            nullable: true
        )
    ]
    private ?string $userId = null;

    /**
     * @var ObjectEntity The object that has been set to unread.
     */
    #[
        Groups(['read', 'write']),
        MaxDepth(1),
        ORM\JoinColumn(nullable: false),
        ORM\ManyToOne(
            targetEntity: ObjectEntity::class
        )
    ]
    private ObjectEntity $object;

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getObject(): ?ObjectEntity
    {
        return $this->object;
    }

    public function setObject(?ObjectEntity $object): self
    {
        $this->object = $object;

        return $this;
    }
}
