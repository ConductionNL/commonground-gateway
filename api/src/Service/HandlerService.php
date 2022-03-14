<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Endpoint;
use App\Entity\Handler;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

class HandlerService
{
    private EntityManagerInterface $entityManager;
    private TranslationService $translationService;
    private LogService $logService;
    private TemplateService $templateService;
    private ObjectEntityService $objectEntityService;
    private FormIOService $formIOService;
    private SubscriberService $subscriberService;

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
        'application/form.io'                                                                => 'form.io',
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
        ObjectEntityService $objectEntityService,
        FormIOService $formIOService,
        SubscriberService $subscriberService
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
        $this->objectEntityService = $objectEntityService->addServices($validationService, $eavService); // todo: temp fix untill we no longer need these services here
        $this->formIOService = $formIOService;
        $this->subscriberService = $subscriberService;
    }

    /**
     * This function sets the endpoint in the session and executes handleHandler with its found Handler.
     */
    public function handleEndpoint(Endpoint $endpoint): Response
    {
        $session = new Session();
        $session->set('endpoint', $endpoint->getId()->toString());

        // @todo creat logicdata, generalvaribales uit de translationservice

        foreach ($endpoint->getHandlers() as $handler) {
            // Check the JSON logic (voorbeeld van json logic in de validatie service)
            /* @todo acctualy check for json logic */

            if (true) {
                $session->set('handler', $handler->getId());

                return $this->handleHandler($handler, $endpoint);
            }
        }

        return $this->handleHandler(null, $endpoint);

        throw new GatewayException('No handler found for endpoint: '.$endpoint->getName(), null, null, ['data' => ['id' => $endpoint->getId()], 'path' => null, 'responseType' => Response::HTTP_NOT_FOUND]);
    }

    /**
     * This function walks through the $handler with $data from the request to perform mapping, translating and fetching/saving from/to the eav.
     *
     * @todo remove old eav code if new way is finished and working
     * @todo better check if $data is a document/template line 199
     */
    public function handleHandler(Handler $handler = null, Endpoint $endpoint): Response
    {
        $method = $this->request->getMethod();

        // Form.io components array
        if ($method === 'GET' && $this->getRequestType('accept') === 'form.io' && $handler->getEntity() && $handler->getEntity()->getAttributes()) {
            return new Response(
                $this->serializer->serialize($this->formIOService->createFormIOArray($handler->getEntity()), 'json'),
                Response::HTTP_OK,
                ['content-type' => 'json']
            );
        }

        // To start it al off we need the data from the incomming request
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $data = $this->getDataFromRequest($this->request);

            if ($data == null || empty($data)) {
                throw new GatewayException('Faulty body or no body given', null, null, ['data' => null, 'path' => 'Request body', 'responseType' => Response::HTTP_NOT_FOUND]);
            }
        }

        // Update current Log
        isset($data) ? $this->logService->saveLog($this->request, null, json_encode($data)) : $this->logService->saveLog($this->request, null, null);

        // Only do mapping and translation -in for calls with body
        in_array($method, ['POST', 'PUT', 'PATCH']) && $handler && $data = $this->handleDataBeforeEAV($data, $handler);

        // eav new way
        $handler->getEntity() !== null && $data = $this->objectEntityService->handleObject($handler, $data ?? null, $method);

        // @todo remove this when eav part works and catch this->objectEntityService->handleObject instead
        if (!isset($data)) {
            throw new GatewayException('Could not fetch object(s) on endpoint: /'.$endpoint->getPath(), null, null, ['data' => null, 'path' => null, 'responseType' => Response::HTTP_NOT_FOUND]);
        }

        // If data contains error dont execute following code and create response
        if (!(isset($data['type']) && isset($data['message']))) {

            // Check if we need to trigger subscribers for this entity
            $this->subscriberService->handleSubscribers($handler->getEntity(), $data, $method);

            // Update current Log
            $this->logService->saveLog($this->request, null, json_encode($data));

            $handler && $data = $this->handleDataAfterEAV($data, $handler);
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
     * Checks content type and decodes that if needed.
     *
     * @return array|null
     *
     * @todo more content types ?
     * @todo check for specific error when decoding
     */
    public function getDataFromRequest()
    {
        $content = $this->request->getContent();
        $contentType = $this->getRequestType('content-type');
        switch ($contentType) {
            case 'json':
            case 'jsonhal':
            case 'jsonld':
                return json_decode($content, true);
            case 'xml':
                // otherwise xml will throw its own error bypassing our exception handling
                libxml_use_internal_errors(true);
                // string to xml object, encode that to json then decode to array
                $xml = simplexml_load_string($content);
                // if xml is false get errors and throw exception
                if ($xml === false) {
                    $errors = 'Something went wrong decoding xml:';
                    foreach (libxml_get_errors() as $e) {
                        $errors .= ' '.$e->message;
                    }

                    throw new GatewayException($errors, null, null, ['data' => $content, 'path' => 'Request body', 'responseType' => Response::HTTP_UNPROCESSABLE_ENTITY]);
                }

                return json_decode(json_encode($xml), true);
            default:
                throw new GatewayException('Unsupported content type', null, null, ['data' => $content, 'path' => null, 'responseType' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE]);
        }
    }

    /**
     * This function creates and prepares the response.
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

        $acceptType = $this->getRequestType('accept');

        // Lets fill in some options
        $options = [];
        switch ($acceptType) {
            case 'text/csv':
                // @todo do something with options?
                $options = [
                    CsvEncoder::ENCLOSURE_KEY   => '"',
                    CsvEncoder::ESCAPE_CHAR_KEY => '+',
                ];
                $data = $this->serializer->encode($data, 'csv');

                break;
            case 'pdf':
                $document = new Document();
                // @todo find better name for document
                $document->setName('pdf');
                $document->setDocumentType($acceptType);
                $document->setType('pdf');
                // If data is not a template json_encode it
                if (isset($data) && !is_string($data)) {
                    $data = json_encode($data);
                }
                $document->setContent($data);
                $result = $this->templateService->renderPdf($document);
                break;
        }

        // Lets seriliaze the shizle (if no document and we have a result)
        try {
            !isset($document) && $result = $this->serializer->serialize($data, $acceptType, $options);
        } catch (NotEncodableValueException $e) {
            !isset($document) && $result = $this->serializer->serialize($data, 'json', $options);
            // throw new GatewayException($e->getMessage(), null, null, ['data' => null, 'path' => null, 'responseType' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE]);
        }

        // Lets create the actual response
        $response = new Response(
            $result,
            $status,
            ['content-type' => $this->acceptHeaderToSerialiazation[array_search($acceptType, $this->acceptHeaderToSerialiazation)]]
        );

        // Lets handle file responses
        $routeParameters = $this->request->attributes->get('_route_params');
        if (array_key_exists('extension', $routeParameters) && $extension = $routeParameters['extension']) {
            $date = new \DateTime();
            $date = $date->format('Ymd_His');
            $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$routeParameters['route']}_{$date}.{$acceptType}");
            $response->headers->set('Content-Disposition', $disposition);
        }

        $response->prepare($this->request);

        return $response;
    }

    /**
     * Validates content or accept type from request.
     *
     * @param string $type 'content-type' or 'accept'
     *
     * @return string Accept or content-type
     */
    public function getRequestType(string $type): string
    {
        // Lets grap the route parameters
        $routeParameters = $this->request->attributes->get('_route_params');

        // If we have an extension and the extension is a valid serialization format we will use that
        if ($type == 'content-type' && array_key_exists('extension', $routeParameters)) {
            if (in_array($routeParameters['extension'], $this->acceptHeaderToSerialiazation)) {
                return $routeParameters['extension'];
            } else {
                throw new GatewayException('invalid extension requested', null, null, ['data' => $routeParameters['extension'], 'path' => null, 'responseType' => Response::HTTP_BAD_REQUEST]);
            }
        }

        // Lets pick the first accaptable content type that we support
        $typeValue = $this->request->headers->get($type);
        (!isset($typeValue) || $typeValue === '*/*' || empty($typeValue)) && $typeValue = 'application/json';

        if (array_key_exists($typeValue, $this->acceptHeaderToSerialiazation)) {
            return $this->acceptHeaderToSerialiazation[$typeValue];
        }

        // If we end up here we are dealing with an unsupported content type
        throw new GatewayException('Unsupported content type', null, null, ['data' => $this->request->getAcceptableContentTypes(), 'path' => null, 'responseType' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE]);
    }

    /**
     * Checks template type on handler and creates template.
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
                throw new GatewayException('Unsupported template type', null, null, ['data' => $this->request->getAcceptableContentTypes(), 'path' => null, 'responseType' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE]);
        }
    }

    private function handleDataBeforeEAV(array $data, Handler $handler): array
    {
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

        return $data;
    }

    private function handleDataAfterEAV(array $data, Handler $handler): array
    {

        // The we want to do  translations on the outgoing response
        $transRepo = $this->entityManager->getRepository('App:Translation');
        $translations = $transRepo->getTranslations($handler->getTranslationsOut());

        $data = $this->translationService->parse($data, true, $translations);

        // Update current Log
        $this->logService->saveLog($this->request, null, json_encode($data));

        // Then we want to do to mapping on the outgoing response
        $skeleton = $handler->getSkeletonOut();
        if (!$skeleton || empty($skeleton)) {
            $skeleton = $data;
        }

        $data = $this->translationService->dotHydrator($skeleton, $data, $handler->getMappingOut());

        // Update current Log
        $this->logService->saveLog($this->request, null, json_encode($data));

        // Lets see if we need te use a template
        if ($handler->getTemplatetype() && $handler->getTemplate()) {
            $data = $this->renderTemplate($handler, $data);
        }

        return $data;
    }
}
