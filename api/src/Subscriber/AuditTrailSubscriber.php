<?php

namespace App\Subscriber;

use App\Entity\AuditTrail;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class AuditTrailSubscriber implements EventSubscriberInterface
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
     * @var Security
     */
    private Security $security;

    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameterBag;

    /**
     * The request stack.
     *
     * @var RequestStack
     */
    private RequestStack $requestStack;

    /**
     * The cache service.
     *
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface        $valueSubscriberLogger
     * @param Security               $security
     * @param ParameterBagInterface  $parameterBag
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $valueSubscriberLogger,
        Security $security,
        ParameterBagInterface $parameterBag,
        RequestStack $requestStack,
        CacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $valueSubscriberLogger;
        $this->security = $security;
        $this->parameterBag = $parameterBag;
        $this->requestStack = $requestStack;
        $this->cacheService = $cacheService;
    }//end __construct()

    /**
     * Defines the events that the subscriber should subscribe to.
     *
     * @return array The subscribed events
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postUpdate,
            Events::postPersist,
            Events::preRemove,
//            Events::postLoad,
        ];
    }//end getSubscribedEvents()

    /**
     * Passes the result of prePersist to preUpdate.
     *
     * @param ObjectEntity $object
     *
     * @return array
     */
    public function createAuditTrail(ObjectEntity $object, array $config): AuditTrail
    {
        $userId = null;
        $user   = null;

        if ($this->security->getUser() !== null) {
            $userId = $this->security->getUser()->getUserIdentifier();
            $user = $this->entityManager->getRepository('App:User')->find($userId);
        }

        $auditTrail = new AuditTrail();
        if ($object->getEntity() !== null
            && $object->getEntity()->getCollections()->first() !== false
        ) {
            $auditTrail->setSource($object->getEntity()->getCollections()->first()->getPrefix());
        }

        $auditTrail->setApplicationId('Anonymous');
        $auditTrail->setApplicationView('Anonymous');
        $auditTrail->setUserId('Anonymous');
        $auditTrail->setUserView('Anonymous');

        if ($user !== null) {
            $auditTrail->setApplicationId($user->getApplications()->first()->getId()->toString());
            $auditTrail->setApplicationView($user->getApplications()->first()->getName());
            $auditTrail->setUserId($userId);
            $auditTrail->setUserView($user->getName());
        }
        $auditTrail->setAction($config['action']);
        $auditTrail->setActionView($config['action']);
        $auditTrail->setResult($config['result']);
        $auditTrail->setResource($object->getId()->toString());
        $auditTrail->setResourceUrl($object->getUri());
        $auditTrail->setResourceView($object->getName());
        $auditTrail->setCreationDate(new \DateTime('now'));

        $this->entityManager->persist($auditTrail);

        return $auditTrail;
    }

    /**
     * Adds object resources from identifier.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this event
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
            return;
        } else if ($object->getEntity() === null || $object->getEntity()->getCreateAuditTrails() === false) {
            return;
        }

        $config = [
            'action' => 'READ',
            'result' => 200,
        ];

        $auditTrail = $this->createAuditTrail($object, $config);
        $this->entityManager->persist($auditTrail);
    }

    /**
     * Adds object resources from identifier.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this event
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof ObjectEntity === false) {
            return;
        } else if ($object->getEntity()->getCreateAuditTrails() === false) {
            return;
        }

        $config = [
            'action' => 'UPDATE',
            'result' => 200,
        ];

        if ($this->requestStack->getMainRequest() !== null
            && $this->requestStack->getMainRequest()->getMethod() === 'PATCH'
        ) {
            $config = [
                'action' => 'PARTIAL_UPDATE',
                'result' => 200,
            ];
        }

        $auditTrail = $this->createAuditTrail($object, $config);

        $auditTrail->setAmendments([
            'new' => $this->objectEntityService->toArray($object),
            'old' => $this->cacheService->getObject($object->getId()),
        ]);
        $this->entityManager->persist($auditTrail);
        $this->entityManager->flush();
    }//end preUpdate()

    /**
     * Passes the result of prePersist to preUpdate.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
            return;
        } else if ($object->getEntity()->getCreateAuditTrails() === false) {
            return;
        }

        $config = [
            'action' => 'CREATE',
            'result' => 201,
        ];

        $auditTrail = $this->createAuditTrail($object, $config);
        $auditTrail->setAmendments([
            'new' => $this->objectEntityService->toArray($object),
            'old' => null,
        ]);

        $this->entityManager->persist($auditTrail);
        $this->entityManager->flush();
    }//end prePersist()

    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
            return;
        } else if ($object->getEntity()->getCreateAuditTrails() === false) {
            return;
        }

        $config = [
            'action' => 'DELETE',
            'result' => 204,
        ];
        $auditTrail = $this->createAuditTrail($object, $config);
        $auditTrail->setAmendments([
            'new' => null,
            'old' => $this->objectEntityService->toArray($object),
        ]);

        $this->entityManager->persist($auditTrail);
    }//end preRemove()
}//end class
