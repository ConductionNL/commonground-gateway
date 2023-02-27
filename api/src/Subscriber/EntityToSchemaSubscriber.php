<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class EntityToSchemaSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['toSchema', EventPriorities::PRE_DESERIALIZE],
        ];
    }

    /**
     * This function returns the schema of an objectEntity or entity.
     *
     * @param RequestEvent $event The event object
     * @throws GatewayException
     */
    public function toSchema(RequestEvent $event)
    {
        $request = $event->getRequest();

        // Let not do anything if a schema is not requested
        if ($request->headers->get('Accept') !== 'application/json+schema' && $request->headers->get('Accept') !== 'application/schema+json') {
            return;
        }

        // @todo Get endpoint with prefix does not work
//        $objectType = $request->attributes->get('_route_params') ? $request->attributes->get('_route_params')['_api_resource_class'] : null; //The class of the requested entity
        $objectId = $request->attributes->get('_route_params') ? $request->attributes->get('_route_params')['id'] : null; //The id of the resource

        if (!$objectId) {
            throw new GatewayException('Cannot give a schema if no entity is given');
        }

        if ($objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($objectId)) {
            $event->setResponse(new Response(json_encode($objectEntity->getEntity()->toSchema($objectEntity)), Response::HTTP_OK, ['content-type' => 'application/json+schema']));
        }

        if ($entity = $this->entityManager->getRepository('App:Entity')->find($objectId)) {
            $event->setResponse(new Response(json_encode($entity->toSchema(null)), Response::HTTP_OK, ['content-type' => 'application/json+schema']));
        }
    }
}
