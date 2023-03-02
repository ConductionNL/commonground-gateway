<?php

namespace App\Subscriber;

use App\Entity\ObjectEntity;
use App\Entity\Value;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class ValueSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $valueSubscriberLogger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $valueSubscriberLogger;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::prePersist,
            Events::preRemove,
        ];
    }

    public function getSubObject(string $uuid, Value $valueObject): ?ObjectEntity
    {
        $parentObject = $valueObject->getObjectEntity();
        if (!$subObject = $this->entityManager->find(ObjectEntity::class, $uuid)) {
            try {
                $subObject = $this->entityManager->getRepository(ObjectEntity::class)->findByAnyId($uuid);
            } catch (NonUniqueResultException $exception) {
                $this->logger->error("Found more than one ObjectEntity with uuid = '$uuid' or with a synchronization with sourceId = '$uuid'");

                return null;
            }
        }
        if (!$subObject instanceof ObjectEntity) {
            $this->logger->error(
                "No subObjectEntity found with uuid ($uuid) or with a synchronization with sourceId = uuid for ParentObject",
                [
                    'uuid'         => $uuid,
                    'ParentObject' => [
                        'id'     => $parentObject->getId()->toString(),
                        'entity' => $parentObject->getEntity() ? [
                            'id'   => $parentObject->getEntity()->getId()->toString(),
                            'name' => $parentObject->getEntity()->getName(),
                        ] : null,
                        '_self' => $parentObject->getSelf(),
                        'name'  => $parentObject->getName(),
                    ],
                ]
            );

            return null;
        }

        return $subObject;
    }

    public function preUpdate(LifecycleEventArgs $value): void
    {
        $valueObject = $value->getObject();

        if ($valueObject instanceof Value && $valueObject->getAttribute()->getType() == 'object') {
            if ($valueObject->getArrayValue()) {
                foreach ($valueObject->getArrayValue() as $uuid) {
                    $subObject = $this->getSubObject($uuid, $valueObject);
                    $subObject && $valueObject->addObject($subObject);
                }
                $valueObject->setArrayValue([]);
            } elseif (($uuid = $valueObject->getStringValue()) && Uuid::isValid($valueObject->getStringValue())) {
                $subObject = $this->getSubObject($uuid, $valueObject);
                $subObject && $valueObject->addObject($subObject);
            }
        }
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->preUpdate($args);
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
    }
}
