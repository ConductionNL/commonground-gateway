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

        $response = $this->gatewayService->processSource(
            $event->getRequest()->attributes->get('name'),
            $event->getRequest()->attributes->get('endpoint'),
            $event->getRequest()->getMethod(),
            $event->getRequest()->getContent(),
            $event->getRequest()->query->all(),
            $event->getRequest()->headers->all(),
        );

        // Lets see if we need to render a file
        // @todo dit is echt but lellijke code
        if (strpos($event->getRequest()->attributes->get('name'), '.') && $renderType = explode('.', $event->getRequest()->attributes->get('name'))) {
            $path = $renderType[0];
            $renderType = end($renderType);
        } elseif (strpos($event->getRequest()->attributes->get('endpoint'), '.') && $renderType = explode('.', $event->getRequest()->attributes->get('endpoint'))) {
            $path = $renderType[0];
            $renderType = end($renderType);
        }
        if (isset($renderType)) {
            $response = $this->gatewayService->retrieveExport($response, $renderType, $path);
        }

        $event->setResponse($response);
    }
}
