<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Gateway as Source;
use CommonGateway\CoreBundle\Service\CallService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ProxySubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private CallService $callService;

    public const PROXY_ROUTES = [
        'api_gateways_get_proxy_item',
        'api_gateways_get_proxy_endpoint_item',
        'api_gateways_post_proxy_collection',
        'api_gateways_post_proxy_endpoint_collection',
        'api_gateways_put_proxy_single_item',
        'api_gateways_delete_proxy_single_item',
    ];

    public function __construct(EntityManagerInterface $entityManager, CallService $callService)
    {
        $this->entityManager = $entityManager;
        $this->callService = $callService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['proxy', EventPriorities::PRE_DESERIALIZE],
        ];
    }

    public function proxy(RequestEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');

        if (!in_array($route, self::PROXY_ROUTES)) {
            return;
        }

        //@Todo rename
        $source = $this->entityManager->getRepository('App:Gateway')->find($event->getRequest()->attributes->get('id'));
        if (!$source instanceof Source) {
            return;
        }

        try {
            $result = $this->callService->call(
                $source,
                '/' . ($event->getRequest()->attributes->get('endpoint') !== null ? $event->getRequest()->attributes->get('endpoint') : ''),
                $event->getRequest()->headers->get('x-method') ?? $event->getRequest()->getMethod(),
                array_merge([
                    'headers' => array_merge_recursive($source->getHeaders(), $event->getRequest()->headers->all()),
                    'query'   => $event->getRequest()->query->all(),
                    'body'    => $event->getRequest()->getContent(),
                ], $source->getConfiguration())
            );
        } catch (ServerException | ClientException | RequestException $e) {
            $result = $e->getResponse();

            // If error catched dont pass event->getHeaders (causes infinite loop)
            $wentWrong = true;
        }
        $event->setResponse(new Response($result->getBody()->getContents(), $result->getStatusCode(), !isset($wentWrong) ? $result->getHeaders() : []));
    }
}
