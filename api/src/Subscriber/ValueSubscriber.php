<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Doctrine\ORM\Events;

class ValueSubscriber implements EventSubscriberInterface
{

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::prePersist
        ];
    }

    public function prePersist (LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if($object instanceof Value && $object->getAttribute()->getType() == 'object') {
            if($uuid = $object->getStringValue()) {
                $subObject = $this->entityManager->find(ObjectEntity::class, $uuid);
                $object->setStringValue(null);
                $object->addObject($subObject);
            } elseif ($object->getArrayValue()) {
                foreach($object->getArrayValue() as $uuid) {
                    $subObject = $this->entityManager->find(ObjectEntity::class, $uuid);
                    $object->addObject($subObject);
                }
                $object->setArrayValue([]);
                $object->setSimpleArrayValue([]);
            }
        }
    }
}
