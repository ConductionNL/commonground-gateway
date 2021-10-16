<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Service\GatewayService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class GatewaySubscriber implements EventSubscriberInterface
{
    private GatewayService $gatewayService;

    public function __construct(GatewayService $gatewayService)
    {
        $this->gatewayService = $gatewayService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['gateway', EventPriorities::PRE_VALIDATE],
        ];
    }

    /**
     * @param ViewEvent $event
     */
    public function gateway(ViewEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');

        $response = new Response();

        if (
            $route !== 'api_gateways_gateway_post_collection'
        ) {
            return;
        }

        $response = $this->gatewayService->processGateway(
            $event->getRequest()->attributes->get('name'),
            $event->getRequest()->attributes->get('endpoint'),
            $event->getRequest()->getMethod(),
            $event->getRequest()->getContent(),
            $event->getRequest()->query->all(),
            $event->getRequest()->headers->all(),
        );

        $event->setResponse($response);
    }
}
