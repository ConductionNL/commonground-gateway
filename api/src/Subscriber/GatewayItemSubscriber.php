<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Service\GatewayService;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class GatewayItemSubscriber implements EventSubscriberInterface
{
    private GatewayService $gatewayService;

    public function __construct(GatewayService $gatewayService)
    {
        $this->gatewayService = $gatewayService;
    }

    /**
     * @return array[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['gateway', EventPriorities::PRE_DESERIALIZE],
        ];
    }

    /**
     * @param RequestEvent $event
     *
     * @throws Exception
     */
    public function gateway(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $route = $event->getRequest()->attributes->get('_route');

        if (
            $route !== 'api_gateways_gateway_get_item' &&
            $route !== 'api_gateways_gateway_put_item' &&
            $route !== 'api_gateways_gateway_delete_item'
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

        $event->setResponse($response);
    }
}
