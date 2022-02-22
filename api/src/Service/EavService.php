<?php

namespace App\Service;

use Adbar\Dot;
use App\Entity\Application;
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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
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
    private ResponseService $responseService;
    private ParameterBagInterface $parameterBag;
    private TranslationService $translationService;
    private FunctionService $functionService;

    public function __construct(
        EntityManagerInterface $em,
        CommonGroundService $commonGroundService,
        ValidationService $validationService,
        SerializerService $serializerService,
        SerializerInterface $serializer,
        AuthorizationService $authorizationService,
        ConvertToGatewayService $convertToGatewayService,
        SessionInterface $session,
        ObjectEntityService $objectEntityService,
        ResponseService $responseService,
        ParameterBagInterface $parameterBag,
        TranslationService $translationService,
        FunctionService $functionService
    ) {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->validationService = $validationService;
        $this->serializerService = $serializerService;
        $this->serializer = $serializer;
        $this->authorizationService = $authorizationService;
        $this->convertToGatewayService = $convertToGatewayService;
        $this->session = $session;
        $this->objectEntityService = $objectEntityService;
        $this->responseService = $responseService;
        $this->parameterBag = $parameterBag;
        $this->translationService = $translationService;
        $this->functionService = $functionService;
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
            // make sure $id is actually an uuid
            if (Uuid::isValid($id) == false) {
                return [
                    'message' => 'The given id ('.$id.') is not a valid uuid.',
                    'type'    => 'Bad Request',
                    'path'    => $entity->getName(),
                    'data'    => ['id' => $id],
                ];
            }

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

            if ($method == 'POST' || $method == 'PUT') {
                return $this->objectEntityService->handleOwner($object);
            }

            return $object;
        } elseif ($method == 'POST') {
            $object = new ObjectEntity();
            $object->setEntity($entity);
            // if entity->function == 'organization', organization for this ObjectEntity will be changed later in handleMutation
            $object->setOrganization($this->session->get('activeOrganization'));
            $object->setApplication($this->session->get('application'));

            return $this->objectEntityService->handleOwner($object);
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
        $requestBase = $this->getRequestBase($request);
        $contentType = $this->getRequestContentType($request, $requestBase['extension']);
        $entity = $this->getEntity($requestBase['path']);
        $body = []; // Lets default

        // What if we canot find an entity?
        if (is_array($entity)) {
            $resultConfig['responseType'] = Response::HTTP_BAD_REQUEST;
            $resultConfig['result'] = $entity;
            $entity = null;
        }

        // Get a body
        if ($request->getContent()) {
            //@todo support xml messages
            $body = json_decode($request->getContent(), true);
        }
//        // If we have no body but are using form-data with a POST or PUT call instead: //TODO find a better way to deal with form-data?
//        elseif ($request->getMethod() == 'POST' || $request->getMethod() == 'PUT') {
//            // get other input values from form-data and put it in $body ($request->get('name'))
//            $body = $this->handleFormDataBody($request, $entity);
//
//            $formDataResult = $this->handleFormDataFiles($request, $entity, $object);
//            if (array_key_exists('result', $formDataResult)) {
//                $result = $formDataResult['result'];
//                $responseType = Response::HTTP_BAD_REQUEST;
//            } else {
//                $object = $formDataResult;
//            }
//        }

        if (!isset($resultConfig['result'])) {
            $resultConfig = $this->generateResult($request, $entity, $requestBase, $body);
        }

        $options = [];
        switch ($contentType) {
            case 'text/csv':
                $options = [
                    CsvEncoder::ENCLOSURE_KEY   => '"',
                    CsvEncoder::ESCAPE_CHAR_KEY => '+',
                ];

                // Lets allow _mapping tot take place
                /* @todo remove the old fields support */
                /* @todo make this universal */
                if ($mapping = $request->query->get('_mapping')) {
                    foreach ($resultConfig['result'] as $key =>  $result) {
                        $resultConfig['result'][$key] = $this->translationService->dotHydrator([], $result, $mapping);
                    }
                }
        }

        // Lets seriliaze the shizle
        $result = $this->serializerService->serialize(new ArrayCollection($resultConfig['result']), $requestBase['renderType'], $options);

        // Afther that we transale the shizle out of it

        /*@todo this is an ugly catch to make sure it only applies to bisc */
        /*@todo this should DEFINTLY be configuration */
        if ($contentType === 'text/csv') {
            $translationVariables = [
                'OTHER'     => 'Anders',
                'YES_OTHER' => '"Ja, Anders"',
            ];

            $result = $this->translationService->parse($result, true, $translationVariables);
        } else {
            $translationVariables = [];
        }

        /*
        if ($contentType === 'text/csv') {
            $replacements = [
                '/student\.person.givenName/'                        => 'Voornaam',
                '/student\.person.additionalName/'                   => 'Tussenvoegsel',
                '/student\.person.familyName/'                       => 'Achternaam',
                '/student\.person.emails\..\.email/'                 => 'E-mail adres',
                '/student.person.telephones\..\.telephone/'          => 'Telefoonnummer',
                '/student\.intake\.dutchNTLevel/'                    => 'NT1/NT2',
                '/participations\.provider\.id/'                     => 'ID aanbieder',
                '/participations\.provider\.name/'                   => 'Aanbieder',
                '/participations/'                                   => 'Deelnames',
                '/learningResults\..\.id/'                           => 'ID leervraag',
                '/learningResults\..\.verb/'                         => 'Werkwoord',
                '/learningResults\..\.subjectOther/'                 => 'Onderwerp (anders)',
                '/learningResults\..\.subject/'                      => 'Onderwerp',
                '/learningResults\..\.applicationOther/'             => 'Toepasing (anders)',
                '/learningResults\..\.application/'                  => 'Toepassing',
                '/learningResults\..\.levelOther/'                   => 'Niveau (anders)',
                '/learningResults\..\.level/'                        => 'Niveau',
                '/learningResults\..\.participation/'                => 'Deelname',
                '/learningResults\..\.testResult/'                   => 'Test Resultaat',
                '/agreements/'                                       => 'Overeenkomsten',
                '/desiredOffer/'                                     => 'Gewenst aanbod',
                '/advisedOffer/'                                     => 'Geadviseerd aanbod',
                '/offerDifference/'                                  => 'Aanbod verschil',
                '/person\.givenName/'                                => 'Voornaam',
                '/person\.additionalName/'                           => 'Tussenvoegsel',
                '/person\.familyName/'                               => 'Achternaam',
                '/person\.emails\..\.email/'                         => 'E-mail adres',
                '/person.telephones\..\.telephone/'                  => 'Telefoonnummer',
                '/intake\.date/'                                     => 'Aanmaakdatum',
                '/intake\.referringOrganizationEmail/'               => 'Verwijzer Email',
                '/intake\.referringOrganizationOther/'               => 'Verwijzer Telefoon',
                '/intake\.referringOrganization/'                    => 'Verwijzer',
                '/intake\.foundViaOther/'                            => 'Via (anders)',
                '/intake\.foundVia/'                                 => 'Via',
                '/roles/'                                            => 'Rollen',
                '/student\.id/'                                      => 'ID deelnemer',
                '/description/'                                      => 'Beschrijving',
                '/motivation/'                                       => 'Leervraag',
                '/languageHouse\.name/'                              => 'Naam taalhuis',
            ];

            foreach ($replacements as $key => $value) {
                $result = preg_replace($key, $value, $result);
            }
        }
        */

        // Let return the shizle
        $response = new Response(
            $result,
            $resultConfig['responseType'],
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

        // Lets see if we have to log an error
        if ($this->responseService->checkForErrorResponse($resultConfig['result'], $resultConfig['responseType'])) {
            $this->responseService->createRequestLog($request, $entity ?? null, $resultConfig['result'], $response, $resultConfig['object'] ?? null);
        }

        return $response;
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
    public function generateResult(Request $request, Entity $entity, array $requestBase, ?array $body = []): array
    {
        // Lets get our base stuff
        $result = $requestBase['result'];

        // Set default responseType
        $responseType = Response::HTTP_OK;

        // Get the application by searching for an application with a domain that matches the host of this request
        $host = $request->headers->get('host');
        // TODO: use a sql query instead of array_filter for finding the correct application
        //        $application = $this->em->getRepository('App:Application')->findByDomain($host);
        //        if (!empty($application)) {
        //            $this->session->set('application', $application);
        //        }
        $applications = $this->em->getRepository('App:Application')->findAll();
        $applications = array_values(array_filter($applications, function (Application $application) use ($host) {
            return in_array($host, $application->getDomains());
        }));
        if (count($applications) > 0) {
            $this->session->set('application', $applications[0]);
        } else {
            //            var_dump('no application found');
            if ($host == 'localhost') {
                $localhostApplication = new Application();
                $localhostApplication->setName('localhost');
                $localhostApplication->setDescription('localhost application');
                $localhostApplication->setDomains(['localhost']);
                $localhostApplication->setPublic('');
                $localhostApplication->setSecret('');
                $localhostApplication->setOrganization('localhostOrganization');
                $this->em->persist($localhostApplication);
                $this->em->flush();
                $this->session->set('application', $localhostApplication);
            //                var_dump('Created Localhost Application');
            } else {
                $this->session->set('application', null);
                $responseType = Response::HTTP_FORBIDDEN;
                $result = [
                    'message' => 'No application found with domain '.$host,
                    'type'    => 'Forbidden',
                    'path'    => $host,
                    'data'    => ['host' => $host],
                ];
            }
        }

        if (!$this->session->get('activeOrganization') && $this->session->get('application')) {
            $this->session->set('activeOrganization', $this->session->get('application')->getOrganization());
        }
        if (!$this->session->get('organizations') && $this->session->get('activeOrganization')) {
            $this->session->set('organizations', [$this->session->get('activeOrganization')]);
        }
        if (!$this->session->get('parentOrganizations')) {
            $this->session->set('parentOrganizations', []);
        }

        // Lets create an object
        if (($requestBase['id'] || $request->getMethod() == 'POST') && $responseType == Response::HTTP_OK) {
            $object = $this->getObject($requestBase['id'], $request->getMethod(), $entity);
            if (array_key_exists('type', $object) && $object['type'] == 'Bad Request') {
                $responseType = Response::HTTP_BAD_REQUEST;
                $result = $object;
                $object = null;
            } // Lets check if the user is allowed to view/edit this resource.
            elseif (!$this->objectEntityService->checkOwner($object)) {
                // TODO: do we want to throw a different error if there are nog organizations in the session? (because of logging out for example)
                if ($object->getOrganization() && !in_array($object->getOrganization(), $this->session->get('organizations') ?? [])) {
                    $object = null; // Needed so we return the error and not the object!
                    $responseType = Response::HTTP_FORBIDDEN;
                    $result = [
                        'message' => 'You are forbidden to view or edit this resource.',
                        'type'    => 'Forbidden',
                        'path'    => $entity->getName(),
                        'data'    => ['id' => $requestBase['id']],
                    ];
                }
            }
        }

        // Check for scopes, if forbidden to view/edit overwrite result so far to this forbidden error
        if ((!isset($object) || !$object->getUri()) || !$this->objectEntityService->checkOwner($object)) {
            try {
                //TODO what to do if we do a get collection and want to show objects this user is the owner of, but not any other objects?
                $this->authorizationService->checkAuthorization($this->authorizationService->getRequiredScopes($request->getMethod(), null, $entity));
            } catch (AccessDeniedException $e) {
                $responseType = Response::HTTP_FORBIDDEN;
                $result = [
                    'message' => $e->getMessage(),
                    'type'    => 'Forbidden',
                    'path'    => $entity->getName(),
                    'data'    => [],
                ];
            }
        }

        // Lets allow for filtering specific fields
        $fields = $this->getRequestFields($request);

        // Lets setup a switchy kinda thingy to handle the input (in handle functions)
        // Its a enity endpoint
        if ($requestBase['id'] && isset($object) && $object instanceof ObjectEntity) {
            // Lets handle all different type of endpoints
            $endpointResult = $this->handleEntityEndpoint($request, [
                'object' => $object ?? null, 'body' => $body ?? null, 'fields' => $fields, 'path' => $requestBase['path'],
            ]);
        }
        // its an collection endpoind
        elseif ($responseType == Response::HTTP_OK) {
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

        return [
            'result'       => $result,
            'responseType' => $responseType,
            'object'       => $object ?? null,
        ];
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
            'application/json'     => 'json',
            'application/ld+json'  => 'jsonld',
            'application/json+ld'  => 'jsonld',
            'application/hal+json' => 'jsonhal',
            'application/json+hal' => 'jsonhal',
            'application/xml'      => 'xml',
            'text/csv'             => 'csv',
            'text/yaml'            => 'yaml',
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
    public function getRequestFields(Request $request): ?array
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
    public function handleEntityEndpoint(Request $request, array $info): array
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
                $result = $this->handleMutation($info['object'], $info['body'], $info['fields'], $request);
                $responseType = Response::HTTP_OK;
                if (isset($result) && array_key_exists('type', $result) && $result['type'] == 'Forbidden') {
                    $responseType = Response::HTTP_FORBIDDEN;
                }
                break;
            case 'DELETE':
                $result = $this->handleDelete($info['object']);
                $responseType = Response::HTTP_NO_CONTENT;
                if (isset($result) && array_key_exists('type', $result) && $result['type'] == 'Forbidden') {
                    $responseType = Response::HTTP_FORBIDDEN;
                }
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
    public function handleCollectionEndpoint(Request $request, array $info): array
    {
        // its a collection endpoint
        switch ($request->getMethod()) {
            case 'GET':
                $result = $this->handleSearch($info['entity']->getName(), $request, $info['fields'], $info['extension']);
                $responseType = Response::HTTP_OK;
                break;
            case 'POST':
                // Transfer the variable to the service
                $result = $this->handleMutation($info['object'], $info['body'], $info['fields'], $request);
                $responseType = Response::HTTP_CREATED;
                if (isset($result) && array_key_exists('type', $result) && $result['type'] == 'Forbidden') {
                    $responseType = Response::HTTP_FORBIDDEN;
                }
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
    public function handleMutation(ObjectEntity $object, array $body, $fields, Request $request): array
    {
        // Check if session contains an activeOrganization, so we can't do calls without it. So we do not create objects with no organization!
        if ($this->parameterBag->get('app_auth') && empty($this->session->get('activeOrganization'))) {
            return [
                'message' => 'An active organization is required in the session, please login to create a new session.',
                'type'    => 'Forbidden',
                'path'    => $object->getEntity()->getName(),
                'data'    => ['activeOrganization' => null],
            ];
        }

        // Validation stap
        $this->validationService->setRequest($request);
//        if ($request->getMethod() == 'POST') {
//            var_dump($object->getEntity()->getName());
//        }
        $this->validationService->createdObjects = $request->getMethod() == 'POST' ? [$object] : [];
        $object = $this->validationService->validateEntity($object, $body);

        // Let see if we have errors
        if ($object->getHasErrors()) {
            $errorsResponse = $this->returnErrors($object);
            $this->handleDeleteOnError();

            return $errorsResponse;
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
            $errorsResponse = $this->returnErrors($object);
            $this->handleDeleteOnError();

            return $errorsResponse;
        }

        // Saving the data
        $this->em->persist($object);
        if ($request->getMethod() == 'POST' && $object->getEntity()->getFunction() === 'organization' && !array_key_exists('@organization', $body)) {
            $object = $this->functionService->createOrganization($object, $object->getUri(), $body['type']);
        }
        $this->em->persist($object);
        $this->em->flush();

        return $this->responseService->renderResult($object, $fields);
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
        return $this->responseService->renderResult($object, $fields);
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
        $entity = $this->em->getRepository('App:Entity')->findOneBy(['name' => $entityName]);
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
                    'data'    => ['queryParameter' => $param],
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
            $results[] = $this->responseService->renderResult($object, $fields, null, $flat);
        }

        // If we need a flattend responce we are al done
        if ($flat) {
            return $results;
        }

        // If not lets make it pritty
        $results = ['results' => $results];
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
    public function handleDelete(ObjectEntity $object, ArrayCollection $maxDepth = null): array
    {
        // TODO: check if we are allowed to delete this?!!! (this is a copy paste):
//        try {
//            if (!$this->objectEntityService->checkOwner($objectEntity) && !($attribute->getDefaultValue() && $value === $attribute->getDefaultValue())) {
//                $this->authorizationService->checkAuthorization($this->authorizationService->getRequiredScopes($objectEntity->getUri() ? 'PUT' : 'POST', $attribute));
//            }
//        } catch (AccessDeniedException $e) {
//            $objectEntity->addError($attribute->getName(), $e->getMessage());
//
//            return $objectEntity;
//        }

        // Check mayBeOrphaned
        // Get all attributes with mayBeOrphaned == false and one or more objects
        $cantBeOrphaned = $object->getEntity()->getAttributes()->filter(function (Attribute $attribute) use ($object) {
            if (!$attribute->getMayBeOrphaned() && count($object->getValueByAttribute($attribute)->getObjects()) > 0) {
                return true;
            }

            return false;
        });
        if (count($cantBeOrphaned) > 0) {
            $data = [];
            foreach ($cantBeOrphaned as $attribute) {
                $data[] = $attribute->getName();
//                $data[$attribute->getName()] = $object->getValueByAttribute($attribute)->getId();
            }

            return [
                'message' => 'You are not allowed to delete this object because of attributes that can not be orphaned.',
                'type'    => 'Forbidden',
                'path'    => $object->getEntity()->getName(),
                'data'    => ['cantBeOrphaned' => $data],
            ];
        }

        // Lets keep track of objects we already encountered, for inversedBy, checking maxDepth 1, preventing recursion loop:
        if (is_null($maxDepth)) {
            $maxDepth = new ArrayCollection();
        }
        $maxDepth->add($object);

        foreach ($object->getEntity()->getAttributes() as $attribute) {
            // If this object has subresources and cascade delete is set to true, delete the subresources as well.
            // TODO: use switch for type? ...also delete type file?
            if ($attribute->getType() == 'object' && $attribute->getCascadeDelete() && !is_null($object->getValueByAttribute($attribute)->getValue())) {
                if ($attribute->getMultiple()) {
                    // !is_null check above makes sure we do not try to loop through null
                    foreach ($object->getValueByAttribute($attribute)->getValue() as $subObject) {
                        if ($subObject && !$maxDepth->contains($subObject)) {
                            $this->handleDelete($subObject, $maxDepth);
                        }
                    }
                } else {
                    $subObject = $object->getValueByAttribute($attribute)->getValue();
                    if ($subObject && !$maxDepth->contains($subObject)) {
                        $this->handleDelete($subObject, $maxDepth);
                    }
                }
            }
        }
        if ($object->getEntity()->getGateway() && $object->getEntity()->getGateway()->getLocation() && $object->getEntity()->getEndpoint() && $object->getExternalId()) {
            if ($resource = $this->commonGroundService->isResource($object->getUri())) {
                $this->commonGroundService->deleteResource(null, $object->getUri()); // could use $resource instead?
            }
        }
        $this->validationService->notify($object, 'DELETE');

        $this->em->remove($object);
        $this->em->flush();

        return [];
    }

    /**
     * We need to do a clean up if there are errors, almost same as handleDelete, but without the cascade checks and notifications.
     *
     * @return void
     */
    public function handleDeleteOnError()
    {
        foreach (array_reverse($this->validationService->createdObjects) as $createdObject) {
            $this->handleDeleteObjectOnError($createdObject); // see to do in this function
        }
    }

    /**
     * @param ObjectEntity      $createdObject
     * @param ObjectEntity|null $motherObject
     *
     * @return void
     */
    private function handleDeleteObjectOnError(ObjectEntity $createdObject, ?ObjectEntity $motherObject = null)
    {
        //TODO: DO NOT TOUCH! This will only delete emails from the gateway when an error is thrown. should delete all created ObjectEntities...
//        var_dump($createdObject->getUri());
//        if ($createdObject->getEntity()->getGateway() && $createdObject->getEntity()->getGateway()->getLocation() && $createdObject->getEntity()->getEndpoint() && $createdObject->getExternalId()) {
//            try {
//                $resource = $this->commonGroundService->getResource($createdObject->getUri(), [], false);
//                var_dump('Delete extern object for: '.$createdObject->getEntity()->getName());
//                $this->commonGroundService->deleteResource(null, $createdObject->getUri()); // could use $resource instead?
//            } catch (\Throwable $e) {
//                $resource = null;
//            }
//        }
//        var_dump('Delete: '.$createdObject->getEntity()->getName());
//        var_dump('Values on this^ object '.count($createdObject->getObjectValues()));
        foreach ($createdObject->getObjectValues() as $value) {
            $this->deleteSubobjects($value, $motherObject);

            try {
                if ($createdObject->getEntity()->getName() == 'email') {
                    $this->em->remove($value);
                    $this->em->flush();
//                    var_dump($value->getAttribute()->getEntity()->getName().' -> '.$value->getAttribute()->getName());
                }
            } catch (Exception $exception) {
//                var_dump($exception->getMessage());
//                var_dump($value->getId()->toString());
//                var_dump($value->getValue());
//                var_dump($value->getAttribute()->getEntity()->getName().' -> '.$value->getAttribute()->getName().' GAAT MIS');
                continue;
            }
        }

        try {
            if ($createdObject->getEntity()->getName() == 'email') {
                $this->em->remove($createdObject);
                $this->em->flush();
//                var_dump('Deleted: '.$createdObject->getEntity()->getName());
            }
        } catch (Exception $exception) {
//            var_dump($createdObject->getEntity()->getName().' GAAT MIS');
        }
    }

    /**
     * @param Value             $value
     * @param ObjectEntity|null $motherObject
     *
     * @return void
     */
    private function deleteSubobjects(Value $value, ?ObjectEntity $motherObject = null)
    {
        foreach ($value->getObjects() as $object) {
            if ($object && (!$motherObject || $object->getId() !== $motherObject->getId())) {
                $this->handleDeleteObjectOnError($object, $value->getObjectEntity());
            }
        }
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
}
