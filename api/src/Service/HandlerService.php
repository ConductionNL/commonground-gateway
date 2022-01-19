<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\File;
use App\Entity\Handler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment as Environment;

class HandlerService
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private ValidationService $validationService;
    private TranslationService $translationService;
    private SOAPService $soapService;
    private EavService $eavService;
    private SerializerInterface $serializerInterface;
    private LogService $logService;
    private TemplateService $templateService;
    private ObjectEntityService $objectEntityService;

    // This list is used to map content-types to extentions, these are then used for serializations and downloads
    // based on https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
    public $acceptHeaderToSerialiazation = [
        'application/json'                                                                   => 'json',
        'application/ld+json'                                                                => 'jsonld',
        'application/json+ld'                                                                => 'jsonld',
        'application/hal+json'                                                               => 'jsonhal',
        'application/json+hal'                                                               => 'jsonhal',
        'application/xml'                                                                    => 'xml',
        'text/csv'                                                                           => 'csv',
        'text/yaml'                                                                          => 'yaml',
        'text/html'                                                                          => 'html',
        'application/pdf'                                                                    => 'pdf',
        'application/msword'                                                                 => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'            => 'docx',
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        ValidationService $validationService,
        TranslationService $translationService,
        SOAPService $soapService,
        EavService $eavService,
        SerializerInterface $serializer,
        LogService $logService,
        Environment $twig,
        TemplateService $templateService,
        ObjectEntityService $objectEntityService
    ) {
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();
        $this->validationService = $validationService;
        $this->translationService = $translationService;
        $this->soapService = $soapService;
        $this->eavService = $eavService;
        $this->serializer = $serializer;
        $this->logService = $logService;
        $this->templating = $twig;
        $this->templateService = $templateService;
        $this->objectEntityService = $objectEntityService;
    }

    /**
     * This function sets the endpoint in the session and executes handleHandler with its found Handler.
     */
    public function handleEndpoint(Endpoint $endpoint): Response
    {
        $session = new Session();
        $session->set('endpoint', $endpoint);

        // @todo creat logicdata, generalvaribales uit de translationservice

        foreach ($endpoint->getHandlers() as $handler) {
            // Check the JSON logic (voorbeeld van json logic in de validatie service)
            /* @todo acctualy check for json logic */

            if (true) {
                $session->set('handler', $handler);

                return $this->handleHandler($handler);
            }
        }

        $response = new Response(
            $this->serializer->serialize(['message' => 'No handler found for endpoint: ' . $endpoint->getName(), 'data' => $endpoint->getId()], $this->getRequestContentType()),
            Response::HTTP_NOT_FOUND,
            ['content-type' => 'json']
        );
        $this->logService->saveLog($this->request, $response);
        return $response->prepare($this->request);
    }

    /**
     * This function walks through the $handler with $data from the request to perform mapping, translating and fetching/saving from/to the eav.
     * 
     * @todo remove old eav code if new way is finished and working
     * @todo better check if $data is a document/template line 199
     */
    public function handleHandler(Handler $handler): Response
    {
        $method = $this->request->getMethod();

        // Only do mapping and translation -in for calls with body
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {

            // To start it al off we need the data from the incomming request
            $data = $this->getDataFromRequest($this->request);

            if ($data == null || empty($data)) {
                $response = new Response(
                    $this->serializer->serialize(['message' => 'No request body given for ' . $method . ' or faulty body given', 'path' => 'Request body'],  $this->getRequestContentType()),
                    Response::HTTP_NOT_FOUND,
                    ['content-type' => 'json']
                );
                $this->logService->saveLog($this->request, $response);
                return $response->prepare($this->request);
            };

            // Update current Log
            $this->logService->saveLog($this->request, null, json_encode($data));

            // Then we want to do the mapping in the incomming request
            $skeleton = $handler->getSkeletonIn();
            if (!$skeleton || empty($skeleton)) {
                $skeleton = $data;
            }
            $data = $this->translationService->dotHydrator($skeleton, $data, $handler->getMappingIn());

            // Update current Log
            $this->logService->saveLog($this->request, null, json_encode($data));

            // The we want to do translations on the incomming request
            $transRepo = $this->entityManager->getRepository('App:Translation');
            $translations = $transRepo->getTranslations($handler->getTranslationsIn());
            $data = $this->translationService->parse($data, true, $translations);

            // Update current Log
            $this->logService->saveLog($this->request, null, json_encode($data));
        }

        // eav new way
        // $handler->getEntity() !== null && $data = $this->objectEntityService->handleObject($handler, $data ?? null, $method);

        // If data contains error dont execute following code and create response
        if (!(isset($data['type']) && isset($data['message']))) {
            
            // Update current Log
            $this->logService->saveLog($this->request, null, json_encode($data));

            // The we want to do  translations on the outgoing responce
            $transRepo = $this->entityManager->getRepository('App:Translation');
            $translations = $transRepo->getTranslations($handler->getTranslationsOut());

            if (isset($data['result'])) {
                $data['result'] = $this->translationService->parse($data['result'], true, $translations);
            } else {
                $data = $this->translationService->parse($data, true, $translations);
            }

            // Update current Log
            $this->logService->saveLog($this->request, null, json_encode($data));

            // Then we want to do to mapping on the outgoing responce
            $skeleton = $handler->getSkeletonOut();
            if (!$skeleton || empty($skeleton)) {
                isset($data['result']) ? $skeleton = $data['result'] : $skeleton = $data;
            }
            if (isset($data['result'])) {
                $data['result'] = $this->translationService->dotHydrator($skeleton, $data['result'], $handler->getMappingOut());
            } elseif (isset($data)) {
                $data = $this->translationService->dotHydrator($skeleton, $data, $handler->getMappingOut());
            }

            // Update current Log
            $this->logService->saveLog($this->request, null, json_encode($data));

            // Lets see if we need te use a template
            if ($handler->getTemplatetype() && $handler->getTemplate()) {
                $data = $this->renderTemplate($handler, $data);
            }

            // @todo should be done better
            // If data is string it could be a document/template
            if (is_string($data)) {
                $result = $data;
                $data = [];
                $data['result'] = $result;
            }
        }
        // Update current Log
        $this->logService->saveLog($this->request, null, json_encode($data));

        // An lastly we want to create a response
        $response = $this->createResponse($data);

        // Final update Log
        $this->logService->saveLog($this->request, $response, null, true);

        return $response;
    }

    /**
     * Checks content type and decodes that if needed
     * 
     * @return array|null
     * 
     * @todo more content types ?
     * @todo check for specific error when decoding
     * @todo support xml messages (xml is already decoded??)
     */
    public function getDataFromRequest()
    {
        $content = $this->request->getContent();
        $contentType = $this->getRequestContentType();
        switch ($contentType) {
            case 'json':
                return json_decode($this->request->getContent(), true);
                // @todo support xml messages (xml in $content looks already decoded?)
            case 'xml':
                throw new \Exception('XML is not yet supported');
                // return simplexml_load_string($content);
            default:
                throw new \Exception('Unsupported content type');
        }
    }

    /**
     * This function creates and prepares the response
     * 
     * @todo throw error if $data is not string when creating pdf
     */
    public function createResponse(array $data): Response
    {

        // We only end up here if there are no errors, so we only suply best case senario's
        switch ($this->request->getMethod()) {
            case 'GET':
                $status = Response::HTTP_OK;
                break;
            case 'POST':
                $status = Response::HTTP_CREATED;
                break;
            case 'PUT':
                $status = Response::HTTP_ACCEPTED;
                break;
            case 'UPDATE':
                $status = Response::HTTP_ACCEPTED;
                break;
            case 'DELETE':
                $status = Response::HTTP_NO_CONTENT;
                break;
            default:
                $status = Response::HTTP_OK;
        }

        $contentType = $this->getRequestContentType();

        // Lets fill in some options
        $options = [];
        switch ($contentType) {
            case 'text/csv':
                $options = [
                    CsvEncoder::ENCLOSURE_KEY   => '"',
                    CsvEncoder::ESCAPE_CHAR_KEY => '+',
                ];
                break;
            case 'pdf':
                //create template
                if (!is_string($data['result']) || (!isset($data['result']) && !is_string($data))) {
                    // throw error
                    throw new \Exception("PDF couldn't be created");
                }
                $document = new Document();
                $document->setDocumentType($contentType);
                $document->setType('twig');
                isset($data['result']) ? $document->setContent($data['result']) : $document->setContent($data);
                $result = $this->templateService->renderPdf($document);
                break;
        }

        // Lets seriliaze the shizle (if no document)
        !isset($document) && (isset($data['result']) ? $result = $this->serializer->serialize($data['result'], $contentType, $options)
            : $result = $this->serializer->serialize($data, $contentType, $options));

        // Lets create the actual response
        $response = new Response(
            $result,
            $status,
            ['content-type' => $this->acceptHeaderToSerialiazation[array_search($contentType, $this->acceptHeaderToSerialiazation)]]
        );

        // Lets handle file responses
        $routeParameters = $this->request->attributes->get('_route_params');
        if (array_key_exists('extension', $routeParameters) && $extension = $routeParameters['extension']) {
            $date = new \DateTime();
            $date = $date->format('Ymd_His');
            $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$routeParameters['route']}_{$date}.{$contentType}");
            $response->headers->set('Content-Disposition', $disposition);
        }

        $response->prepare($this->request);

        return $response;
    }

    /**
     * Validates content type from request
     * 
     * @todo throw error if invalid extension
     * @todo throw error if unsupported content type
     */
    private function getRequestContentType(): string
    {
        // Lets grap the route parameters
        $routeParameters = $this->request->attributes->get('_route_params');

        // If we have an extension and the extension is a valid serialization format we will use that
        if (array_key_exists('extension', $routeParameters)) {
            if (in_array($routeParameters['extension'], $this->acceptHeaderToSerialiazation)) {
                return $routeParameters['extension'];
            } else {
                throw new \Exception("invalid extension requested");
            }
        }

        // Lets pick the first accaptable content type that we support
        // @todo where is request->acceptablecontenttypes being set?
        foreach ($this->request->getAcceptableContentTypes() as $contentType) {
            if (array_key_exists($contentType, $this->acceptHeaderToSerialiazation)) {
                return $this->acceptHeaderToSerialiazation[$contentType];
            }
        }

        // If we end up here we are dealing with an unsupported content type
        throw new \Exception("Unsupported content type");
    }

    /**
     * Checks template type on handler and creates template
     * 
     * @todo Add global variables
     */
    private function renderTemplate(Handler $handler, array $data): string
    {
        /* @todo add global variables */
        $variables = $data;

        // We only end up here if there are no errors, so we only suply best case senario's
        switch (strtoupper($handler->getTemplateType())) {
            case 'TWIG':
                $document = $this->templating->createTemplate($handler->getTemplate());

                return $document->render($variables);
                break;
            case 'MD':
                return $handler->getTemplate();
                break;
            case 'RST':
                return $handler->getTemplate();
                break;
            case 'HTML':
                return $handler->getTemplate();
                break;
            default:
                throw new \Exception("Unsupported template type");
        }
    }
}
