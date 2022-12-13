<?php

namespace App\Subscriber;

use App\Entity\ObjectEntity;
use App\Entity\Value;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
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

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $value = $args->getObject();
        if ($value instanceof Value && $value->getAttribute()->getType() == 'object') {
            if ($value->getArrayValue()) {
                foreach ($value->getArrayValue() as $uuid) {
                    $subObject = $this->entityManager->find(ObjectEntity::class, $uuid);
                    $value->addObject($subObject);
                }
                $value->setArrayValue([]);
            } elseif ($uuid = $value->getStringValue() && Uuid::isValid($value->getStringValue())) {
                $subObject = $this->entityManager->find(ObjectEntity::class, $uuid);
                $value->addObject($subObject);
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
