<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use App\Service\HandlerService;
use App\Service\ObjectEntityService;
use App\Service\ResponseService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ObjectSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private HandlerService $handlerService;
    private ResponseService $responseService;
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
        'api_object_entities_post_objects_schema_collection',
    ];

    public function __construct(EntityManagerInterface $entityManager, ObjectEntityService $objectEntityService, HandlerService $handlerService, ResponseService $responseService)
    {
        $this->entityManager = $entityManager;
        $this->objectEntityService = $objectEntityService;
        $this->handlerService = $handlerService;
        $this->responseService = $responseService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['object', EventPriorities::PRE_VALIDATE],
        ];
    }

    /**
     * Subscriber handling the /admin endpoints used for getting, editing and deleting ObjectEntities
     * in a more user-friendly way than the standard /object_entities endpoints. Comparable to how /api endpoints work.
     *
     * @param ViewEvent $event
     *
     * @throws CacheException|ComponentException|InvalidArgumentException|GatewayException
     *
     * @return void
     */
    public function object(ViewEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');
        $this->route = is_string($route) ? $route : '';

        // Make sure we only trigger when needed
        if (!in_array($this->route, self::ALLOWED_ROUTES)) {
            return;
        }

        $acceptType = $this->handlerService->getRequestType('accept');
        $acceptType == 'form.io' && $acceptType = 'json';

        $response = new Response(null, Response::HTTP_OK, ['content-type' => $acceptType]);

        try {
            $responseContent = $this->handleRequest($event->getRequest(), $response, $acceptType);
        } catch (GatewayException $gatewayException) {
            $options = $gatewayException->getOptions();
            $responseContent = ['message' =>  $gatewayException->getMessage(), 'data' => $options['data'], 'path' => $options['path']];
            $response->setStatusCode($options['responseType'] ?? Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response->setContent(json_encode($responseContent));
        $event->setResponse($response);
    }

    /**
     * This function will handle the request and generate the correct response using the ObjectEntityService.
     *
     * @param Request  $request    The Request we are currently handling.
     * @param Response $response   The response we are going to return.
     * @param string   $acceptType The acceptType used, this will influence how the response will look.
     *
     * @throws GatewayException|CacheException|InvalidArgumentException|ComponentException Might throw for example a GatewayException when there are ValidationErrors.
     *
     * @return array The response content.
     */
    private function handleRequest(Request $request, Response $response, string $acceptType): array
    {
        $body = json_decode($request->getContent(), true);
        $requestIds = $this->getRequestIds($request);

        // todo: we might just want to delete this route (or add pagination to the result at some point)
        if ($this->route === 'api_object_entities_get_objects_collection') {
            $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
            foreach ($objectEntities as &$objectEntity) {
                $objectEntity = $acceptType === 'json' ? $objectEntity->toArray() : $this->responseService->renderResult($objectEntity, null, null, $acceptType);
            }

            return $objectEntities;
        }

        $schema = $this->findSchema($requestIds, $request->getUri());
        if ($this->route == 'api_object_entities_get_objects_schema_collection' || $this->route == 'api_entities_get_objects_collection') {
            $objectEntities = $schema->getObjectEntities();
            $renderedObjectEntities = [];
            foreach ($objectEntities as &$objectEntity) {
                $renderedObjectEntities[] = $acceptType === 'json' ? $objectEntity->toArray() : $this->responseService->renderResult($objectEntity, null, null, $acceptType);
            }

            return $renderedObjectEntities;
        }

        switch ($request->getMethod()) {
            case 'POST':
                $response->setStatusCode(Response::HTTP_CREATED);
                break;
            case 'DELETE':
                $response->setStatusCode(Response::HTTP_NO_CONTENT);
                break;
        }

        $validationErrors = $this->objectEntityService->switchMethod($body, null, $schema, $requestIds['objectId'], $request->getMethod(), $acceptType);
        if (isset($validationErrors)) {
            throw new GatewayException('Validation errors', null, null, [
                'data'         => $validationErrors, 'path' => $schema->getName(),
                'responseType' => Response::HTTP_BAD_REQUEST,
            ]);
        }

        return $body;
    }

    /**
     * Gets the objectId and schemaId from the request attributes.
     * Will get it from different attributes depending on the request route.
     *
     * @param Request $request The Request we are currently handling.
     *
     * @return array An array containing the following keys (+value): schemaId and objectId. Values could be null.
     */
    private function getRequestIds(Request $request): array
    {
        $objectId = $request->attributes->get('objectId');
        $schemaId = $request->attributes->get('schemaId');

        if (str_contains($this->route, 'api_entities_')) {
            $schemaId = $request->attributes->get('id');
        } elseif (str_contains($this->route, 'api_object_entities_') && str_contains($this->route, '_object_item')) {
            $objectId = $request->attributes->get('id');
        }

        return [
            'schemaId' => $schemaId ?? null,
            'objectId' => $objectId ?? null,
        ];
    }

    /**
     * Will look for a schema with the given requestIds.
     *
     * @param array  $requestIds An array containing a schemaId and/or objectId.
     * @param string $errorPath  The route/path of the current Request, used in case we throw an GatewayException.
     *
     * @throws GatewayException Throws an GatewayException when no schema is found.
     *
     * @return Entity The schema if we found one.
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
            throw new GatewayException('No Schema found with/for these ids', null, null, [
                'data'         => $requestIds, 'path' => $errorPath,
                'responseType' => Response::HTTP_NOT_FOUND,
            ]);
        }

        return $schema;
    }
}
