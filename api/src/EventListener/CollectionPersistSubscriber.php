<?php

namespace App\EventListener;

use App\Entity\CollectionEntity;
use App\Service\OasParserService;
use App\Service\ParseDataService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class CollectionPersistSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private OasParserService $oasParser;
    private ParseDataService $dataService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ParseDataService $dataService
    ) {
        $this->entityManager = $entityManager;
        $this->oasParser = new OasParserService($entityManager);
        $this->dataService = $dataService;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        // return [];
        return [
            Events::postPersist,
        ];
    }

    // callback methods must be called exactly like the events they listen to;
    // they receive an argument of type LifecycleEventArgs, which gives you access
    // to both the entity object of the event and the entity manager itself
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if (!$object instanceof CollectionEntity) {
            return;
        }

        $collection = $this->entityManager->getRepository(CollectionEntity::class)->find($object->getId());

        if ($collection->getSyncedAt()) {
            return;
        }
        if (!$collection->getLocationOAS()) {
            return;
        }
        if (!$collection->getAutoLoad()) {
            return;
        }

        $collection = $this->oasParser->parseOas($collection);
        $collection->getLoadTestData() ? $this->dataService->loadData($collection->getTestDataLocation(), $collection->getLocationOAS(), true) : null;

        $collection = $this->entityManager->getRepository(CollectionEntity::class)->find($object->getId());
        $collection->setSyncedAt(new \DateTime("now"));
        $this->entityManager->persist($collection);
        $this->entityManager->flush($collection);
    }
}
