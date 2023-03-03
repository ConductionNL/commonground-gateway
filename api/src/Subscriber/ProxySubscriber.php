<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Gateway as Source;
use CommonGateway\CoreBundle\Service\CallService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
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

        $headers = array_merge_recursive($source->getHeaders(), $event->getRequest()->headers->all());
        $endpoint = $headers['x-endpoint'][0] ?? '';
        if (empty($endpoint) === false && str_starts_with($endpoint, '/') === false && str_ends_with($source->getLocation(), '/') === false) {
            $endpoint = '/'.$endpoint;
        }
        $endpoint = rtrim($endpoint, '/');

        $method = $headers['x-method'][0] ?? $event->getRequest()->getMethod();
        unset($headers['authorization']);
        unset($headers['x-endpoint']);
        unset($headers['x-method']);

        try {
            $result = $this->callService->call(
                $source,
                $endpoint,
                $method,
                [
                    'headers' => $headers,
                    'query'   => $event->getRequest()->query->all(),
                    'body'    => $event->getRequest()->getContent(),
                ]
            );
        } catch (ServerException|ClientException|RequestException $e) {
            $result = $e->getResponse();

            // If error catched dont pass event->getHeaders (causes infinite loop)
            $wentWrong = true;
        }
        $event->setResponse(new Response($result->getBody()->getContents(), $result->getStatusCode(), !isset($wentWrong) ? $result->getHeaders() : []));
    }
}
