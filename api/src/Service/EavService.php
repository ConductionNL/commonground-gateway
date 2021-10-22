<?php

namespace App\Service;

use Adbar\Dot;
use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\File;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use function GuzzleHttp\json_decode;
use GuzzleHttp\Promise\Utils;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Serializer\SerializerInterface;

class EavService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private ValidationService $validationService;
    private SerializerService $serializerService;
    private SerializerInterface $serializer;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, ValidationService $validationService, SerializerService $serializerService, SerializerInterface $serializer)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->validationService = $validationService;
        $this->serializerService = $serializerService;
        $this->serializer = $serializer;
    }

    /**
     * Looks for an Entity object using a entityName.
     *
     * @param string $entityName
     *
     * @return Entity|array
     */
    public function getEntity(string $entityName)
    {
        if (!$entityName) {
            return [
                'message' => 'No entity name provided',
                'type'    => 'Bad Request',
                'path'    => 'entity',
                'data'    => [],
            ];
        }
        $entity = $this->em->getRepository('App:Entity')->findOneBy(['name' => $entityName]);
        if (!($entity instanceof Entity)) {
            $entity = $this->em->getRepository('App:Entity')->findOneBy(['route' => '/api/'.$entityName]);
        }

        if (!($entity instanceof Entity)) {
            return [
                'message' => 'Could not establish an entity for '.$entityName,
                'type'    => 'Bad Request',
                'path'    => 'entity',
                'data'    => ['Entity Name' => $entityName],
            ];
        }

        return $entity;
    }

    // TODO: REMOVE? not used anywhere?
    public function getId(array $body, ?string $id): ?string
    {
        if (!$id && array_key_exists('id', $body)) {
            $id = $body['id'];
        }
        //elseif(!$id && array_key_exists('uuid', $body) ){ // this catches zgw api's
        //    $id = $body['uuid'];
        //)
        elseif (!$id && array_key_exists('@id', $body)) {
            $id = $this->commonGroundService->getUuidFromUrl($body['@id']);
        } elseif (!$id && array_key_exists('@self', $body)) {
            $id = $this->commonGroundService->getUuidFromUrl($body['@self']);
        }

        return $id;
    }

    /**
     * Looks for a ObjectEntity using an id or creates a new ObjectEntity if no ObjectEntity was found with that id or if no id is given at all.
     *
     * @param string|null $id
     * @param string      $method
     * @param Entity      $entity
     *
     * @return ObjectEntity|array|null
     */
    public function getObject(?string $id, string $method, Entity $entity)
    {
        if ($id) {
            $object = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id'=>Uuid::fromString($id)]);
            if (!$object) {
                return [
                    'message' => "No object found with this id: $id",
                    'type'    => 'Bad Request',
                    'path'    => $entity->getName(),
                    'data'    => ['id' => $id],
                ];
            } elseif ($entity != $object->getEntity()) {
                return [
                    'message' => "There is a mismatch between the provided ({$entity->getName()}) entity and the entity already attached to the object ({$object->getEntity()->getName()})",
                    'type'    => 'Bad Request',
                    'path'    => $entity->getName(),
                    'data'    => [
                        'providedEntityName' => $entity->getName(),
                        'attachedEntityName' => $object->getEntity()->getName(),
                    ],
                ];
            }

            return $object;
        } elseif ($method == 'POST') {
            $object = new ObjectEntity();
            $object->setEntity($entity);

            return $object;
        }

        return null;
    }

    /**
     * Handles an api request.
     *
     * @param Request $request
     *
     * @throws Exception
     *
     * @return Response
     */
    public function handleRequest(Request $request): Response
    {
        // Lets get our base stuff
        $path = $request->attributes->get('entity');
        $id = $request->attributes->get('id');

        // Checking and validating the id
        $contentType = $request->headers->get('accept');
        // This should be moved to the commonground service and callded true $this->serializerService->getRenderType($contentType);
        $acceptHeaderToSerialiazation = [
            'application/json'    => 'json',
            'application/ld+json' => 'jsonld',
            'application/json+ld' => 'jsonld',
            'application/hal+json'=> 'jsonhal',
            'application/json+hal'=> 'jsonhal',
            'application/xml'     => 'xml',
            'text/csv'            => 'csv',
            'text/yaml'           => 'yaml',
        ];
        if (array_key_exists($contentType, $acceptHeaderToSerialiazation)) {
            $renderType = $acceptHeaderToSerialiazation[$contentType];
        } else {
            $contentType = 'application/json';
            $renderType = 'json';
        }

        $renderTypes = ['json', 'jsonld', 'jsonhal', 'xml', 'csv', 'yaml'];
        $supportedExtensions = ['json', 'jsonld', 'jsonhal', 'xml', 'csv', 'yaml'];
        $extension = false;
        $responseType = Response::HTTP_OK;

        // Lets pull a render type form the extension if we have any
        if (strpos($path, '.') && $renderType = explode('.', $path)) {
            $path = $renderType[0];
            $renderType = end($renderType);
            $extension = $renderType;
        } elseif (strpos($id, '.') && $renderType = explode('.', $id)) {
            $id = $renderType[0];
            $renderType = end($renderType);
            $extension = $renderType;
        } else {
            $renderType = 'json';
        }

        // If we overrule the content type then we must adjust the return header acordingly
        if ($extension) {
            $contentType = array_search($extension, $acceptHeaderToSerialiazation);
        }

        // Let do a backup to defeault to an allowed render type
        if ($renderType && !in_array($renderType, $renderTypes)) {
            $result = [
                'message' => 'The rendering of this type is not suported, suported types are '.implode(',', $renderTypes),
                'type'    => 'Bad Request',
                'path'    => $path,
                'data'    => ['rendertype' => $renderType],
            ];
        }

        // Lets allow for filtering specific fields
        $fields = $request->query->get('fields');

        if ($fields) {
            // Lets deal with a comma seperated list
            if (!is_array($fields)) {
                $fields = explode(',', $fields);
            }

            $dot = new Dot();
            // Lets turn the from dor attat into an propper array
            foreach ($fields as $field => $value) {
                $dot->add($value, true);
            }

            $fields = $dot->all();
        }

        // Lets handle the entity
        $entity = $this->getEntity($path);
        // What if we canot find an entity?
        if (is_array($entity)) {
            $result = $entity;
            $entity = false;
        }

        // Lets create an object
        if ($entity && ($id || $request->getMethod() == 'POST')) {
            $object = $this->getObject($id, $request->getMethod(), $entity);
        }

        // Get a body
        if ($request->getContent()) {
            $body = json_decode($request->getContent(), true);
        }
        // If we have no body but are using form-data with a POST or PUT call instead: //TODO find a better way to deal with form-data?
        elseif ($request->getMethod() == 'POST' || $request->getMethod() == 'PUT') {
            // get other input values from form-data and put it in $body ($request->get('name'))
            $body = $this->handleFormDataBody($request, $entity);

            $formDataResult = $this->handleFormDataFiles($request, $entity, $object);
            if (array_key_exists('result', $formDataResult)) {
                $result = $formDataResult['result'];
                $responseType = Response::HTTP_BAD_REQUEST;
            } else {
                $object = $formDataResult;
            }
        }

        // Lets setup a switchy kinda thingy to handle the input (in handle functions)
        // Its a enity endpoint
        if ($entity && $id) {
            // Lets handle all different type of endpoints
            $endpointResult = $this->handleEntityEndpoint($request, [
                'object' => $object ?? null, 'body' => $body ?? null, 'fields' => $fields, 'path' => $path,
            ]);
        }
        // its an collection endpoind
        elseif ($entity) {
            $endpointResult = $this->handleCollectionEndpoint($request, [
                'object' => $object ?? null, 'body' => $body ?? null, 'fields' => $fields, 'path' => $path,
                'entity' => $entity, 'extension' => $extension,
            ]);
        }
        if (isset($endpointResult)) {
            $result = $endpointResult['result'];
            $responseType = $endpointResult['responseType'];
        }

        // If we have an error we want to set the responce type to error
        if (isset($result) && array_key_exists('type', $result) && $result['type'] == 'error') {
            $responseType = Response::HTTP_BAD_REQUEST;
        }

        // Let seriliaze the shizle
        $result = $this->serializerService->serialize(new ArrayCollection($result), $renderType, []);

        // Let return the shizle
        $response = new Response(
            $result,
            $responseType,
            ['content-type' => $contentType]
        );

        // Let intervene if it is  a known file extension
        if ($entity && in_array($extension, $supportedExtensions)) {
            $date = new \DateTime();
            $date = $date->format('Ymd_His');
            $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$entity->getName()}_{$date}.{$extension}");
            $response->headers->set('Content-Disposition', $disposition);
        }

        return $response;
    }

    /**
     * Creates a body array from the given key+values when using form-data for an POST or PUT (excl. attribute of type file).
     *
     * @param Request $request
     * @param Entity  $entity
     *
     * @return array
     */
    private function handleFormDataBody(Request $request, Entity $entity): array
    {
        // get other input values from form-data and put it in $body ($request->get('name'))
        // TODO: Maybe use $request->request->all() and filter out attributes with type = file after that? ...
        // todo... (so that we can check for input key+values that are not allowed and throw an error/warning instead of just ignoring them)
        $body = [];
        foreach ($entity->getAttributes() as $attribute) {
            if ($attribute->getType() != 'file' && $request->get($attribute->getName())) {
                $body[$attribute->getName()] = $request->get($attribute->getName());
            }
        }

        return $body;
    }

    /**
     * Handles file validation and mutations for form-data.
     *
     * @param Request      $request
     * @param Entity       $entity
     * @param ObjectEntity $objectEntity
     *
     * @throws Exception
     */
    private function handleFormDataFiles(Request $request, Entity $entity, ObjectEntity $objectEntity)
    {
        if (count($request->files) > 0) {
            // Check if this entity has an attribute with type file
            $criteria = Criteria::create()->andWhere(Criteria::expr()->eq('type', 'file'))->setMaxResults(1);
            $attributes = $entity->getAttributes()->matching($criteria);

            // If no attribute with type file found, throw an error
            if ($attributes->isEmpty()) {
                $result = [
                    'message' => 'No attribute with type file found for this entity',
                    'type'    => 'Bad Request',
                    'path'    => $entity->getName(),
                    'data'    => [],
                ];

                return ['result' => $result];
            } else {
                // Else set attribute to the attribute with type = file
                $attribute = $attributes->first();
                // Get the value (file(s)) for this attribute
                $value = $request->files->get($attribute->getName());

                if ($attribute->getMultiple()) {
                    // When using form-data with multiple=true for files the form-data key should have [] after the name (to make it an array, example key: files[], and support multiple file uploads with one key+multiple files in a single value)
                    if (!is_array($value)) {
                        $objectEntity->addError($attribute->getName(), 'Multiple is set for this attribute. Expecting an array of files. (Use array in form-data with the following key: '.$attribute->getName().'[])');
                    } else {
                        // Loop through all files, validate them and store them in the files ArrayCollection
                        foreach ($value as $file) {
                            $objectEntity = $this->validationService->validateFile($objectEntity, $attribute, $this->validationService->uploadedFileToFileArray($file, $file->getClientOriginalName()));
                        }
                    }
                } else {
                    // Validate (and create/update) this file
                    $objectEntity = $this->validationService->validateFile($objectEntity, $attribute, $this->validationService->uploadedFileToFileArray($value));
                }

                return $objectEntity;
            }
        }
    }

    /**
     * Handles entity endpoints.
     *
     * @param Request $request
     * @param array   $info    Array with some required info, must contain the following keys: object, body, fields & path.
     *
     * @throws Exception
     *
     * @return array
     */
    private function handleEntityEndpoint(Request $request, array $info): array
    {
        // Lets setup a switchy kinda thingy to handle the input
        // Its an enity endpoint
        switch ($request->getMethod()) {
            case 'GET':
                $result = $this->handleGet($info['object'], $info['fields']);
                $responseType = Response::HTTP_OK;
                break;
            case 'PUT':
                // Transfer the variable to the service
                $result = $this->handleMutation($info['object'], $info['body'], $info['fields']);
                $responseType = Response::HTTP_OK;
                break;
            case 'DELETE':
                $result = $this->handleDelete($info['object']);
                $responseType = Response::HTTP_NO_CONTENT;
                break;
            default:
                $result = [
                    'message' => 'This method is not allowed on this endpoint, allowed methods are GET, PUT and DELETE',
                    'type'    => 'Bad Request',
                    'path'    => $info['path'],
                    'data'    => ['method' => $request->getMethod()],
                ];
                $responseType = Response::HTTP_BAD_REQUEST;
                break;
        }

        return [
            'result'       => $result ?? null,
            'responseType' => $responseType,
        ];
    }

    /**
     * Handles collection endpoints.
     *
     * @param Request $request
     * @param array   $info    Array with some required info, must contain the following keys: object, body, fields, path, entity & extension.
     *
     * @throws Exception
     *
     * @return array
     */
    private function handleCollectionEndpoint(Request $request, array $info): array
    {
        // its a collection endpoint
        switch ($request->getMethod()) {
            case 'GET':
                $result = $this->handleSearch($info['entity']->getName(), $request, $info['fields'], $info['extension']);
                $responseType = Response::HTTP_OK;
                break;
            case 'POST':
                // Transfer the variable to the service
                $result = $this->handleMutation($info['object'], $info['body'], $info['fields']);
                $responseType = Response::HTTP_CREATED;
                break;
            default:
                $result = [
                    'message' => 'This method is not allowed on this endpoint, allowed methods are GET and POST',
                    'type'    => 'Bad Request',
                    'path'    => $info['path'],
                    'data'    => ['method' => $request->getMethod()],
                ];
                $responseType = Response::HTTP_BAD_REQUEST;
                break;
        }

        return [
            'result'       => $result ?? null,
            'responseType' => $responseType,
        ];
    }

    /**
     * This function handles data mutations on EAV Objects.
     *
     * @param ObjectEntity $object
     * @param array        $body
     * @param $fields
     *
     * @throws Exception
     *
     * @return array
     */
    public function handleMutation(ObjectEntity $object, array $body, $fields): array
    {
        // Validation stap
        $object = $this->validationService->validateEntity($object, $body);

        // Let see if we have errors
        if ($object->getHasErrors()) {
            return $this->returnErrors($object);
        }

        // TODO: use (ObjectEntity) $object->promises instead
        /* this way of working is way vasther then passing stuff trough the object's, lets also implement this for error checks */
        if (!empty($this->validationService->promises)) {
            Utils::settle($this->validationService->promises)->wait();

            foreach ($this->validationService->promises as $promise) {
                echo $promise->wait();
            }
        }

        // Check optional conditional logic
        $object->checkConditionlLogic();

        // Afther guzzle has cleared we need to again check for errors
        if ($object->getHasErrors()) {
            return $this->returnErrors($object);
        }

        // Saving the data
        $this->em->persist($object);
        $this->em->flush();

        return $this->renderResult($object, $fields);
    }

    /**
     * Handles a get item api call.
     *
     * @param ObjectEntity $object
     * @param $fields
     *
     * @return array
     */
    public function handleGet(ObjectEntity $object, $fields): array
    {
        return $this->renderResult($object, $fields);
    }

    /**
     * Handles a search (collection) api call.
     *
     * @param string  $entityName
     * @param Request $request
     * @param $fields
     * @param $extension
     *
     * @return array|array[]
     */
    public function handleSearch(string $entityName, Request $request, $fields, $extension): array
    {
        $query = $request->query->all();
        unset($query['limit']);
        unset($query['page']);
        unset($query['start']);
        $limit = (int) ($request->query->get('limit') ?? 25); // These type casts are not redundant!
        $page = (int) ($request->query->get('page') ?? 1);
        $start = (int) ($request->query->get('start') ?? 1);

        if ($start > 1) {
            $offset = $start - 1;
        } else {
            $offset = ($page - 1) * $limit;
        }

        /* @todo we might want some filtering here, also this should be in the entity repository */
        $entity = $this->em->getRepository('App:Entity')->findOneBy(['name'=>$entityName]);
        $total = $this->em->getRepository('App:ObjectEntity')->findByEntity($entity, $query); // todo custom sql to count instead of getting items.
        $objects = $this->em->getRepository('App:ObjectEntity')->findByEntity($entity, $query, $offset, $limit);

        $results = [];
        foreach ($objects as $object) {
            $results[] = $this->renderResult($object, $fields);
        }

        // Lets skip the pritty styff when dealing with csv
        if (in_array($request->headers->get('accept'), ['text/csv']) || in_array($extension, ['csv'])) {
            return $results;
        }

        // If not lets make it pritty
        $results = ['results'=>$results];
        $results['total'] = count($total);
        $results['limit'] = $limit;
        $results['pages'] = ceil($results['total'] / $limit);
        $results['pages'] = $results['pages'] == 0 ? 1 : $results['pages'];
        $results['page'] = floor($offset / $limit) + 1;
        $results['start'] = $offset + 1;

        return $results;
    }

    /**
     * Handles a delete api call.
     *
     * @param ObjectEntity $object
     *
     * @return array
     */
    public function handleDelete(ObjectEntity $object): array
    {
        $this->em->remove($object);
        $this->em->flush();

        return [];
    }

    /**
     * Builds the error response for an objectEntity that contains errors.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return array
     */
    public function returnErrors(ObjectEntity $objectEntity): array
    {
        return [
            'message' => 'The where errors',
            'type'    => 'error',
            'path'    => $objectEntity->getEntity()->getName(),
            'data'    => $objectEntity->getAllErrors(),
        ];
    }

    /**
     * Renders the result for a ObjectEntity that will be used for the response after a successful api call.
     *
     * @param ObjectEntity         $result
     * @param ArrayCollection|null $maxDepth
     * @param $fields
     *
     * @return array
     */
    public function renderResult(ObjectEntity $result, $fields, ArrayCollection $maxDepth = null): array
    {
        $response = [];

        if ($result->getUri()) {
            $response['@uri'] = $result->getUri();
        }

        // Lets start with the external results
        // TODO: for get (item and collection) calls this seems to only contain the @uri, not the full extern object (result) !!! as if setExternalResult is not saved properly?
        $response = array_merge($response, $result->getExternalResult());

        // Lets move some stuff out of the way
        if (array_key_exists('@context', $response)) {
            $response['@gateway/context'] = $response['@context'];
        }
        if (array_key_exists('id', $response)) {
            $response['@gateway/id'] = $response['id'];
        }
        if (array_key_exists('@type', $response)) {
            $response['@gateway/type'] = $response['@type'];
        }

        $response = $this->renderValues($result, $fields, $maxDepth);

        // Lets make it personal
        $response['@context'] = '/contexts/'.ucfirst($result->getEntity()->getName());
        $response['@id'] = ucfirst($result->getEntity()->getName()).'/'.$result->getId();
        $response['@type'] = ucfirst($result->getEntity()->getName());
        $response['id'] = $result->getId();

        return $response;
    }

    /**
     * Renders the values of an ObjectEntity for the renderResult function.
     *
     * @param ObjectEntity $result
     * @param $fields
     * @param ArrayCollection|null $maxDepth
     * @return array
     */
    private function renderValues(ObjectEntity $result, $fields, ?ArrayCollection $maxDepth): array
    {
        $response = [];

        // Lets keep track of objects we already rendered, for inversedBy, checking maxDepth 1:
        if (is_null($maxDepth)) {
            $maxDepth = new ArrayCollection();
        }
        $maxDepth->add($result);

        foreach ($result->getObjectValues() as $value) {
            $attribute = $value->getAttribute();

            // Lets deal with fields filtering
            if (is_array($fields) and !array_key_exists($attribute->getName(), $fields)) {
                continue;
            }

            // @todo ruben: kan iemand me een keer uitleggen wat hier gebeurd?
            // todo @ruben: zie: https://conduction.atlassian.net/browse/BISC-539 (comments) over usedProperties
            // Only render the attributes that are used
            if (!is_null($result->getEntity()->getUsedProperties()) && !in_array($attribute->getName(), $result->getEntity()->getUsedProperties())) {
                continue;
            }
            if ($attribute->getType() == 'object') {
                $response = $this->renderObjects($value, $fields, $maxDepth);
                continue;
            } elseif ($attribute->getType() == 'file') {
                $response = $this->renderFiles($value);
                continue;
            }
            $response[$attribute->getName()] = $value->getValue();
        }

        return $response;
    }

    /**
     * Renders the objects of a value with attribute type 'object' for the renderValues function.
     *
     * @param Value $value
     * @param $fields
     * @param ArrayCollection $maxDepth
     * @return array
     */
    private function renderObjects(Value $value, $fields, ArrayCollection $maxDepth): array
    {
        $response = [];
        $attribute = $value->getAttribute();

        $subFields = null;
        if (is_array($fields)) {
            $subFields = $fields[$attribute->getName()];
        }

        if ($value->getValue() == null) {
            $response[$attribute->getName()] = null;
            return $response;
        }

        // If we have only one Object (because multiple = false)
        if (!$attribute->getMultiple()) {
            // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
            if (!$maxDepth->contains($value->getValue())) {
                $response[$attribute->getName()] = $this->renderResult($value->getValue(), $subFields, $maxDepth);
            }
            return $response;
        }

        // If we can have multiple Objects (because multiple = true)
        $objects = $value->getValue();
        $objectsArray = [];
        foreach ($objects as $object) {
            // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
            if (!$maxDepth->contains($object)) {
                $objectsArray[] = $this->renderResult($object, $subFields, $maxDepth);
                continue;
            }
            // If multiple = true and a subresource contains an inversedby list of resources that contains this resource ($result), only show the @id
            $objectsArray[] = ['@id' => ucfirst($object->getEntity()->getName()).'/'.$object->getId()];
        }
        $response[$attribute->getName()] = $objectsArray;
        return $response;
    }

    /**
     * Renders the files of a value with attribute type 'file' for the renderValues function.
     *
     * @param Value $value
     * @return array
     */
    private function renderFiles(Value $value): array
    {
        $attribute = $value->getAttribute();

        if ($value->getValue() == null) {
            $response[$attribute->getName()] = null;
            return $response;
        }
        if (!$attribute->getMultiple()) {
            $response[$attribute->getName()] = $this->renderFileResult($value->getValue());
            return $response;
        }
        $files = $value->getValue();
        $filesArray = [];
        foreach ($files as $file) {
            $filesArray[] = $this->renderFileResult($file);
        }
        $response[$attribute->getName()] = $filesArray;

        return $response;
    }

    /**
     * Renders the result for a File that will be used (in renderFiles) for the response after a successful api call.
     *
     * @param File $file
     *
     * @return array
     */
    private function renderFileResult(File $file): array
    {
        return [
            'name'      => $file->getName(),
            'extension' => $file->getExtension(),
            'mimeType'  => $file->getMimeType(),
            'size'      => $file->getSize(),
            'base64'    => $file->getBase64(),
        ];
    }
}
