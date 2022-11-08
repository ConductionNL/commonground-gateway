<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Entity;
use App\Exception\GatewayException;
use App\Service\ObjectEntityService;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

class ObjectSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private SynchronizationService $synchronizationService;
    private string $route;
    public const ALLOWED_ROUTES = [
        'api_entities_get_object_item',
        'api_entities_put_object_item',
        'api_entities_delete_object_item',
        'api_entities_get_objects_collection',
        'api_entities_post_objects_collection',
        'api_object_entities_get_object_item',
        'api_object_entities_put_object_item',
        'api_object_entities_delete_object_item',
        'api_object_entities_get_objects_collection',
        'api_object_entities_get_objects_schema_collection',
        'api_object_entities_post_objects_schema_collection'
    ];

    public function __construct(EntityManagerInterface $entityManager, ObjectEntityService $objectEntityService, SynchronizationService $synchronizationService)
    {
        $this->entityManager = $entityManager;
        $this->objectEntityService = $objectEntityService;
        $this->synchronizationService = $synchronizationService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['object', EventPriorities::PRE_VALIDATE],
        ];
    }

    /**
     * @todo
     *
     * @param ViewEvent $event
     *
     * @return void
     *
     * @throws CacheException|ComponentException|InvalidArgumentException
     */
    public function object(ViewEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');
        $this->route = is_string($route) ? $route : '';

        var_dump($this->route);

        // Make sure we only trigger when needed
        if (!in_array($this->route, self::ALLOWED_ROUTES)) {
            return;
        }

        // todo: acceptType? see ZZController
        $response = new Response();
        try {
            $responseContent = $this->routeSwitch($event, $response);
        } catch (GatewayException $gatewayException) {
            $options = $gatewayException->getOptions();
            $responseContent = ['message' =>  $gatewayException->getMessage(), 'data' => $options['data'], 'path' => $options['path']];
            $response->setStatusCode($options['responseType'] ?? Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $response->setContent(json_encode($responseContent));
        $event->setResponse($response);
    }

    /**
     * @todo
     *
     * @param Request $request
     *
     * @return array
     */
    private function getRequestIds(Request $request): array
    {
        if (str_contains($this->route, 'api_entities_')) {
            $schemaId = $request->attributes->get('id');
            if (str_contains($this->route, '_object_item')) {
                $objectId = $request->attributes->get('objectId');
            }
        } else {
            if (str_contains($this->route, '_object_item')) {
                $objectId = $request->attributes->get('id');
            } elseif (str_contains($this->route, '_objects_schema_collection')) {
                $schemaId = $request->attributes->get('schemaId');
            }
        }

        return [
            'schemaId' => $schemaId ?? null,
            'objectId' => $objectId ?? null
        ];
    }

    /**
     * @todo
     *
     * @param ViewEvent $event
     * @param Response $response
     *
     * @return array
     *
     * @throws GatewayException|CacheException|InvalidArgumentException|ComponentException
     */
    private function routeSwitch(ViewEvent $event, Response $response): array
    {
        $request = $event->getRequest();
        $body = json_decode($request->getContent(), true);
        $requestIds = $this->getRequestIds($request);
        $responseContent = $requestIds;
//        $responseContent = []; // <<<- todo: instead of ^

        if ($requestIds['schemaId']) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $requestIds['schemaId']]);
            if (!$entity instanceof Entity) {
                throw new GatewayException('No Entity found with this id', null, null, [
                    'data' => ['Entity' => $requestIds['schemaId']], 'path' => $request->getUri(),
                    'responseType' => Response::HTTP_NOT_FOUND
                ]);
            }
        }

        switch ($this->route) {
            case 'api_entities_get_objects_collection':
            case 'api_object_entities_get_objects_collection':
            case 'api_object_entities_get_objects_schema_collection':
                var_dump('GET Collection');
                break;
            case 'api_entities_post_objects_collection':
            case 'api_object_entities_post_objects_schema_collection':
                var_dump('POST Collection');
                // todo: acceptType for switchMethod function?
                // todo: $entity could technically be undefined (should never be though)
                $validationErrors = $this->objectEntityService->switchMethod($body, null, $entity, 'POST');
                if (isset($validationErrors)) {
                    throw new GatewayException('Validation errors', null, null, [
                        'data' => $validationErrors, 'path' => $entity->getName(),
                        'responseType' => Response::HTTP_BAD_REQUEST
                    ]);
                }
                $responseContent = $body;
                $response->setStatusCode(Response::HTTP_CREATED);
                break;
            case 'api_entities_get_object_item':
            case 'api_object_entities_get_object_item':
                var_dump('GET Item');
                break;
            case 'api_entities_put_object_item':
            case 'api_object_entities_put_object_item':
                var_dump('PUT Item');
                break;
            case 'api_entities_delete_object_item':
            case 'api_object_entities_delete_object_item':
                var_dump('DELETE Item');
                $response->setStatusCode(Response::HTTP_NO_CONTENT);
                break;
        }

        return $responseContent;
    }
}
