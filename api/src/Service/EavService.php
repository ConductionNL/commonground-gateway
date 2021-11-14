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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;

class EavService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private ValidationService $validationService;
    private SerializerService $serializerService;
    private SerializerInterface $serializer;
    private AuthorizationService $authorizationService;
    private ConvertToGatewayService $convertToGatewayService;
    private SessionInterface $session;
    private ObjectEntityService $objectEntityService;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, ValidationService $validationService, SerializerService $serializerService, SerializerInterface $serializer, AuthorizationService $authorizationService, ConvertToGatewayService $convertToGatewayService, SessionInterface $session, ObjectEntityService $objectEntityService)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->validationService = $validationService;
        $this->serializerService = $serializerService;
        $this->serializer = $serializer;
        $this->authorizationService = $authorizationService;
        $this->convertToGatewayService = $convertToGatewayService;
        $this->session = $session;
        $this->objectEntityService = $objectEntityService;
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
     * @throws Exception
     *
     * @return ObjectEntity|array|null
     */
    public function getObject(?string $id, string $method, Entity $entity)
    {
        if ($id) {
            // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
            if (!$object = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'id' => $id])) {
                if (!$object = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'externalId' => $id])) {
                    // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside of the gateway for an existing object.
                    $object = $this->convertToGatewayService->convertToGatewayObject($entity, null, $id);
                    if (!$object) {
                        return [
                            'message' => 'Could not find an object with id '.$id.' of type '.$entity->getName(),
                            'type'    => 'Bad Request',
                            'path'    => $entity->getName(),
                            'data'    => ['id' => $id],
                        ];
                    }
                }
            }
            if ($object instanceof ObjectEntity && $entity != $object->getEntity()) {
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

            return $this->objectEntityService->handleOwner($object);
        } elseif ($method == 'POST') {
            $object = new ObjectEntity();
            $object->setEntity($entity);

            return $this->objectEntityService->handleOwner($object);
        }

        return null;
    }

    public function checkOwner(ObjectEntity $objectEntity): bool
    {
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
        $requestBase = $this->getRequestBase($request);
        $result = $requestBase['result'];
        $contentType = $this->getRequestContentType($request, $requestBase['extension']);

        // Lets handle the entity
        $entity = $this->getEntity($requestBase['path']);
        // What if we canot find an entity?
        if (is_array($entity)) {
            $result = $entity;
            $entity = false;
        }

        // Set default responseType
        $responseType = Response::HTTP_OK;

        // Lets create an object
        if ($entity && ($requestBase['id'] || $request->getMethod() == 'POST')) {
            $object = $this->getObject($requestBase['id'], $request->getMethod(), $entity);
            // Lets check if the user is allowed to view/edit this resource.

            if (!$this->objectEntityService->checkOwner($object)) {
                if ($object->getOrganization() && !in_array($object->getOrganization(), $this->session->get('organizations') ?? []) // TODO: do we want to throw an error if there are nog organizations in the session? (because of logging out)
                    //                || $object->getApplication() != $this->session->get('application') // TODO: Check application
                ) {
                    $object = null; // Needed so we return the error and not the object!
                    $responseType = Response::HTTP_FORBIDDEN;
                    $result = [
                        'message' => 'You are forbidden to view or edit this resource.',
                        'type'    => 'Forbidden',
                        'path'    => $entity->getName(),
                        'data'    => ['id' => $requestBase['id']],
                    ];
                } elseif (array_key_exists('type', $object) && $object['type'] == 'Bad Request') {
                    $responseType = Response::HTTP_BAD_REQUEST;
                    $result = $object;
                }
            }
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

        // Lets allow for filtering specific fields
        $fields = $this->getRequestFields($request);

        // Lets setup a switchy kinda thingy to handle the input (in handle functions)
        // Its a enity endpoint
        if ($entity && $requestBase['id'] && isset($object) && $object instanceof ObjectEntity) {
            // Lets handle all different type of endpoints
            $endpointResult = $this->handleEntityEndpoint($request, [
                'object' => $object ?? null, 'body' => $body ?? null, 'fields' => $fields, 'path' => $requestBase['path'],
            ]);
        }
        // its an collection endpoind
        elseif ($entity && $responseType == Response::HTTP_OK) {
            $endpointResult = $this->handleCollectionEndpoint($request, [
                'object' => $object ?? null, 'body' => $body ?? null, 'fields' => $fields, 'path' => $requestBase['path'],
                'entity' => $entity, 'extension' => $requestBase['extension'],
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
        $result = $this->serializerService->serialize(new ArrayCollection($result), $requestBase['renderType'], []);

        // Let return the shizle
        $response = new Response(
            $result,
            $responseType,
            ['content-type' => $contentType]
        );

        // Let intervene if it is  a known file extension
        $supportedExtensions = ['json', 'jsonld', 'jsonhal', 'xml', 'csv', 'yaml'];
        if ($entity && in_array($requestBase['extension'], $supportedExtensions)) {
            $date = new \DateTime();
            $date = $date->format('Ymd_His');
            $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$entity->getName()}_{$date}.{$requestBase['extension']}");
            $response->headers->set('Content-Disposition', $disposition);
        }

        return $response;
    }

    /**
     * Gets the path, id, extension & renderType from the Request.
     *
     * @param Request $request
     *
     * @return array
     */
    private function getRequestBase(Request $request): array
    {
        // Lets get our base stuff
        $path = $request->attributes->get('entity');
        $id = $request->attributes->get('id');
        $extension = false;

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

        return [
            'path'       => $path,
            'id'         => $id,
            'extension'  => $extension,
            'renderType' => $renderType,
            'result'     => $this->checkAllowedRenderTypes($renderType, $path),
        ];
    }

    /**
     * Let do a backup to default to an allowed render type.
     *
     * @param string $renderType
     * @param string $path
     *
     * @return array|null
     */
    private function checkAllowedRenderTypes(string $renderType, string $path): ?array
    {
        // Let do a backup to defeault to an allowed render type
        $renderTypes = ['json', 'jsonld', 'jsonhal', 'xml', 'csv', 'yaml'];
        if ($renderType && !in_array($renderType, $renderTypes)) {
            return [
                'message' => 'The rendering of this type is not suported, suported types are '.implode(',', $renderTypes),
                'type'    => 'Bad Request',
                'path'    => $path,
                'data'    => ['rendertype' => $renderType],
            ];
        }

        return null;
    }

    private function getRequestContentType(Request $request, string $extension): string
    {
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

        $contentType = $request->headers->get('accept');
        // If we overrule the content type then we must adjust the return header acordingly
        if ($extension) {
            $contentType = array_search($extension, $acceptHeaderToSerialiazation);
        } elseif (!array_key_exists($contentType, $acceptHeaderToSerialiazation)) {
            $contentType = 'application/json';
        }

        return $contentType;
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
     * Gets fields from the request to use for filtering specific fields.
     *
     * @param Request $request
     *
     * @return array
     */
    private function getRequestFields(Request $request): ?array
    {
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

        return $fields;
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
                if (isset($result) && array_key_exists('type', $result) && $result['type'] == 'Forbidden') {
                    $responseType = Response::HTTP_FORBIDDEN;
                }
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
        // Check if session contains an activeOrganization, so we can't do calls without it. So we do not create objects with no organization!
        if (empty($this->session->get('activeOrganization'))) {
            return [
                'message' => 'An active organization is required in the session, please login to create a new session.',
                'type'    => 'Forbidden',
                'path'    => $object->getEntity()->getName(),
                'data'    => ['activeOrganization' => null],
            ];
        }

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
        $object->checkConditionlLogic(); // Old way of checking condition logic

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
        if ($request->query->get('updateGatewayPool') == 'true') { // TODO: remove this when we have a better way of doing this?!
            $this->convertToGatewayService->convertEntityObjects($entity);
        }
        unset($query['updateGatewayPool']);

        $filterCheck = $this->em->getRepository('App:ObjectEntity')->getFilterParameters($entity);
        // Lets add generic filters
        $filterCheck[] = 'fields';
        $filterCheck[] = 'extend';
        foreach ($query as $param => $value) {
            $param = str_replace(['_'], ['.'], $param);
            $param = str_replace(['..'], ['._'], $param);
            if (substr($param, 0, 1) == '.') {
                $param = '_'.ltrim($param, $param[0]);
            }
            if (!in_array($param, $filterCheck)) {
                $filterCheckStr = '';
                foreach ($filterCheck as $filter) {
                    $filterCheckStr = $filterCheckStr.$filter;
                    if ($filter != end($filterCheck)) {
                        $filterCheckStr = $filterCheckStr.', ';
                    }
                }

                if (is_array($value)) {
                    $value = end($value);
                }

                return [
                    'message' => 'Unsupported queryParameter ('.$param.'). Supported queryParameters: '.$filterCheckStr,
                    'type'    => 'error',
                    'path'    => $entity->getName().'?'.$param.'='.$value,
                    'data'    => ['queryParameter'=>$param],
                ];
            }
        }
        $total = $this->em->getRepository('App:ObjectEntity')->countByEntity($entity, $query);
        $objects = $this->em->getRepository('App:ObjectEntity')->findByEntity($entity, $query, $offset, $limit);

        // Lets see if we need to flatten te responce (for example csv use)
        $flat = false;
        if (in_array($request->headers->get('accept'), ['text/csv']) || in_array($extension, ['csv'])) {
            $flat = true;
        }

        $results = [];
        foreach ($objects as $object) {
            $results[] = $this->renderResult($object, $fields, null, $flat);
        }

        // If we need a flattend responce we are al done
        if ($flat) {
            return $results;
        }

        // If not lets make it pritty
        $results = ['results'=>$results];
        $results['total'] = $total;
        $results['limit'] = $limit;
        $results['pages'] = ceil($total / $limit);
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
    public function renderResult(ObjectEntity $result, $fields, ArrayCollection $maxDepth = null, bool $flat = false, int $level = 0): array
    {
        $response = [];

        // Lets start with the external results
        if (!empty($result->getExternalResult())) {
            $response = array_merge($response, $result->getExternalResult());
        } elseif ($this->commonGroundService->isResource($result->getExternalResult())) {
            $response = array_merge($response, $this->commonGroundService->getResource($result->getExternalResult()));
        } elseif ($this->commonGroundService->isResource($result->getUri())) {
            $response = array_merge($response, $this->commonGroundService->getResource($result->getUri()));
        }

        // Only render the attributes that are available for this Entity (filters out unwanted properties from external results)
        if (!is_null($result->getEntity()->getAvailableProperties())) {
            $response = array_filter($response, function ($propertyName) use ($result) {
                if (str_contains($propertyName, '@gateway/')) {
                    return true;
                }

                return in_array($propertyName, $result->getEntity()->getAvailableProperties());
            }, ARRAY_FILTER_USE_KEY);
        }

        // Let overwrite the id with the gateway id
        $response['id'] = $result->getId();

        // Lets make sure we don't return stuf thats not in our field list
        // @todo make array filter instead of loop
        // @todo on a higher lever we schould have a filter result function that can also be aprouched by the authentication
        foreach ($response as $key => $value) {
            if (is_array($fields) && !array_key_exists($key, $fields)) {
                unset($response[$key]);
            }
        }

        // Let get the internal results
        $response = array_merge($response, $this->renderValues($result, $fields, $maxDepth, $flat, $level));

        // Lets sort the result alphabeticly

        // Lets skip the pritty styff when dealing with a flat object
        if ($flat) {
            ksort($response);

            return $response;
        }

        // Lets make it personal
        $gatewayContext = [];
        $gatewayContext['@id'] = ucfirst($result->getEntity()->getName()).'/'.$result->getId();
        $gatewayContext['@type'] = ucfirst($result->getEntity()->getName());
        $gatewayContext['@context'] = '/contexts/'.ucfirst($result->getEntity()->getName());
        $gatewayContext['@dateCreated'] = $result->getDateCreated();
        $gatewayContext['@dateModified'] = $result->getDateModified();
        $gatewayContext['@organization'] = $result->getOrganization();
        $gatewayContext['@application'] = $result->getApplication();
        $gatewayContext['@owner'] = $result->getOwner();
        if ($result->getUri()) {
            $gatewayContext['@uri'] = $result->getUri();
        }
        // Lets move some stuff out of the way
        if (array_key_exists('@context', $response)) {
            $gatewayContext['@gateway/context'] = $response['@context'];
        }
        if ($result->getExternalId()) {
            $gatewayContext['@gateway/id'] = $result->getExternalId();
        } elseif (array_key_exists('id', $response)) {
            $gatewayContext['@gateway/id'] = $response['id'];
        }
        if (array_key_exists('@type', $response)) {
            $gatewayContext['@gateway/type'] = $response['@type'];
        }
        if (is_array($fields)) {
            $gatewayContext['@fields'] = $fields;
        }
        $gatewayContext['@level'] = $level;
        $gatewayContext['id'] = $result->getId();

        ksort($response);
        $response = $gatewayContext + $response;

        return $response;
    }

    /**
     * Renders the values of an ObjectEntity for the renderResult function.
     *
     * @param ObjectEntity $result
     * @param $fields
     * @param ArrayCollection|null $maxDepth
     *
     * @return array
     */
    private function renderValues(ObjectEntity $result, $fields, ?ArrayCollection $maxDepth = null, bool $flat = false, int $level = 0): array
    {
        $response = [];

        // Lets keep track of how deep in the three we are
        $level++;

        // Lets keep track of objects we already rendered, for inversedBy, checking maxDepth 1:
        if (is_null($maxDepth)) {
            $maxDepth = new ArrayCollection();
        }
        $maxDepth->add($result);

        foreach ($result->getObjectValues() as $value) {
            $attribute = $value->getAttribute();
            $subfields = false;

            // Lets deal with fields filtering
            if (is_array($fields) and !array_key_exists($attribute->getName(), $fields)) {
                continue;
            } elseif (is_array($fields) and array_key_exists($attribute->getName(), $fields)) {
                $subfields = $fields[$attribute->getName()];
            }
            if (!$subfields) {
                $subfields = $fields;
            }

            // @todo ruben: kan iemand me een keer uitleggen wat hier gebeurd?
            // todo @ruben: zie: https://conduction.atlassian.net/browse/BISC-539 (comments) over usedProperties

            // Only render the attributes that are used
            if (!is_null($result->getEntity()->getUsedProperties()) && !in_array($attribute->getName(), $result->getEntity()->getUsedProperties())) {
                continue;
            }

            if ($attribute->getType() == 'object') {
                try {
                    if (!$this->objectEntityService->checkOwner($result)) {
                        $this->authorizationService->checkAuthorization($this->authorizationService->getRequiredScopes('GET', $attribute));
                    }

                    // TODO: this code might cause for very slow api calls, another fix could be to always set inversedBy on both (sides) attributes so we only have to check $attribute->getInversedBy()
                    // If this attribute has no inversedBy but the Object we are rendering has parent objects.
                    // Check if one of the parent objects has an attribute with inversedBy -> this attribute.
                    $parentInversedByAttribute = [];
                    if (!$attribute->getInversedBy() && count($result->getSubresourceOf()) > 0) {
                        // Get all parent (value) objects...
                        $parentInversedByAttribute = $result->getSubresourceOf()->filter(function (Value $valueObject) use ($attribute) {
                            // ...that have getInversedBy set to $attribute
                            $inversedByAttributes = $valueObject->getObjectEntity()->getEntity()->getAttributes()->filter(function (Attribute $item) use ($attribute) {
                                return $item->getInversedBy() === $attribute;
                            });
                            if (count($inversedByAttributes) > 0) {
                                return true;
                            }

                            return false;
                        });
                    }
                    // Only use maxDepth for subresources if inversedBy is set on this attribute or if one of the parent objects has an attribute with inversedBy this attribute.
                    // If we do not check this, we might skip rendering of entire objects (subresources) we do want to render!!!
                    if ($attribute->getInversedBy() || count($parentInversedByAttribute) > 0) {
                        $response[$attribute->getName()] = $this->renderObjects($value, $subfields, $maxDepth, $flat, $level);
                    } else {
                        $response[$attribute->getName()] = $this->renderObjects($value, $subfields, null, $flat, $level);
                    }

                    if ($response[$attribute->getName()] === ['continue'=>'continue']) {
                        unset($response[$attribute->getName()]);
                    }
                    continue;
                } catch (AccessDeniedException $exception) {
                    continue;
                }
            } elseif ($attribute->getType() == 'file') {
                $response[$attribute->getName()] = $this->renderFiles($value);
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
     * @param ArrayCollection|null $maxDepth
     *
     * @return array|null
     */
    private function renderObjects(Value $value, $fields, ?ArrayCollection $maxDepth, bool $flat = false, int $level = 0): ?array
    {
        $attribute = $value->getAttribute();

        // Lets keep track of objects we already rendered, for inversedBy, checking maxDepth 1:
        if (is_null($maxDepth)) {
            $maxDepth = new ArrayCollection();
        }

        if ($value->getValue() == null) {
            return null;
        }

        // If we have only one Object (because multiple = false)
        if (!$attribute->getMultiple()) {
            // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
            if (!$maxDepth->contains($value->getValue())) {
                return $this->renderResult($value->getValue(), $fields, $maxDepth, $flat, $level);
            }

            return ['continue'=>'continue']; //TODO We want this
        }

        // If we can have multiple Objects (because multiple = true)
        $objects = $value->getValue();
        $objectsArray = [];
        foreach ($objects as $object) {
            // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
            if (!$maxDepth->contains($object)) {
                $objectsArray[] = $this->renderResult($object, $fields, $maxDepth, $level);
                continue;
            }
            // If multiple = true and a subresource contains an inversedby list of resources that contains this resource ($result), only show the @id
            $objectsArray[] = ['@id' => ucfirst($object->getEntity()->getName()).'/'.$object->getId()];
        }

        return $objectsArray;
    }

    /**
     * Renders the files of a value with attribute type 'file' for the renderValues function.
     *
     * @param Value $value
     *
     * @return array|null
     */
    private function renderFiles(Value $value): ?array
    {
        $attribute = $value->getAttribute();

        if ($value->getValue() == null) {
            return null;
        }
        if (!$attribute->getMultiple()) {
            return $this->renderFileResult($value->getValue());
        }
        $files = $value->getValue();
        $filesArray = [];
        foreach ($files as $file) {
            $filesArray[] = $this->renderFileResult($file);
        }

        return $filesArray;
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
