<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ObjectSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private $route;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['object', EventPriorities::PRE_SERIALIZE],
        ];
    }

    public function object(ViewEvent $event)
    {
        $this->route = $event->getRequest()->attributes->get('_route');

        // Make sure we only trigger when needed
        if (!in_array($this->route, [
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
        ])) {
            return;
        }

        $this->routeSwitch($event);
    }

    private function getRequestIds(ViewEvent $event): array
    {
        if (str_contains($this->route, 'api_entities_')) {
            $schemaId = $event->getRequest()->attributes->get('id');
            if (str_contains($this->route, '_object_item')) {
                $objectId = $event->getRequest()->attributes->get('objectId');
            }
        } else {
            if (str_contains($this->route, '_object_item')) {
                $objectId = $event->getRequest()->attributes->get('id');
            } elseif (str_contains($this->route, '_objects_schema_collection')) {
                $schemaId = $event->getRequest()->attributes->get('schemaId');
            }
        }

        return [
            'schemaId' => $schemaId ?? null,
            'objectId' => $objectId ?? null
        ];
    }

    private function routeSwitch(ViewEvent $event)
    {
        $requestIds = $this->getRequestIds($event);

        switch ($this->route) {
            case 'api_entities_get_object_item':
            case 'api_object_entities_get_object_item':
                var_dump('GET Item', $requestIds);
                break;
            case 'api_entities_put_object_item':
            case 'api_object_entities_put_object_item':
                var_dump('PUT Item', $requestIds);
                break;
            case 'api_entities_delete_object_item':
            case 'api_object_entities_delete_object_item':
                var_dump('DELETE Item', $requestIds);
                break;
            case 'api_entities_get_object_collection':
            case 'api_object_entities_get_object_collection':
            case 'api_object_entities_get_objects_schema_collection':
                var_dump('GET Collection', $requestIds);
                break;
            case 'api_entities_post_object_collection':
            case 'api_object_entities_post_objects_schema_collection':
                var_dump('POST Collection', $requestIds);
                break;
        }
    }
}
