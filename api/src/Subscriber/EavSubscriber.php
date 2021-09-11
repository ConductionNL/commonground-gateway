<?php


namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Component;
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
use Symfony\Component\Serializer\SerializerInterface;
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
        if(!in_array($route, ['post_eav_objects','put_eav_object','delete_eav_object','get_eav_object','get_eav_objects'])){
            return;
        }

        // We will always need an $entity
        $entityName = $event->getRequest()->attributes->get("entity");
        if(!$entityName){
            throw new HttpException('No entity name provided', 400);
        }
        $entity = $this->em->getRepository("App\Entity\Entity")->findOneBy(['name' => $entityName]);
        if(!$entity){
            throw new HttpException('Could not establish an entity for '.$entityName, 400);
        }

        // Get  a body
        $body = json_decode($event->getRequest()->getContent(), true);

        /*
         * Checking and validating the id
         */
        $id = $event->getRequest()->attributes->get("uuid");
        // The id might be contained somwhere else, lets test for that
        if(!$id && array_key_exists('id', $body) ){
            $id = $body['id'];
        }
        //elseif(!$id && array_key_exists('uuid', $body) ){ // this catches zgw api's
        //    $id = $body['uuid'];
        //)
        elseif(!$id && array_key_exists('@id', $body) ){
            $id = $this->commonGroundService->getUuidFromUrl($body['@id']);
        )
        elseif(!$id && array_key_exists('@self', $body) ){
            $id = $this->commonGroundService->getUuidFromUrl($body['@self']);
        )

        /*
         * Fixing the object
         */
        if($id){
            $object = $this->em->getRepository("App\Entity\ObjectEntity")->get($id);
            if($object){
                throw new HttpException('No object found with this id: ' . $id, 400);
            }
        }
        elseif($route=="post_eav_objects"){
            $object = New ObjectEntity;
            $object->setEntity($entity);
        }
        // lets make sure that the entity and object match
        if($entity != $object->getEntity() ){
            throw new HttpException('There is a mismatch between the provided ('.$entity->getName().') entity and the entity already atached to the object ('.$object->getEntity()->getName().')' 400);
        }

        /*
         * Handeling data mutantions
         */
        if ($route == 'post_eav_objects' || $route == 'put_eav_object') {

            /* @todo catch missing data and trhow error */
            if(!$entityName){
                /* throw error */
            }
            if(!$body){
                /* throw error */
            }
            if(!$uuid &&  $route == 'put_eav_object'){
            }

            // Transfer the variable to the service
            $result = $this->eavService->handleMutation($object, $body);
        }


        /*
         * Handeling reading requests
         */
        if ($route == 'get_eav_object' || $route == 'get_eav_object')
        {
            /* @todo catch missing data and trhow error */
            if(!$entityName){
                /* throw error */
            }
            if(!$body){
                /* throw error */
            }
            if(!$uuid &&  $route == 'get_eav_object'){
                /* throw error */
            }

            // Transfer the variable to the service
            $result = $this->eavService->handleGet($body, $entityName, $object);
        }

        /*
         * Handeling deletions
         */
        if ($route == 'delete_eav_object')
        {

            /* @todo catch missing data and trhow error */
            if(!$entityName){
                /* throw error */
            }
            if(!$uuid ){
                /* throw error */
            }

            // Transfer the variable to the service
            $result = $this->eavService->handleDelete($body, $entityName, $object);
        }

        /* @todo we can support more then just json */

        $responseType = Response::HTTP_CREATED;
        $response = new Response(
            json_encode($result),
            $responseType,
            ['content-type' => 'application/json']
        );
        $event->setResponse($response);


    }
}
