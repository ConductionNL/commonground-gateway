<?php

namespace App\Subscriber;

use App\Entity\Coupler;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Entity\Value;
use App\Service\SynchronizationService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

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

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $synchronizationService;

    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameterBag;

    /**
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $valueSubscriberLogger
     * @param SynchronizationService $synchronizationService
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $valueSubscriberLogger, SynchronizationService $synchronizationService, ParameterBagInterface $parameterBag)
    {
        $this->entityManager = $entityManager;
        $this->logger = $valueSubscriberLogger;
        $this->synchronizationService = $synchronizationService;
        $this->parameterBag = $parameterBag;
    }//end __construct()

    /**
     * Defines the events that the subscriber should subscribe to.
     *
     * @return array The subscribed events
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::prePersist,
            Events::preRemove,
        ];
    }//end getSubscribedEvents()

    /**
     * Gets a subobject by uuid.
     *
     * @param string $uuid The id of the subobject
     * @param Value $valueObject The valueObject to add the subobject to
     *
     * @return ObjectEntity|null The found subobject
     */
    public function getSubObjectById(string $uuid, Value $valueObject): ?ObjectEntity
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
                    'uuid' => $uuid,
                    'ParentObject' => [
                        'id' => $parentObject->getId()->toString(),
                        'entity' => $parentObject->getEntity() ? [
                            'id' => $parentObject->getEntity()->getId()->toString(),
                            'name' => $parentObject->getEntity()->getName(),
                        ] : null,
                        '_self' => $parentObject->getSelf(),
                        'name' => $parentObject->getName(),
                    ],
                ]
            );

            return null;
        }//end if

        return $subObject;
    }//end getSubObjectById()

    /**
     * Gets a subobject by url.
     *
     * @param string $url The url of the subobject
     * @param Value $valueObject The value object to add the subobject to
     *
     * @return ObjectEntity|null The resulting subobject
     */
    public function getSubObjectByUrl(string $url, Value $valueObject): ?ObjectEntity
    {
        // First check if the object is already being synced.
        foreach ($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $insertion) {
            if ($insertion instanceof Synchronization === true && $insertion->getSourceId() === $url) {
                return $insertion->getObject();
            }
        }

        // Then check if the url is internal.
        $self = str_replace(rtrim($this->parameterBag->get('app_url'), '/'), '', $url);
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['self' => $self]);
        if ($objectEntity !== null) {
            return $objectEntity;
        }

        // Finally, if we really don't have the object, get it from the source.
        $synchronization = $this->entityManager->getRepository('App:Synchronization')->findOneBy(['sourceId' => $url]);
        if ($synchronization instanceof Synchronization === true) {
            return $synchronization->getObject();
        }

        return $this->synchronizationService->aquireObject($url, $valueObject->getAttribute()->getObject());
    }//end getSubObjectByUrl()

    /**
     * Finds subobjects by identifiers.
     *
     * @param string $identifier The identifier to find the object for
     * @param Value $valueObject The value object to add objects to
     *
     * @return ObjectEntity|null The found object
     */
    public function findSubobject(string $identifier, Value $valueObject): ?ObjectEntity
    {
        if (Uuid::isValid($identifier)) {
            return $this->getSubObjectById($identifier, $valueObject);
        } elseif (filter_var($identifier, FILTER_VALIDATE_URL)) {
            return $this->getSubObjectByUrl($identifier, $valueObject);
        }

        return null;
    }//end findSubObject()

    public function getInverses(Coupler $coupler, Value $value, ObjectEntity &$object = null): ArrayCollection
    {
        $targetObjectId = $coupler->getObjectId();
        $sourceObjectId = $value->getObjectEntity()->getId()->toString();

        $object = $this->entityManager->find("App:ObjectEntity", $targetObjectId);

        if ($object instanceof ObjectEntity === false) {
            return new ArrayCollection([]);
        }

        $inverseValue = $object->getValueObject($value->getAttribute()->getInversedBy());

        $criteria = Criteria::create()->where(Criteria::expr()->eq('objectId', $sourceObjectId));

        $inverses = new ArrayCollection($inverseValue->getObjects()->toArray());

        return $inverses->matching($criteria);
    }

    public function inverseRelation(Value $value)
    {

        if ($value->getAttribute()->getInversedBy() !== null) {
            foreach ($value->getObjects() as $coupler) {
                $inverses = $this->getInverses($coupler, $value, $object);

                $inverseValue = $object->getValueObject($value->getAttribute()->getInversedBy());

                if ($inverses->count() === 0) {
                    $inverseCoupler = new Coupler($value->getObjectEntity());

                    $inverseValue->addObject($inverseCoupler);
                    $this->entityManager->persist($inverseValue);
                }

            }
        }
    }

    public function removeInverses(Coupler $coupler, Value $value)
    {
        $inverses = $this->getInverses($coupler, $value, $object);

        foreach ($inverses as $inverse) {
            $this->entityManager->remove($inverse);
        }

    }

    public function preDelete(LifecycleEventArgs $value)
    {
        $valueObject = $value->getObject();

        if ($valueObject instanceof Value === true
            && $valueObject->getAttribute()->getType() === 'object'
            && $valueObject->getAttribute()->getInversedBy() !== null
        ) {

            foreach ($valueObject->getObjects() as $coupler) {
                $this->removeInverses($coupler, $valueObject);
            }
        }
    }

    /**
     * Adds object resources from identifier.
     *
     * @param LifecycleEventArgs $value The lifecycle event arguments for this event
     */
    public function preUpdate(LifecycleEventArgs $value): void
    {
        $valueObject = $value->getObject();

        if ($valueObject instanceof Value && $valueObject->getAttribute()->getType() == 'object') {
            if ($valueObject->getArrayValue()) {
                foreach ($valueObject->getArrayValue() as $identifier) {
                    $subobject = $this->findSubobject($identifier, $valueObject);
                    if ($subobject !== null) {
                        $valueObject->addObject(new Coupler($subobject));
                    }
                }
                $valueObject->setArrayValue([]);
            } elseif ((Uuid::isValid($valueObject->getStringValue()) || filter_var($valueObject->getStringValue(), FILTER_VALIDATE_URL)) && $identifier = $valueObject->getStringValue()) {
                foreach ($valueObject->getObjects() as $object) {
                    $valueObject->removeObject($object);
                }
                $subobject = $this->findSubobject($identifier, $valueObject);

                if ($subobject !== null) {
                    $valueObject->addObject(new Coupler($subobject));
                }
            }
            $this->inverseRelation($valueObject);

            $valueObject->getObjectEntity()->setDateModified(new \DateTime());
        }
    }//end preUpdate()

    /**
     * Passes the result of prePersist to preUpdate.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->preUpdate($args);
    }//end prePersist()

    public function preRemove(LifecycleEventArgs $value): void
    {
        $valueObject = $value->getObject();

        if ($valueObject instanceof Value === true
            && $valueObject->getAttribute()->getType() === 'object'
            && $valueObject->getAttribute()->getInversedBy() !== null
        ) {

            foreach ($valueObject->getObjects() as $coupler) {
                $this->removeInverses($coupler, $valueObject);
            }
        }
    }//end preRemove()
}//end class
