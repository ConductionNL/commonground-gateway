<?php

namespace App\Subscriber;

use App\Entity\ObjectEntity;
use App\Entity\Value;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;
use Ramsey\Uuid\Uuid;

class ValueSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::prePersist,
            Events::preRemove,
        ];
    }

    public function getSubObject(string $uuid): ?ObjectEntity
    {
        if (!$subObject = $this->entityManager->find(ObjectEntity::class, $uuid)) {
            try {
                $subObject = $this->entityManager->getRepository(ObjectEntity::class)->findByAnyId($uuid);
            } catch (NonUniqueResultException $exception) {
                throw new Exception('Found more than one ObjectEntity with this externalId or id: '.$uuid);
            }
        }
        if (!$subObject instanceof ObjectEntity) {
            throw new Exception('No object found with uuid: '.$uuid);
        }

        return $subObject;
    }

    public function preUpdate(LifecycleEventArgs $value): void
    {
        $valueObject = $value->getObject();

        if ($valueObject instanceof Value && $valueObject->getAttribute()->getType() == 'object') {
            if ($valueObject->getArrayValue()) {
                foreach ($valueObject->getArrayValue() as $uuid) {
                    $subObject = $this->getSubObject($uuid);
                    $subObject && $valueObject->addObject($subObject);
                }
                $valueObject->setArrayValue([]);
            } elseif (($uuid = $valueObject->getStringValue()) && Uuid::isValid($valueObject->getStringValue())) {
                $subObject = $this->getSubObject($uuid);
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
