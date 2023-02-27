<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Service\EavService;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;


final class EntityDeleteObjectsSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var EavService
     */
    private EavService $eavService;

    /**
     * @param EntityManagerInterface $entityManager The entity manager
     * @param EavService $eavService The EAV Service
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        EavService $eavService
    ) {
        $this->entityManager = $entityManager;
        $this->eavService = $eavService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['entityDeleteObjects', EventPriorities::PRE_DESERIALIZE],
        ];
    }

    /**
     * This function returns the schema of an objectEntity or entity.
     *
     * @param RequestEvent $event The event object
     */
    public function entityDeleteObjects(RequestEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');

        // Only trigger when we want to.
        if (
            $route !== 'api_entities_delete_objects_item'
        ) {
            return;
        }

        // Let's see if we have the proper info on our route.
        $entity = $this->entityManager->getRepository('App:Entity')->find($event->getRequest()->attributes->get('id'));
        $method = $event->getRequest()->getMethod();
        if ($entity instanceof Entity === false || $method !==  'PUT') {
            return;
        }

        $this->eavService->deleteAllObjects($entity);

    }//end entityDeleteObjects()
}
