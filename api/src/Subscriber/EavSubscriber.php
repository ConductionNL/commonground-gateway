<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Service\AuthorizationService;
use App\Service\EavService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;

class EavSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private CommonGroundService $commonGroundService;
    private EavService $eavService;
    private AuthorizationService $authorizationService;
    private SerializerService $serializerService;

    public function __construct(EntityManagerInterface $entityManager, CommonGroundService $commonGroundService, EavService $eavService, AuthorizationService $authorizationService, SerializerInterface $serializer)
    {
        $this->entityManager = $entityManager;
        $this->commonGroundService = $commonGroundService;
        $this->eavService = $eavService;
        $this->authorizationService = $authorizationService;
        $this->serializerService = new SerializerService($serializer);
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['eav', EventPriorities::PRE_SERIALIZE],
        ];
    }

    public function eav(ViewEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');
        $resource = $event->getControllerResult();

        if ($route == 'api_entities_get_sync_item') {
            $query = $event->getRequest()->query->all();
            unset($query['limit']);
            unset($query['page']);
            unset($query['start']);
            $entity = $this->entityManager->getRepository('App:Entity')->find($event->getRequest()->attributes->get('id'));
        }

        // Make sure we only triggen when needed
        if (!in_array($route, [
            'api_object_entities_post_eav_objects_collection',
            'api_object_entities_put_eav_object_item',
            'api_object_entities_delete_eav_object_item',
            'api_object_entities_get_eav_object_collection',
            'api_object_entities_get_eav_objects_collection',
        ])) {
            return;
        }
        $response = $this->eavService->handleRequest($event->getRequest()); // todo duplicate code

        $entityName = $event->getRequest()->attributes->get('entity');

        try {
            $response = $this->eavService->handleRequest($event->getRequest(), $entityName); // todo duplicate code
        } catch (AccessDeniedException $exception) {
            $contentType = $event->getRequest()->headers->get('Accept', $event->getRequest()->headers->get('accept', 'application/ld+json'));
            if ($contentType == '*/*') {
                $contentType = 'application/ld+json';
            }
            $response = $this->authorizationService->serializeAccessDeniedException($contentType, $this->serializerService, $exception);
        }

        $event->setResponse($response);
    }
}
