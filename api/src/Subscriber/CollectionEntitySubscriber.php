<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Entity;
use App\Service\AuthorizationService;
use App\Service\ConvertToGatewayService;
use App\Service\EavService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;

class CollectionEntitySubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private CommonGroundService $commonGroundService;
    private EavService $eavService;
    private AuthorizationService $authorizationService;
    private SerializerService $serializerService;
    private ConvertToGatewayService $convertToGatewayService;

    public function __construct(EntityManagerInterface $entityManager, CommonGroundService $commonGroundService, EavService $eavService, AuthorizationService $authorizationService, SerializerInterface $serializer, ConvertToGatewayService $convertToGatewayService)
    {
        $this->entityManager = $entityManager;
        $this->commonGroundService = $commonGroundService;
        $this->eavService = $eavService;
        $this->authorizationService = $authorizationService;
        $this->serializerService = new SerializerService($serializer);
        $this->convertToGatewayService = $convertToGatewayService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['collectionEntities', EventPriorities::PRE_SERIALIZE],
        ];
    }

    public function collectionEntities(ViewEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');
        $resource = $event->getControllerResult();

        if ($route == 'api_collection_entities_get_sync_item') {
            $collectionEntity = $this->entityManager->getRepository('App:CollectionEntity')->find($event->getRequest()->attributes->get('id'));

            if ($collectionEntity !== null) {

                foreach ($collectionEntity->getEntities() as $entity) {
                    $query = $event->getRequest()->query->all();
                    unset($query['limit']);
                    unset($query['page']);
                    unset($query['start']);
                    if ($entity instanceof Entity) {
                        $this->convertToGatewayService->convertEntityObjects($entity, $query);

                    }
                }
            }
        }
    }
}
