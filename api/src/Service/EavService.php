<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Paginator;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\String\Inflector\EnglishInflector;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use function GuzzleHttp\json_decode;

class EavService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private ValidationService $validationService;
    private SerializerService $serializerService;

    /* @wilco waar hebben we onderstaande voor nodig? */
    private string $entityName;
    private ?string $uuid;
    private array $body;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, ValidationService $validationService, SerializerService $serializerService)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->validationService = $validationService;
        $this->serializerService = $serializerService;
    }

    /**
     * @param string $entityName
     * @return Entity|array
     */
    public function getEntity(string $entityName)
    {
        if (!$entityName) {
            return [
                "message" => "No entity name provided",
                "type" => "Bad Request",
                "path" => "entity",
                "data" => [],
            ];
        }
        $entity = $this->em->getRepository("App:Entity")->findOneBy(['name' => $entityName]);
        if (!$entity || !($entity instanceof Entity)) {
            return [
                "message" => "Could not establish an entity for ".$entityName,
                "type" => "Bad Request",
                "path" => "entity",
                "data" => ["Entity Name" => $entityName],
            ];
        }

        return $entity;
    }

    public function getId(array $body, ?string $id): ?string
    {
        if(!$id && array_key_exists('id', $body) ){
            $id = $body['id'];
        }
        //elseif(!$id && array_key_exists('uuid', $body) ){ // this catches zgw api's
        //    $id = $body['uuid'];
        //)
        elseif(!$id && array_key_exists('@id', $body)) {
            $id = $this->commonGroundService->getUuidFromUrl($body['@id']);
        }
        elseif(!$id && array_key_exists('@self', $body)) {
            $id = $this->commonGroundService->getUuidFromUrl($body['@self']);
        }

        return $id;
    }

    /**
     * @param string|null $id
     * @param string $method
     * @param Entity $entity
     * @return ObjectEntity|array|null
     */
    public function getObject(?string $id, string $method, Entity $entity)
    {
        if($id) {
            $object = $this->em->getRepository("App:ObjectEntity")->findOneBy(['id'=>Uuid::fromString($id)]);
            if(!$object) {
                return [
                    "message" => "No object found with this id: $id",
                    "type" => "Bad Request",
                    "path" => $entity->getName(),
                    "data" => ["id" => $id],
                ];
            } elseif ($entity != $object->getEntity()) {
                return [
                    "message" => "There is a mismatch between the provided ({$entity->getName()}) entity and the entity already attached to the object ({$object->getEntity()->getName()})",
                    "type" => "Bad Request",
                    "path" => $entity->getName(),
                    "data" => [
                        "providedEntityName" => $entity->getName(),
                        "attachedEntityName" => $object->getEntity()->getName()
                    ],
                ];
            }
            return $object;
        }
        elseif($method == 'POST'){
            $object = new ObjectEntity;
            $object->setEntity($entity);
            return $object;
        }
        return null;
    }

    public function handleRequest(Request $request, string $entityName): Response
    {
        $route = $request->attributes->get('_route');

        // We will always need an $entity


        // Get  a body
        if($request->getContent()){
            $body = json_decode($request->getContent(), true);
        }

        // Checking and validating the id
        $id = $request->attributes->get("id");
        // The id might be contained somwhere else, lets test for that
        //$id = $this->eavService->getId($body, $id);


        /*@todo deze check voelt wierd aan, als op  entity endpoints hebben we het object al */
        if(!((strpos($route, 'objects_collection') !== false || strpos($route, 'get_collection') !== false)&& $request->getMethod() == 'GET')){
            $entity = $this->getEntity($entityName);
            if (is_array($entity)) {
                return new Response(
                    $this->serializerService->serialize(new ArrayCollection($entity), $this->serializerService->getRenderType($request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json'))), []),
                    Response::HTTP_BAD_REQUEST,
                    ['content-type' => $this->handleContentType($request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json')))]
                );
            }
            $object = $this->getObject($id, $request->getMethod(), $entity);
            if (is_array($object)) {
                return new Response(
                    $this->serializerService->serialize(new ArrayCollection($object), $this->serializerService->getRenderType($request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json'))), []),
                    Response::HTTP_BAD_REQUEST,
                    ['content-type' => $this->handleContentType($request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json')))]
                );
            }
        }

        /*
         * Handeling data mutantions
         */
        if (strpos($route, 'collection') !== false && $request->getMethod() == 'POST') {
            $this->checkRequest($entityName, $body, $id, $request->getMethod());
            // Transfer the variable to the service
            $result = $this->handleMutation($object, $body);
            $responseType = Response::HTTP_CREATED;
        }

        /*
         * Handeling data mutantions
         */
        if (strpos($route, 'item') !== false && $request->getMethod() == 'PUT') {
            $this->checkRequest($entityName, $body, $id, $request->getMethod());
            // Transfer the variable to the service
            $result = $this->handleMutation($object, $body);
            $responseType = Response::HTTP_OK;
        }


        /*
         * Handeling reading requests
         */
        if (((strpos($route, 'object_collection') !== false || strpos($route, 'item') !== false) && $request->getMethod() == 'GET'))
        {
            /* @todo catch missing data and trhow error */
            if(!$entityName){
                /* throw error */
            }
            if(!$id && $route == 'get_eav_object'){
                /* throw error */
            }

            // Transfer the variable to the service
            $result = $this->handleGet($object, $request);
            $responseType = Response::HTTP_OK;
        }


        /*
         * Handeling search requests
         */
        if ((strpos($route, 'objects_collection') !== false || strpos($route, 'get_collection') !== false)&& $request->getMethod() == 'GET')
        {
            /* @todo catch missing data and trhow error */
            if(!$entityName){
                /* throw error */
            }
            if(!$id && $route == 'get_eav_object'){
                /* throw error */
            }

            // Transfer the variable to the service
            $result = $this->handleSearch($entityName, $request);
            $responseType = Response::HTTP_OK;
        }

        /*
         * Handeling deletions
         */
        if ($request->getMethod() == 'DELETE')
        {

            /* @todo catch missing data and trhow error */
            if(!$entityName){
                /* throw error */
            }
            if(!$id ){
                /* throw error */
            }

            // Transfer the variable to the service
            $result = $this->handleDelete($object, $request);
            $responseType = Response::HTTP_NO_CONTENT;
        }

        /* @todo we can support more then just json */
        if(array_key_exists('type',$result ) && $result['type']== 'error'){
            $responseType = Response::HTTP_BAD_REQUEST;
        }

        return new Response(
            $this->serializerService->serialize(new ArrayCollection($result), $this->serializerService->getRenderType($request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json'))), []),
            $responseType,
            ['content-type' => $this->handleContentType($request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json')))]
        );
    }

    private function handleContentType(string $accept): string
    {
        switch ($accept) {
            case "text/csv":
            case "application/json":
            case "application/hal+json":
                return $accept;
            default:
                return "application/ld+json";
        }
    }

    public function checkRequest(string $entityName, array $body, ?string $id, string $method): void
    {
        if(!$entityName){
            throw new HttpException(400,'An entity name should be provided for this route');
        }
        if(!$body){
            throw new HttpException(400, 'An body should be provided for this route');
        }
        if(!$id &&  $method == 'PUT'){
            throw new HttpException(400, 'An id should be provided for this route');
        }
    }

    /*
     * This function handles data mutations on EAV Objects
     */
    public function handleMutation(ObjectEntity $object, array $body)
    {
        // Validation stap
        $object = $this->validationService->validateEntity($object, $body);

        // Let see if we have errors
        if($object->getHasErrors()) {
            return $this->returnErrors($object);
        }

        // TODO: use (ObjectEntity) $object->promises instead
        /* this way of working is way vasther then passing stuff trough the object's, lets also implement this for error checks */
        if(!empty($this->validationService->promises)){
            Utils::settle($this->validationService->promises)->wait();

            foreach($this->validationService->promises as $promise){
                echo $promise->wait();
            }
        }

        // Check optional conditional logic
        $object->checkConditionlLogic();

        // Afther guzzle has cleared we need to again check for errors
        if($object->getHasErrors()) {
            return $this->returnErrors($object);
        }

        // Saving the data
        $this->em->persist($object);
        $this->em->flush();

        return $this->renderResult($object);
    }

    /* @todo typecast the request */
    public function handleGet(ObjectEntity $object, $request): array
    {

        return $this->renderResult($object);
    }

    /* @todo typecast the request */
    public function handleSearch(string $entityName, $request): array
    {
        $query = $request->query->all();;
        $limit = (int) ($request->query->get('limit') ?? 25); // These type casts are not redundant!
        $page = (int) ($request->query->get('page') ?? 1);
        $start = (int) ($request->query->get('start') ?? 1);

        if ($start > 1) {
            $offset = $start-1;
        } else {
            $offset = ($page-1)*$limit;
        }

        /* @todo we might want some filtering here, also this should be in the entity repository */
        $entity= $this->em->getRepository("App:Entity")->findOneBy(['name'=>$entityName]);
        $total = $this->em->getRepository("App:ObjectEntity")->findByEntity($entity, $query); // todo custom sql to count instead of getting items.
        $objects = $this->em->getRepository("App:ObjectEntity")->findByEntity($entity, $query, $offset, $limit);
        $results = ['results'=>[]];
        foreach($objects as $object) {
            $results['results'][] = $this->renderResult($object);
        }
        $results['total'] = count($total);
        $results['limit'] = $limit;
        $results['pages'] = ceil($results['total'] / $limit);
        $results['pages'] = $results['pages'] == 0 ? 1 : $results['pages'];
        $results['page'] = floor($offset / $limit)+1;
        $results['start'] = $offset+1;

        return $results;
    }

    public function handleDelete(ObjectEntity $object, $request)
    {
        $this->em->remove($object);
        $this->em->flush();

        return [];
    }

    public function returnErrors(ObjectEntity $objectEntity)
    {
        return [
            "message" => "The where errors",
            "type" => "error",
            "path" => $objectEntity->getEntity()->getName(),
            "data" => $objectEntity->getAllErrors(),
        ];
    }

    // TODO: Change this to be more efficient? (same foreach as in prepareEntity) or even move it to a different service?
    public function renderResult(ObjectEntity $result, ArrayCollection $maxDepth = null): array
    {
        $response = [];

        if($result->getUri()){
            $response['@uri'] = $result->getUri();
        }


        // Lets start with the external results
        $response = array_merge($response, $result->getExternalResult());

        // Lets move some stuff out of the way
        if(array_key_exists('@context',$response)){$response['@gateway/context'] = $response['@context'];}
        if(array_key_exists('id',$response)){$response['@gateway/id'] = $response['id'];}
        if(array_key_exists('@type',$response)){$response['@gateway/type'] = $response['@type'];}

        // Lets keep track of objects we already rendered, for inversedBy, checking maxDepth 1:
        if (is_null($maxDepth)) {
            $maxDepth = new ArrayCollection();
        }
        $maxDepth->add($result);

        foreach ($result->getObjectValues() as $value) {
            $attribute = $value->getAttribute();
            // Only render the attributes that are used
            if (!is_null($result->getEntity()->getUsedProperties()) && !in_array($attribute->getName(), $result->getEntity()->getUsedProperties())) {
                continue;
            }
            if ($attribute->getType() == 'object') {
                if ($value->getValue() == null) {
                    $response[$attribute->getName()] = null;
                    continue;
                }
                if (!$attribute->getMultiple()) {
                    // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
                    if (!$maxDepth->contains($value->getValue())) {
                        $response[$attribute->getName()] = $this->renderResult($value->getValue(), $maxDepth);
                    }
                    continue;
                }
                $objects = $value->getValue();
                $objectsArray = [];
                foreach ($objects as $object) {
                    // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
                    if (!$maxDepth->contains($object)) {
                        $objectsArray[] = $this->renderResult($object, $maxDepth);
                    } else {
                        // If multiple = true and a subresource contains an inversedby list of resources that contains this resource ($result), only show the @id
                        $objectsArray[] = ["@id" => ucfirst($object->getEntity()->getName()).'/'.$object->getId()];
                    }
                }
                $response[$attribute->getName()] = $objectsArray;
                continue;
            }
            $response[$attribute->getName()] = $value->getValue();

            // Lets insert the object that we are extending
        }

        // Lets make it personal
        $response['@context'] = '/contexts/' . ucfirst($result->getEntity()->getName());
        $response['@id'] = ucfirst($result->getEntity()->getName()).'/'.$result->getId();
        $response['@type'] = ucfirst($result->getEntity()->getName());
        $response['id'] = $result->getId();

        return $response;
    }


}
