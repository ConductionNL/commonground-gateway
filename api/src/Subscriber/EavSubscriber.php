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
    private $em;
    private CommonGroundService $commonGroundService;
    private EavService $eavService;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, EavService $eavService)
    {
        $this->em = $em;
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

        // We will always need an $entity
        $entityName = $event->getRequest()->attributes->get("entity");

        // Get  a body
        if($event->getRequest()->getContent()){
            $body = json_decode($event->getRequest()->getContent(), true);
        }

        // Checking and validating the id
        $id = $event->getRequest()->attributes->get("id");
        // The id might be contained somwhere else, lets test for that
        //$id = $this->eavService->getId($body, $id);


        /*@todo deze check voelt wierd aan, als op  entity endpoints hebben we het object al */
        if($route != 'api_object_entities_get_eav_objects_collection'){
            $entity = $this->eavService->getEntity($entityName);
            $object = $this->eavService->getObject($id, $event->getRequest()->getMethod(), $entity);
        }

        /*
         * Handeling data mutantions
         */
        if ($route == 'api_object_entities_post_eav_objects_collection' ) {
            $this->eavService->checkRequest($entityName, $body, $id, $event->getRequest()->getMethod());
            // Transfer the variable to the service
            $result = $this->eavService->handleMutation($object, $body);
            $responseType = Response::HTTP_CREATED;
        }

        /*
         * Handeling data mutantions
         */
        if ( $route == 'api_object_entities_put_eav_object_item') {
            $this->eavService->checkRequest($entityName, $body, $id, $event->getRequest()->getMethod());
            // Transfer the variable to the service
            $result = $this->eavService->handleMutation($object, $body);
            $responseType = Response::HTTP_OK;
        }


        /*
         * Handeling reading requests
         */
        if ($route == 'api_object_entities_get_eav_object_collection')
        {
            /* @todo catch missing data and trhow error */
            if(!$entityName){
                /* throw error */
            }
            if(!$id && $route == 'get_eav_object'){
                /* throw error */
            }

            // Transfer the variable to the service
            $result = $this->eavService->handleGet($object, $event->getRequest());
            $responseType = Response::HTTP_OK;
        }


        /*
         * Handeling search requests
         */
        if ($route == 'api_object_entities_get_eav_objects_collection')
        {
            /* @todo catch missing data and trhow error */
            if(!$entityName){
                /* throw error */
            }
            if(!$id && $route == 'get_eav_object'){
                /* throw error */
            }

            // Transfer the variable to the service
            $result = $this->eavService->handleSearch($entityName, $event->getRequest());
            $responseType = Response::HTTP_OK;
        }

        /*
         * Handeling deletions
         */
        if ($route == 'delete_eav_item')
        {

            /* @todo catch missing data and trhow error */
            if(!$entityName){
                /* throw error */
            }
            if(!$id ){
                /* throw error */
            }

            // Transfer the variable to the service
            $result = $this->eavService->handleDelete($body, $entityName, $object);
            $responseType = Response::HTTP_NO_CONTENT;
        }

        /* @todo we can support more then just json */
        if(array_key_exists('type',$result ) && $result['type']== 'error'){
            $responseType = Response::HTTP_BAD_REQUEST;
        }
        $response = new Response(
            json_encode($result),
            $responseType,
            ['content-type' => 'application/json']
        );

        $event->setResponse($response);
    }
}
