<?php

namespace App\EventListener;

use App\Entity\Attribute;
use App\Entity\CollectionEntity;
use App\Entity\Entity;
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

    private function bindAttributeToEntity(Attribute $attribute, Entity $entity): void
    {
        if ($attribute->getType() !== 'object') {
            $attribute->setFormat(null);
            $attribute->setType('object');
            $attribute->setObject($entity);
            $attribute->setCascade(true);

            $this->entityManager->persist($attribute);
        }
        if($attribute->getInversedByPropertyName() && !$attribute->getInversedBy()) {
            $attribute->setInversedBy($entity->getAttributeByName($attribute->getInversedByPropertyName()));

            $this->entityManager->persist($attribute);
        }
    }

    private function bindAttributesToEntities(array $schemaRefs): void
    {
        $entityRepo = $this->entityManager->getRepository(Entity::class);
        $attributeRepo = $this->entityManager->getRepository(Attribute::class);

        // Bind objects/properties that are needed from other collections
        foreach ($schemaRefs as $ref) {
            $entity = null;
            $attributes = null;

            // Bind Attribute to the correct Entity by schema
            if ($ref['type'] == 'attribute') {
                $entity = $entityRepo->findOneBy(['schema' => $ref['schema']]);
                $attribute = $attributeRepo->find($ref['id']);
                $entity && $attribute && $this->bindAttributeToEntity($attribute, $entity);
            } elseif ($ref['type'] == 'entity') {
                // Bind all Attributes that refer to this Entity by schema
                $attributes = $attributeRepo->findBy(['schema' => $ref['schema']]);
                $entity = $entityRepo->find($ref['id']);

                if ($entity && $attributes) {
                    foreach ($attributes as $attribute) {
                        $this->bindAttributeToEntity($attribute, $entity);
                    }
                }
            }
        }
        $this->entityManager->flush();
    }

    private function loadCollection(CollectionEntity $collection, object $object): void
    {
        $schemaRefs = null;
        $collection = $this->oasParser->parseOas($collection, $schemaRefs);

        $this->bindAttributesToEntities($schemaRefs);

        $collection->getLoadTestData() ? $this->dataService->loadData($collection->getTestDataLocation(), $collection->getLocationOAS(), true) : null;

        $collection = $this->entityManager->getRepository(CollectionEntity::class)->find($object->getId());
        $collection->setSyncedAt(new \DateTime('now'));
        $this->entityManager->persist($collection);
        $this->entityManager->flush();
    }
}
