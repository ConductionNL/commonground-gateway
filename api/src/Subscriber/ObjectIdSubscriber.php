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

class ObjectIdSubscriber implements EventSubscriberInterface
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
                throw new Exception("Found more than one ObjectEntity with id = '$uuid' or with a source with sourceId = '$uuid'");
            }
        }
        if (!$subObject instanceof ObjectEntity) {
            throw new Exception('No object found with uuid: '.$uuid);
        }

        return $subObject;
    }

    public function preUpdate(LifecycleEventArgs $value): void
    {
        $object = $value->getObject();

        // Let check if it is a new entity that has an id
        if ($object instanceof ObjectEntity && $object->getId() && !$object->getDateCreated() ) {
            // Stack connected objects
            $values = $object->getObjectValues();
            $id = $object->getId();
            $object->clearAllValues();

            $this->entityManager->persist($object);
            $this->entityManager->flush();
            $this->entityManager->refresh($object);
            $object->setId($id);
            $this->entityManager->persist($object);
            $this->entityManager->flush();
            $this->entityManager->refresh($object);
            $object->setValues($values);
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
