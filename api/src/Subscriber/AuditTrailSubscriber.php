<?php

namespace App\Subscriber;

use App\Entity\AuditTrail;
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
use Symfony\Component\HttpKernel\Event\KernelEvent;
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
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface        $valueSubscriberLogger
     * @param Security $security
     * @param ParameterBagInterface  $parameterBag
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $valueSubscriberLogger,
        Security $security,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $valueSubscriberLogger;
        $this->security = $security;
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
//            Events::postLoad,
            Events::postUpdate,
            Events::postPersist,
            Events::preRemove
        ];
    }//end getSubscribedEvents()

    /**
     * Passes the result of prePersist to preUpdate.
     *
     * @param ObjectEntity $object
     * @return array
     */
    public function createAuditTrail(ObjectEntity $object, array $config): AuditTrail
    {
        $userId = $this->security->getUser()->getUserIdentifier();
        $user = $this->entityManager->getRepository('App:User')->find($userId);

        $auditTrail = new AuditTrail();
        $auditTrail->setSource($object->getEntity()->getCollections()->first()->getPrefix());
        $auditTrail->setApplicationId($user->getApplications()->first()->getId()->toString());
        $auditTrail->setApplicationView($user->getApplications()->first()->getName());
        $auditTrail->setUserId($userId);
        $auditTrail->setUserView($user->getName());
        $auditTrail->setAction($config['action']);
        $auditTrail->setActionView($config['action']);
        $auditTrail->setResult($config['result']);
        $auditTrail->setResource($object->getId()->toString());
        $auditTrail->setResourceUrl($object->getUri());
        $auditTrail->setResourceView($object->getName());

        $this->entityManager->persist($auditTrail);
        $this->entityManager->flush();

        return $auditTrail;
    }

    /**
     * Adds object resources from identifier.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this event
     */
    public function postLoad(LifecycleEventArgs $args): void
    {

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
        }

//        $postAuditTrail = $this->entityManager->getRepository('App:AuditTrail')->findOneBy(['resource' => $object->getId()->toString(), 'result' => 201]);
//        $amendments = $postAuditTrail->getAmendments();

        $config = [
            'action' => 'UPDATE',
            'result' => 200
        ];
        $auditTrail = $this->createAuditTrail($object, $config);
        // @TODO Set old object.
        $auditTrail->setAmendments([
            'new' => $object->toArray(),
            'old' => ''
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
        }

        $config = [
            'action' => 'CREATE',
            'result' => 201
        ];

        $auditTrail = $this->createAuditTrail($object, $config);
        $auditTrail->setCreationDate(new \DateTime('now'));

        $auditTrail->setAmendments([
            'new' => $object->toArray(),
            'old' => null
        ]);

        $this->entityManager->persist($auditTrail);
        $this->entityManager->flush();
    }//end prePersist()

    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
           return;
        }

        $config = [
            'action' => 'DELETE',
            'result' => 204
        ];
        $auditTrail = $this->createAuditTrail($object, $config);
        $auditTrail->setAmendments([
            'new' => null,
            'old' => $object->toArray()
        ]);

        $this->entityManager->persist($auditTrail);
    }//end preRemove()
}//end class
