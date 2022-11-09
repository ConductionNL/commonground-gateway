<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
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
            $responseContent = $this->routeSwitch($event->getRequest(), $response);
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
     * @param Response $response
     *
     * @return array
     *
     * @throws GatewayException|CacheException|InvalidArgumentException|ComponentException
     */
    private function routeSwitch(Request $request, Response $response): array
    {
        $body = json_decode($request->getContent(), true);
        $requestIds = $this->getRequestIds($request);

        // todo: we might just want to delete this route (or add pagination to the result at some point)
        if ($this->route === 'api_object_entities_get_objects_collection') {
            $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
            foreach ($objectEntities as &$objectEntity) {
                $objectEntity = $objectEntity->toArray();
            }
            return $objectEntities;
        }

        $schema = $this->findSchema($requestIds, $request->getUri());

        switch ($request->getMethod()) {
            case 'POST':
                $response->setStatusCode(Response::HTTP_CREATED);
                break;
            case 'DELETE':
                // todo: Delete paths don't work atm, we can't create custom delete paths with api-platform?
                $response->setStatusCode(Response::HTTP_NO_CONTENT);
                break;
        }

        // todo: acceptType for switchMethod function?
        $validationErrors = $this->objectEntityService->switchMethod($body, null, $schema, $requestIds['objectId'], $request->getMethod());
        if (isset($validationErrors)) {
            throw new GatewayException('Validation errors', null, null, [
                'data' => $validationErrors, 'path' => $schema->getName(),
                'responseType' => Response::HTTP_BAD_REQUEST
            ]);
        }

        return $body;
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
     * @param array $requestIds
     * @param string $errorPath
     *
     * @return Entity
     *
     * @throws GatewayException
     */
    private function findSchema(array $requestIds, string $errorPath): Entity
    {
        if ($requestIds['schemaId']) {
            $schema = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $requestIds['schemaId']]);
        } elseif ($requestIds['objectId']) {
            $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $requestIds['objectId']]);
            if ($objectEntity instanceof ObjectEntity) {
                $schema = $objectEntity->getEntity();
            }
        }
        if (!isset($schema) || !$schema instanceof Entity) {
            throw new GatewayException('No Schema found with these ids', null, null, [
                'data' => $requestIds, 'path' => $errorPath,
                'responseType' => Response::HTTP_NOT_FOUND
            ]);
        }

        return $schema;
    }
}
