<?php

namespace App\Subscriber;

use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Entity\Value;
use App\Service\SynchronizationService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
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
     * @param LoggerInterface        $valueSubscriberLogger
     * @param SynchronizationService $synchronizationService
     * @param ParameterBagInterface  $parameterBag
     */
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $valueSubscriberLogger, SynchronizationService $synchronizationService, ParameterBagInterface $parameterBag)
    {
        $this->entityManager = $entityManager;
        $this->logger = $valueSubscriberLogger;
        $this->synchronizationService = $synchronizationService;
        $this->parameterBag = $parameterBag;
    }//end __construct()

    /**
     * Defines the events that the subscriber should subscribe to
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
    }

    /**
     * Gets a subobject by uuid
     *
     * @param string $uuid        The id of the subobject
     * @param Value  $valueObject The valueObject to add the subobject to
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

    /**
     * Gets a subobject by url
     *
     * @param string $url         The url of the subobject
     * @param Value  $valueObject The value object to add the subobject to
     *
     * @return ObjectEntity|null The resulting subobject
     */
    public function getSubObjectByUrl(string $url, Value $valueObject): ?ObjectEntity
    {
        $self = str_replace($this->parameterBag->get('app_url'), '', $url);
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['self' => $self]);
        if ($objectEntity !== null) {
            return $objectEntity;
        }

        $synchronization = $this->entityManager->getRepository('App:Synchronization')->findOneBy(['sourceId' => $url]);
        if ($synchronization instanceof Synchronization === true) {
            return $synchronization->getObject();
        } else {
            var_dump('for me and you');
            return $this->synchronizationService->aquireObject($url, $valueObject->getAttribute()->getObject());
        }
    }//end getSubObjectByUrl()

    /**
     * Finds subobjects by identifiers
     *
     * @param string $identifier  The identifier to find the object for
     * @param Value  $valueObject The value object to add objects to
     *
     * @return ObjectEntity|null The found object
     */
    public function findSubobject(string $identifier, Value $valueObject): ?ObjectEntity
    {
        if (Uuid::isValid($identifier)) {
            $subObject = $this->getSubObjectById($identifier, $valueObject);
            $subObject && $valueObject->addObject($subObject);
        } elseif (filter_var($identifier, FILTER_VALIDATE_URL)) {
            var_dump('I see them bloom');
            $subObject = $this->getSubObjectByUrl($identifier, $valueObject);
        }

        return $subObject;
    }//end findSubObject()

    /**
     * Adds object resources from identifier
     *
     * @param LifecycleEventArgs $value The lifecycle event arguments for this event
     */
    public function preUpdate(LifecycleEventArgs $value): void
    {
        $valueObject = $value->getObject();

        var_dump('I see trees of green');
        if ($valueObject instanceof Value && $valueObject->getAttribute()->getType() == 'object') {
            var_dump('red roses too');
            if ($valueObject->getArrayValue()) {
                foreach ($valueObject->getArrayValue() as $identifier) {
                    $subobject = $this->findSubobject($identifier, $valueObject);
                    $subobject === null ?? $valueObject->addObject($subobject);
                }
                $valueObject->setArrayValue([]);
            } elseif ($identifier = $valueObject->getStringValue()) {
                foreach ($valueObject->getObjects() as $object) {
                    $valueObject->removeObject($object);
                }
                $subobject = $this->findSubobject($identifier, $valueObject);
                $subobject === null ?? $valueObject->addObject($subobject);
            }
        }
    }//end preUpdate()

    /**
     * Passes the result of prePersist to preUpdate
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->preUpdate($args);
    }//end prePersist()

    public function preRemove(LifecycleEventArgs $args): void
    {
    }//end preRemove()
}//end class
