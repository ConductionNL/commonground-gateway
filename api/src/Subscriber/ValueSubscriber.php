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
     * @var SynchronizationService $synchronizationService
     */
    private SynchronizationService $synchronizationService;

    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameterBag;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $valueSubscriberLogger, SynchronizationService $synchronizationService, ParameterBagInterface $parameterBag)
    {
        $this->entityManager = $entityManager;
        $this->logger = $valueSubscriberLogger;
        $this->synchronizationService = $synchronizationService;
        $this->parameterBag = $parameterBag;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::prePersist,
            Events::preRemove,
        ];
    }

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

    public function aquireObject(string $url, Entity $entity): ?ObjectEntity
    {
        // 1. Get the domain from the url
        $parse = parse_url($url);
        $location = $parse['host'];

        // 2.c Try to establich a source for the domain
        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['location'=>$location]);

        // 2.b The source might be on a path e.g. /v1 so if whe cant find a source let try to cycle
        foreach(explode('/', $parse['path']) as $pathPart) {
            $location = $location.'/'.$pathPart;
            $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['location'=>$location]);
            if($source !== null) {
                break;
            }
        }
        if($source instanceof Gateway === false) {
            return null;
        }

        // 3 If we have a source we can establich an endpoint.
        $endpoint = str_replace($location,'',$url);

        // 4 Createa sync
        $synchronization = new Synchronization($source, $entity);
        $synchronization->setSourceId($url);
        $synchronization->setEndpoint($endpoint);

        $this->synchronizationService->synchronize($synchronization);

        return $synchronization->getObject();
    }

    public function getSubObjectByUrl(string $url, Value $valueObject): ?ObjectEntity
    {
        $self = str_replace($this->parameterBag->get('app_url'), '', $url);
        $objecEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['self' => $self]);
        if($objecEntity !== null) {
            return $objecEntity;
        }

        $synchronization = $this->entityManager->getRepository('App:Synchronization')->findOneBy(['sourceId' => $url]);
        if($synchronization instanceof Synchronization === true) {
            return $synchronization->getObject();
        } else {
            $this->aquireObject($url, $valueObject->getAttribute()->getObject());
        }

        return null;
    }


    public function findSubobject(string $identifier, Value $valueObject): ?ObjectEntity
    {

        if(Uuid::isValid($identifier)) {
            $subObject = $this->getSubObjectById($identifier, $valueObject);
            $subObject && $valueObject->addObject($subObject);
        } elseif (filter_var($identifier, FILTER_VALIDATE_URL)) {
            $subObject = $this->getSubObjectByUrl($identifier, $valueObject);
        }

        return $subObject;
    }


    public function preUpdate(LifecycleEventArgs $value): void
    {
        $valueObject = $value->getObject();

        if ($valueObject instanceof Value && $valueObject->getAttribute()->getType() == 'object') {
            if ($valueObject->getArrayValue()) {
                foreach ($valueObject->getArrayValue() as $identifier) {
                    $valueObject->addObject($this->findSubobject($identifier, $valueObject));
                }
                $valueObject->setArrayValue([]);
            } elseif ($identifier = $valueObject->getStringValue()) {
                foreach($valueObject->getObjects() as $object)
                    $valueObject->removeObject($object);
                $valueObject->addObject($this->findSubobject($identifier, $valueObject));
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
