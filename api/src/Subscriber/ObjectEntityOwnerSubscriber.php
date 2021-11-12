<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\ObjectEntity;
use App\Service\GatewayService;
use App\Service\ObjectEntityService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ObjectEntityOwnerSubscriber implements EventSubscriberInterface
{
    private ObjectEntityService $objectEntityService;

    public function __construct(ObjectEntityService $objectEntityService)
    {
        $this->objectEntityService = $objectEntityService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['objectEntity', EventPriorities::PRE_VALIDATE],
        ];
    }

    /**
     * @param ViewEvent $event
     */
    public function objectEntity(ViewEvent $event)
    {
        $result = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();
        $route = $event->getRequest()->attributes->get('_route');

        if (
            !$result instanceof ObjectEntity ||
            $route != 'api_object_entities_post_collection' ||
            $route != 'api_object_entities_put_item'
        ) {
            return;
        }

        return $this->objectEntityService->handleOwner($result);

    }
}
