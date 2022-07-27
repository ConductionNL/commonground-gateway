<?php

namespace App\EventListener;

use App\Entity\CollectionEntity;
use App\Entity\ObjectEntity;
use App\Service\OasParserService;
use App\Service\ParseDataService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\Common\Collections\Collection;
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

        // Prevent Collection from being loaded again, if a Collection is duplicated and is not synced yet, it is safe to remove
        $duplicatedCollections = $this->entityManager->getRepository(CollectionEntity::class)->findBy(['name' => $object->getName()]);
        if (count($duplicatedCollections) > 1) {
            foreach ($duplicatedCollections as $collectionToRemove) {
                if (!$collectionToRemove->getSyncedAt() && $collectionToRemove->getId() == $object->getId()) {
                    $this->entityManager->remove($collectionToRemove);
                    $this->entityManager->flush();

                    return;
                }
            }
        }

        $collection = $this->entityManager->getRepository(CollectionEntity::class)->find($object->getId());

        if ($collection->getSyncedAt()) {
            if ($collection->getPrefix() && $collection->getEndpoints()) {
                $this->updateEndpointPaths($collection->getEndpoints(), $collection->getPrefix());
            }
            return;
        }
        if (!$collection->getLocationOAS()) {
            return;
        }
        if (!$collection->getAutoLoad()) {
            return;
        }

        $this->loadCollection($collection, $object);
    }


    private function updateEndpointPaths(Collection $endpoints, string $prefix)
    {
        foreach ($endpoints as $endpoint) {
            $path = $endpoint->getPath();
            array_unshift($path, $prefix);
            $endpoint->setPath($path);
            $this->entityManager->persist($endpoint);
        }
        $this->entityManager->flush();
    }

    private function loadCollection(CollectionEntity $collection, object $object)
    {
        $collection = $this->oasParser->parseOas($collection);
        $collection->getLoadTestData() ? $this->dataService->loadData($collection->getTestDataLocation(), $collection->getLocationOAS(), true) : null;

        $collection = $this->entityManager->getRepository(CollectionEntity::class)->find($object->getId());
        $collection->setSyncedAt(new \DateTime('now'));
        $this->entityManager->persist($collection);
        $this->entityManager->flush();
    }
}
