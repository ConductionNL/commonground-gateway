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
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

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
    private CacheInterface $cache;
    private Stopwatch $stopwatch;

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
        FunctionService $functionService,
        CacheInterface $cache,
        Stopwatch $stopwatch
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
        $this->cache = $cache;
        $this->stopwatch = $stopwatch;
    }

    public function getValidationService(): ValidationService
    {
        return $this->validationService;
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
            if ($object instanceof ObjectEntity && $entity !== $object->getEntity()) {
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
            // if entity->function == 'organization', organization for this ObjectEntity will be changed later in handleMutation
            $this->session->get('activeOrganization') ? $object->setOrganization($this->session->get('activeOrganization')) : $object->setOrganization('http://testdata-organization');
            $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            $object->setApplication(!empty($application) ? $application : null);

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
        $this->cache->invalidateTags(['grantedScopes']);

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
            $body = filter_var_array($body, FILTER_SANITIZE_ENCODED);
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
        //            $this->session->set('application', $application->getId()->toString());
        //        }
        $applications = $this->em->getRepository('App:Application')->findAll();
        $applications = array_values(array_filter($applications, function (Application $application) use ($host) {
            return in_array($host, $application->getDomains());
        }));
        if (count($applications) > 0) {
            $this->session->set('application', $applications[0]->getId()->toString());
        } elseif ($this->session->get('apiKeyApplication')) {
            // If an api-key is used for authentication we already know which application is used
            $this->session->set('application', $this->session->get('apiKeyApplication'));
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
                $this->session->set('application', $localhostApplication->getId()->toString());
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
            $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            $this->session->set('activeOrganization', !empty($application) ? $application->getOrganization() : null);
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
                $this->authorizationService->checkAuthorization([
                    'method' => $request->getMethod(),
                    'entity' => $entity,
                    'object' => $object ?? null,
                ]);
            } catch (AccessDeniedException $e) {
                $result = [
                    'message' => $e->getMessage(),
                    'type'    => 'Forbidden',
                    'path'    => $entity->getName(),
                    'data'    => [],
                ];

                return [
                    'result'       => $result,
                    'responseType' => Response::HTTP_FORBIDDEN,
                    'object'       => $object ?? null,
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
        $fields = $request->query->has('fields') ? $request->query->get('fields') : $request->query->get('_fields');

        if ($fields) {
            // Lets deal with a comma seperated list
            if (!is_array($fields)) {
                $fields = explode(',', $fields);
            }

            $dot = new Dot();
            // Lets turn the from dor attat into an propper array
            foreach ($fields as $key => $value) {
                $dot->add($value, true);
            }

            $fields = $dot->all();
        }

        return $fields;
    }

    /**
     * Gets extend from the request to use for extending.
     *
     * @param Request $request
     *
     * @return array
     */
    public function getRequestExtend(Request $request): ?array
    {
        $extend = $request->query->has('extend') ? $request->query->get('extend') : $request->query->get('_extend');

        if ($extend) {
            // Lets deal with a comma seperated list
            if (!is_array($extend)) {
                $extend = explode(',', $extend);
            }

            $dot = new Dot();
            // Lets turn the from dor attat into an propper array
            foreach ($extend as $key => $value) {
                $dot->add($value, true);
            }

            $extend = $dot->all();
        }

        return $extend;
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
                $result = $this->handleGet($info['object'], $info['fields'], null);
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
                $result = $this->handleSearch($info['entity'], $request, $info['fields'], null, $info['extension']);
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

        // Check if @owner is present in the body and if so unset it.
        // note: $owner is allowed to be null!
        $owner = 'owner';
        if (array_key_exists('@owner', $body)) {
            $owner = $body['@owner'];
            unset($body['@owner']);
        }

        // Validation stap
        $this->validationService->setRequest($request);
        $this->validationService->createdObjects = $request->getMethod() == 'POST' ? [$object] : [];
        $this->validationService->removeObjectsNotMultiple = []; // to be sure
        $this->validationService->notifications = []; // to be sure
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

        // Remove relations for inversedBy objects that are not multiple (example-> POST organization.postalCodes: ["postalCodeUuid"] when the used postalCode already has a postalCode.organization connected, we are disconnecting the old connection here)
        foreach ($this->validationService->removeObjectsNotMultiple as $removeObjectNotMultiple) {
            $removeObjectNotMultiple['object']->removeSubresourceOf($removeObjectNotMultiple['valueObject']);
        }
        $this->em->flush();

        // Check if we need to remove relations and/or objects for multiple objects arrays during a PUT (example-> emails: [])
        if ($request->getMethod() == 'PUT') {
            foreach ($this->validationService->removeObjectsOnPut as $removeObjectOnPut) {
                $removeObjectOnPut['object']->removeSubresourceOf($removeObjectOnPut['valueObject']);
                // If the object has no other 'parent' connections, if the attribute of the value must be unique...
                // Example: Entity "Organization" has Attribute "organization_postalCodes" (array of postalCodes objects) that mustBeUnique
                if (count($removeObjectOnPut['object']->getSubresourceOf()) == 0 && $removeObjectOnPut['valueObject']->getAttribute()->getMustBeUnique()) {
                    // ...and if the object of the attribute has a value that must be unique
                    // Example: Entity "postalCode" has Attribute "code" (integer) that mustBeUnique
                    foreach ($removeObjectOnPut['valueObject']->getAttribute()->getObject()->getAttributes() as $attribute) {
                        if ($attribute->getMustBeUnique()) {
                            // delete it entirely. This is because mustBeUnique checks will trigger if these objects keep existing. And if they have no connection to anything, they shouldn't
                            $this->handleDelete($removeObjectOnPut['object']); // Do make sure to check for mayBeOrphaned and cascadeDelete though
                            break;
                        }
                    }
                }
            }
            $this->em->flush();
        }

        // Saving the data
        $this->em->persist($object);
        if ($request->getMethod() == 'POST' && $object->getEntity()->getFunction() === 'organization' && !array_key_exists('@organization', $body)) {
            $object = $this->functionService->createOrganization($object, $object->getUri(), $body['type']);
        }
        $this->objectEntityService->handleOwner($object, $owner); // note: $owner is allowed to be null!
        $this->em->persist($object);
        $this->em->flush();

        // Send notifications
        foreach ($this->validationService->notifications as $notification) {
            $this->validationService->notify($notification['objectEntity'], $notification['method']);
        }

        return $this->responseService->renderResult($object, $fields, null);
    }

    /**
     * Handles a get item api call.
     *
     * @param ObjectEntity $object
     * @param array|null   $fields
     * @param array|null   $extend
     * @param string       $acceptType
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     */
    public function handleGet(ObjectEntity $object, ?array $fields, ?array $extend, string $acceptType = 'json'): array
    {
        return $this->responseService->renderResult($object, $fields, $extend, $acceptType);
    }

    /**
     * A function to replace Request->query->all() because Request->query->all() will replace some characters with an underscore.
     * This function will not.
     *
     * @param string $method The method of the Request
     *
     * @return array An array with all query parameters.
     */
    public function realRequestQueryAll(string $method = 'get'): array
    {
        $vars = [];
        if (strtolower($method) === 'get' && empty($_SERVER['QUERY_STRING'])) {
            return $vars;
        }
        $pairs = explode('&', strtolower($method) == 'post' ? file_get_contents('php://input') : $_SERVER['QUERY_STRING']);
        foreach ($pairs as $pair) {
            $nv = explode('=', $pair);
            $name = urldecode($nv[0]);
            $value = '';
            if (count($nv) == 2) {
                $value = urldecode($nv[1]);
            }
            $matchesCount = preg_match('/(\[.*])/', $name, $matches);
            if ($matchesCount == 1) {
                $key = $matches[1];
                $name = str_replace($key, '', $name);
                $key = trim($key, '[]');
                if (!empty($key)) {
                    $vars[$name][$key] = $value;
                } else {
                    $vars[$name][] = $value;
                }
                continue;
            }
            $vars[$name] = $value;
        }

        return $vars;
    }

    /**
     * Handles a search (collection) api call.
     *
     * @param Entity     $entity
     * @param Request    $request
     * @param array|null $fields
     * @param array|null $extend
     * @param $extension
     * @param null   $filters
     * @param string $acceptType
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     *
     * @return array|array[]
     */
    public function handleSearch(Entity $entity, Request $request, ?array $fields, ?array $extend, $extension, $filters = null, string $acceptType = 'json', ?array $query = null): array
    {
        $query = $query ?? $this->realRequestQueryAll($request->getMethod());
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

        // Allowed order by
        $this->stopwatch->start('orderParametersCheck', 'handleSearch');
        $orderCheck = $this->em->getRepository('App:ObjectEntity')->getOrderParameters($entity);
        // todo: ^^^ add something to ObjectEntities just like bool searchable, use that to check for fields allowed to be used for ordering.
        // todo: sortable?

        $order = [];
        if (array_key_exists('order', $query)) {
            $order = $query['order'];
            unset($query['order']);
            if (!is_array($order)) {
                $orderCheckStr = implode(', ', $orderCheck);
                $message = 'Please give an attribute to order on. Like this: ?order[attributeName]=desc/asc. Supported order query parameters: '.$orderCheckStr;
            }
            if (is_array($order) && count($order) > 1) {
                $message = 'Only one order query param at the time is allowed.';
            }
            if (is_array($order) && !in_array(strtoupper(array_values($order)[0]), ['DESC', 'ASC'])) {
                $message = 'Please use desc or asc as value for your order query param, not: '.array_values($order)[0];
            }
            if (is_array($order) && !in_array(array_keys($order)[0], $orderCheck)) {
                $orderCheckStr = implode(', ', $orderCheck);
                $message = 'Unsupported order query parameters ('.array_keys($order)[0].'). Supported order query parameters: '.$orderCheckStr;
            }
            if (isset($message)) {
                return [
                    'message' => $message,
                    'type'    => 'error',
                    'path'    => is_array($order) ? $entity->getName().'?order['.array_keys($order)[0].']='.array_values($order)[0] : $entity->getName().'?order='.$order,
                    'data'    => ['order' => $order],
                ];
            }
        }
        $this->stopwatch->stop('orderParametersCheck');

        // Allowed filters
        $this->stopwatch->start('filterParametersCheck', 'handleSearch');
        $filterCheck = $this->em->getRepository('App:ObjectEntity')->getFilterParameters($entity);

        // Lets add generic filters
        $filterCheck = array_merge($filterCheck, ['fields', '_fields', 'extend', '_extend']);
        if (!empty($entity->getSearchPartial())) {
            $filterCheck = array_merge($filterCheck, ['search', '_search']);
        }

        foreach ($query as $param => $value) {
            if (!in_array($param, $filterCheck)) {
                $filterCheckStr = implode(', ', $filterCheck);

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

        if ($filters) {
            $query = array_merge($query, $filters);
        }
        $this->stopwatch->stop('filterParametersCheck');

        $this->stopwatch->start('valueScopesToFilters', 'handleSearch');
        $query = array_merge($query, $this->authorizationService->valueScopesToFilters($entity));
        $this->stopwatch->stop('valueScopesToFilters');

        $this->stopwatch->start('findAndCountByEntity', 'handleSearch');
        $repositoryResult = $this->em->getRepository('App:ObjectEntity')->findAndCountByEntity($entity, $query, $order, $offset, $limit);
        $this->stopwatch->stop('findAndCountByEntity');

        // Lets see if we need to flatten te responce (for example csv use)
        // todo: $flat and $acceptType = 'json' should have the same result, so remove $flat?
        $flat = false;
        if (in_array($request->headers->get('accept'), ['text/csv']) || in_array($extension, ['csv'])) {
            $flat = true;
        }

        $results = [];
        $this->stopwatch->start('renderResults', 'handleSearch');
        foreach ($repositoryResult['objects'] as $object) {
            // If orderBy is used on an attribute we needed to add the value of that attribute to the select of the query...
            // In this^ case $object will be an array containing the object and this specific value we are ordering on.
            if (is_array($object)) {
                $object = $object[0];
                // $object['stringValue'] contains the value we are ordering on.
            }
            $results[] = $this->responseService->renderResult($object, $fields, $extend, $acceptType, false, $flat);
            $this->stopwatch->lap('renderResults');
        }
        $this->stopwatch->stop('renderResults');

        // If we need a flattend responce we are al done
        // todo: $flat and $acceptType = 'json' should have the same result, so remove $flat?
        if ($flat) {
            return $results;
        }

        // If not lets make it pretty
        return $this->handlePagination($acceptType, $entity, $results, $repositoryResult['total'], $limit, $offset);
    }

    /**
     * Returns a response array including pagination for handleSearch function. This response is different depending on the acceptType.
     *
     * @param string $acceptType
     * @param Entity $entity
     * @param array  $results
     * @param int    $total
     * @param int    $limit
     * @param int    $offset
     *
     * @return array[]
     */
    private function handlePagination(string $acceptType, Entity $entity, array $results, int $total, int $limit, int $offset): array
    {
        $pages = ceil($total / $limit);
        $pages = $pages == 0 ? 1 : $pages;
        $page = floor($offset / $limit) + 1;

        switch ($acceptType) {
            case 'jsonhal':
                $paginationResult = $this->handleJsonHal($entity, [
                    'results' => $results, 'limit' => $limit, 'total' => $total,
                    'offset'  => $offset, 'page' => $page, 'pages' => $pages,
                ]);
                break;
            case 'jsonld':
                // todo: try and match api-platform ? https://api-platform.com/docs/core/pagination/
            case 'json':
            default:
                $paginationResult = ['results' => $results];
                $paginationResult = $this->handleDefaultPagination($paginationResult, [
                    'results' => $results, 'limit' => $limit, 'total' => $total,
                    'offset'  => $offset, 'page' => $page, 'pages' => $pages,
                ]);
                break;
        }

        return $paginationResult;
    }

    private function handleJsonHal(Entity $entity, array $data): array
    {
        $path = $entity->getName();
        if ($this->session->get('endpoint')) {
            $endpoint = $this->em->getRepository('App:Endpoint')->findOneBy(['id' => $this->session->get('endpoint')]);
            $path = implode('/', $endpoint->getPath());
        }
        $paginationResult['_links'] = [
            'self'  => ['href' => '/api/'.$path.($data['page'] == 1 ? '' : '?page='.$data['page'])],
            'first' => ['href' => '/api/'.$path],
        ];
        if ($data['page'] > 1) {
            $paginationResult['_links']['prev']['href'] = '/api/'.$path.($data['page'] == 2 ? '' : '?page='.($data['page'] - 1));
        }
        if ($data['page'] < $data['pages']) {
            $paginationResult['_links']['next']['href'] = '/api/'.$path.'?page='.($data['page'] + 1);
        }
        $paginationResult['_links']['last']['href'] = '/api/'.$path.($data['pages'] == 1 ? '' : '?page='.$data['pages']);
        $paginationResult = $this->handleDefaultPagination($paginationResult, $data);
        $paginationResult['_embedded'] = [$path => $data['results']]; //todo replace $path with $entity->getName() ?

        return $paginationResult;
    }

    private function handleDefaultPagination(array $paginationResult, array $data): array
    {
        $paginationResult['count'] = count($data['results']);
        $paginationResult['limit'] = $data['limit'];
        $paginationResult['total'] = $data['total'];
        $paginationResult['start'] = $data['offset'] + 1;
        $paginationResult['page'] = $data['page'];
        $paginationResult['pages'] = $data['pages'];

        return $paginationResult;
    }

    /**
     * Handles a delete api call.
     *
     * @param ObjectEntity         $object
     * @param ArrayCollection|null $maxDepth
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function handleDelete(ObjectEntity $object, ArrayCollection $maxDepth = null): array
    {
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

        // Lets remove unread objects before we delete this object
        $unreads = $this->em->getRepository('App:Unread')->findBy(['object' => $object]);
        foreach ($unreads as $unread) {
            $this->em->remove($unread);
        }

        // Remove this object from cache
        $this->functionService->removeResultFromCache($object);

        $this->em->remove($object);
        $this->em->flush();

        // Send a notification
        $this->validationService->notify($object, 'DELETE');

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
    private function handleDeleteObjectOnError(ObjectEntity $createdObject)
    {
        $this->em->clear();
        //TODO: test and make sure extern objects are not created after an error, and if they are, maybe add this;
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
            if ($value->getAttribute()->getType() == 'object') {
                foreach ($value->getObjects() as $object) {
                    $object->removeSubresourceOf($value);
                }
            }

            try {
                $this->em->remove($value);
                $this->em->flush();
                //                var_dump($value->getAttribute()->getEntity()->getName().' -> '.$value->getAttribute()->getName());
            } catch (Exception $exception) {
                //                var_dump($exception->getMessage());
                //                var_dump($value->getId()->toString());
                //                var_dump($value->getValue());
                //                var_dump($value->getAttribute()->getEntity()->getName().' -> '.$value->getAttribute()->getName().' GAAT MIS');
                continue;
            }
        }

        try {
            $this->em->remove($createdObject);
            $this->em->flush();
            //            var_dump('Deleted: '.$createdObject->getEntity()->getName());
        } catch (Exception $exception) {
            //            var_dump($createdObject->getEntity()->getName().' GAAT MIS');
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
