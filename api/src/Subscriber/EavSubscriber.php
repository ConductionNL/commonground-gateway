<?php
namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Component;
use App\Entity\Entity;
use App\Entity\ObjectCommunication;
use App\Entity\ObjectEntity;

use App\Service\EavService;


use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use SensioLabs\Security\Exception\HttpException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use function GuzzleHttp\json_decode;

class EavSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private CommonGroundService $commonGroundService;
    private EavService $eavService;

    public function __construct(EntityManagerInterface $entityManager, CommonGroundService $commonGroundService, EavService $eavService)
    {
        $this->entityManager = $entityManager;
        $this->commonGroundService = $commonGroundService;
        $this->eavService = $eavService;
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

        // Make sure we only triggen when needed
        if(!in_array($route, [
            'api_object_entities_post_eav_objects_collection',
            'api_object_entities_put_eav_object_item',
            'api_object_entities_delete_eav_object_item',
            'api_object_entities_get_eav_object_collection',
            'api_object_entities_get_eav_objects_collection'
        ])){
            return;
        }

        $entityName = $event->getRequest()->attributes->get("entity");
        $entity =  $this->entityManager->getRepository("App:Entity")->findOneBy(['name' => $entityName]);
        $response = $this->eavService->handleRequest($event->getRequest(), $entity);

        $event->setResponse($response);
    }
}
