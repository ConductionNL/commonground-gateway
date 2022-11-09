<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Endpoint;
use App\Entity\Handler;
use App\Event\ActionEvent;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use JWadhams\JsonLogic;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Twig\Environment;

class HandlerService
{
    private EntityManagerInterface $entityManager;
    private TranslationService $translationService;
    private LogService $logService;
    private ProcessingLogService $processingLogService;
    private TemplateService $templateService;
    private ObjectEntityService $objectEntityService;
    private FormIOService $formIOService;
    private SubscriberService $subscriberService;
    private CacheInterface $cache;
    private GatewayService $gatewayService;
    private Stopwatch $stopwatch;
    private EventDispatcherInterface $eventDispatcher;

    // This list is used to map content-types to extentions, these are then used for serializations and downloads
    // based on https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
    public $acceptHeaderToSerialiazation = [
        'application/json'                                                                                     => 'json',
        'application/ld+json'                                                                                  => 'jsonld',
        'application/json+ld'                                                                                  => 'jsonld',
        'application/hal+json'                                                                                 => 'jsonhal',
        'application/json+hal'                                                                                 => 'jsonhal',
        'application/xml'                                                                                      => 'xml',
        'text/xml'                                                                                             => 'xml',
        'text/xml; charset=utf-8'                                                                              => 'xml',
        'text/csv'                                                                                             => 'csv',
        'text/yaml'                                                                                            => 'yaml',
        'text/html'                                                                                            => 'html',
        'application/pdf'                                                                                      => 'pdf',
        'application/msword'                                                                                   => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'                              => 'docx',
        'application/form.io'                                                                                  => 'form.io',
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
        ProcessingLogService $processingLogService,
        Environment $twig,
        TemplateService $templateService,
        ObjectEntityService $objectEntityService,
        FormIOService $formIOService,
        SubscriberService $subscriberService,
        CacheInterface $cache,
        GatewayService $gatewayService,
        Stopwatch $stopwatch,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();
        $this->validationService = $validationService;
        $this->translationService = $translationService;
        $this->soapService = $soapService;
        $this->eavService = $eavService;
        $this->serializer = $serializer;
        $this->logService = $logService;
        $this->processingLogService = $processingLogService;
        $this->templating = $twig;
        $this->templateService = $templateService;
        $this->objectEntityService = $objectEntityService->addServices($eavService); // todo: temp fix untill we no longer need these services here
        $this->formIOService = $formIOService;
        $this->subscriberService = $subscriberService;
        $this->cache = $cache;
        $this->gatewayService = $gatewayService;
        $this->stopwatch = $stopwatch;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * This function sets the endpoint in the session and executes handleHandler with its found Handler.
     */
    public function handleEndpoint(Endpoint $endpoint, array $parameters): Response
    {
        $this->stopwatch->start('invalidateTags-grantedScopes', 'handleEndpoint');
        $this->cache->invalidateTags(['grantedScopes']);
        $this->stopwatch->stop('invalidateTags-grantedScopes');

        $event = new ActionEvent('commongateway.handler.pre', ['request' => $this->getDataFromRequest(), 'response' => []]);
        $this->stopwatch->start('newSession', 'handleEndpoint');
        $session = new Session();
        $this->stopwatch->stop('newSession');
        $this->stopwatch->start('saveEndpointInSession', 'handleEndpoint');
        $session->set('endpoint', $endpoint->getId()->toString());
        $this->stopwatch->stop('saveEndpointInSession');
        $this->stopwatch->start('saveParametersInSession', 'handleEndpoint');
        $session->set('parameters', $parameters);
        $this->stopwatch->stop('saveParametersInSession');
        $this->eventDispatcher->dispatch($event, 'commongateway.handler.pre');

        // @todo creat logicdata, generalvaribales uit de translationservice

        $this->stopwatch->start('handleHandlers', 'handleEndpoint');
        foreach ($endpoint->getHandlers() as $handler) {
            // Check if handler should be used for this method
            if ($handler->getMethods() !== null) {
                $methods = array_map('strtoupper', $handler->getMethods());
            }
            if (!in_array('*', $methods) && !in_array($this->request->getMethod(), $methods)) {
                $this->stopwatch->lap('handleHandlers');
                continue;
            }
            if ($handler->getConditions() === '{}' || JsonLogic::apply(json_decode($handler->getConditions(), true), $this->getDataFromRequest())) {
                $this->stopwatch->start('saveHandlerInSession', 'handleEndpoint');
                $session->set('handler', $handler->getId());
                $this->stopwatch->stop('saveHandlerInSession');

                $this->stopwatch->start('handleHandler', 'handleEndpoint');
                $result = $this->handleHandler($handler, $endpoint, $event->getData()['request'] ?: []);
                $this->stopwatch->stop('handleHandler');
                $this->stopwatch->stop('handleHandlers');

                $event = new ActionEvent('commongateway.handler.post', array_merge($event->getData(), ['result' => $result]));
                $this->eventDispatcher->dispatch($event, 'commongateway.handler.post');

                return $result;
            }
        }

        throw new GatewayException('No handler found for endpoint: '.$endpoint->getName().' and method: '.$this->request->getMethod(), null, null, ['data' => ['id' => $endpoint->getId()], 'path' => null, 'responseType' => Response::HTTP_NOT_FOUND]);
    }

    public function cutPath(array $pathParams): string
    {
        $path = parse_url($this->request->getUri())['path'];

        return substr($path, strlen('/api/'.$pathParams[0]));
    }

    public function proxy(Handler $handler, Endpoint $endpoint, string $method): Response
    {
        $path = $this->cutPath($endpoint->getPath());

        return $this->gatewayService->processGateway($handler->getProxyGateway(), $path, $method, $this->request->getContent(), $this->request->query->all(), $this->request->headers->all());
    }

    public function getMethodOverrides(string &$method, ?string &$operationType, Handler $handler)
    {
        $overrides = $handler->getMethodOverrides();
        if (!isset($overrides[$this->request->getMethod()])) {
            return;
        }
        $content = new \Adbar\Dot($this->getDataFromRequest());

        foreach ($overrides[$this->request->getMethod()] as $override) {
            if (key_exists($method, $overrides) && (!array_key_exists('condition', $override) || $content->has($override['condition']))) {
                $method = array_key_exists('method', $override) ? $override['method'] : $method;
                $operationType = array_key_exists('operationType', $override) ? $override['operationType'] : $operationType;
                $parameters = $this->request->getSession()->get('parameters');
                if (isset($override['pathValues'])) {
                    foreach ($override['pathValues'] as $key => $value) {
                        $parameters['path'][$key] = $content->get($value);
                    }
                }
                if (isset($override['queryParameters'])) {
                    foreach ($override['queryParameters'] as $key => $value) {
                        if ($key == 'fields' || $key == '_fields') {
                            $this->request->query->set('fields', $value);
                        } else {
                            $this->request->query->set($key, $content->get($value));
                        }
                    }
                }
                $this->request->getSession()->set('parameters', $parameters);
            } elseif (key_exists($method, $overrides) && (!array_key_exists('condition', $override) || $this->request->query->has($override['condition']))) {
                $method = array_key_exists('method', $override) ? $override['method'] : $method;
                $operationType = array_key_exists('operationType', $override) ? $override['operationType'] : $operationType;
                $parameters = $this->request->getSession()->get('parameters');
                foreach ($override['pathValues'] as $key => $value) {
                    $parameters['path'][$key] = $this->request->query->get($value);
                }

                $this->request->getSession()->set('parameters', $parameters);
            }
        }
    }

    /**
     * This function walks through the $handler with $data from the request to perform mapping, translating and fetching/saving from/to the eav.
     *
     * @todo remove old eav code if new way is finished and working
     * @todo better check if $data is a document/template line 199
     */
    public function handleHandler(Handler $handler = null, Endpoint $endpoint, array $data = []): Response
    {
        $originalData = $data;
        $method = $this->request->getMethod();
        $operationType = $endpoint->getOperationType();

        if ($handler->getProxyGateway()) {
            return $this->proxy($handler, $endpoint, $method);
        }

        $this->getMethodOverrides($method, $operationType, $handler);

        // Form.io components array
        // if ($method === 'GET' && $this->getRequestType('accept') === 'form.io' && $handler->getEntity() && $handler->getEntity()->getAttributes()) {
        //   return new Response(
        //     $this->serializer->serialize($this->formIOService->createFormIOArray($handler->getEntity()), 'json'),
        //     Response::HTTP_OK,
        //     ['content-type' => 'json']
        //   );
        // }

        // To start it al off we need the data from the incomming request
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && ($data == null || empty($data))) {
            throw new GatewayException('Faulty body or no body given', null, null, ['data' => null, 'path' => 'Request body', 'responseType' => Response::HTTP_NOT_FOUND]);
        }

        // Update current Log
        isset($data) ? $this->logService->saveLog($this->request, null, 0, json_encode($data)) : $this->logService->saveLog($this->request, null, 0, null);

        // Only do mapping and translation -in for calls with body
        in_array($method, ['POST', 'PUT', 'PATCH']) && $handler && $data = $this->handleDataBeforeEAV($data, $handler);

        // eav new way
        // dont get collection if accept type is formio
        if (($this->getRequestType('accept') === 'form.io' && ($method === 'GET' && $operationType === 'item')) || $this->getRequestType('accept') !== 'form.io') {
            $handler->getEntity() !== null && $data = $this->objectEntityService->handleObject($handler, $endpoint, $data ?? null, $method, $this->getRequestType('accept'));
        }

        // Form.io components array
        if ($method === 'GET' && $this->getRequestType('accept') === 'form.io' && $handler->getEntity() && $handler->getEntity()->getAttributes()) {
            return new Response(
                $this->serializer->serialize($this->formIOService->createFormIOArray($handler->getEntity(), $data ?? null), 'json'),
                Response::HTTP_OK,
                ['content-type' => 'json']
            );
        }

        // @todo remove this when eav part works and catch this->objectEntityService->handleObject instead
        if (!isset($data)) {
            throw new GatewayException('Could not fetch object(s) on endpoint: /'.implode('/'.$endpoint->getPath()), null, null, ['data' => null, 'path' => null, 'responseType' => Response::HTTP_NOT_FOUND]);
        }

        // If data contains error dont execute following code and create response
        if (!(isset($data['type']) && isset($data['message']))) {

            // Check if we need to trigger subscribers for this entity
            $this->subscriberService->handleSubscribers($handler->getEntity(), $data, $method);

            // Update current Log
            $this->logService->saveLog($this->request, null, 2, json_encode($data));

            $event = new ActionEvent('commongateway.response.pre', ['entity' => $handler->getEntity()->getId()->toString(), 'httpRequest' => $this->request, 'request' => $originalData, 'response' => $data, 'queryParameters' => $this->request->query->all()]);
            $this->eventDispatcher->dispatch($event, 'commongateway.response.pre');
            $data = $event->getData()['response'];

            $handler && $data = $this->handleDataAfterEAV($data, $handler);
        }

        // Update current Log
        $this->logService->saveLog($this->request, null, 3, json_encode($data));

        // An lastly we want to create a response
        $response = $this->createResponse($data, $endpoint);

        // Final update Log
        $this->logService->saveLog($this->request, $response, 4, null, true);

        $this->processingLogService->saveProcessingLog();

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
          $xmlEncoder = new XmlEncoder();
          $xml = $xmlEncoder->decode($content, $contentType);
        // otherwise xml will throw its own error bypassing our exception handling
//        libxml_use_internal_errors(true);
        // string to xml object, encode that to json then decode to array
//        $xml = simplexml_load_string($content);
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
    public function createResponse(array $data, ?Endpoint $endpoint = null): Response
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
        $status = Response::HTTP_OK;
        break;
      case 'UPDATE':
        $status = Response::HTTP_OK;
        break;
      case 'DELETE':
        $status = Response::HTTP_NO_CONTENT;
        break;
      default:
        $status = Response::HTTP_OK;
    }

        $this->stopwatch->start('getRequestType', 'createResponse');
        $acceptType = $this->getRequestType('accept', $endpoint);
        $this->stopwatch->stop('getRequestType');

        // Lets fill in some options
        $options = [];
        $this->stopwatch->start('switchAcceptType', 'createResponse');
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
            case 'xml':
                $options['xml_root_node_name'] = array_keys($data)[0];
                $data = $data[array_keys($data)[0]];
                break;

        }
        $this->stopwatch->stop('switchAcceptType');

        // Lets seriliaze the shizle (if no document and we have a result)
        $this->stopwatch->start('serialize', 'createResponse');

        try {
            !isset($document) && $result = $this->serializer->serialize($data, $acceptType, $options);
        } catch (NotEncodableValueException $e) {
            !isset($document) && $result = $this->serializer->serialize($data, 'json', $options);
            // throw new GatewayException($e->getMessage(), null, null, ['data' => null, 'path' => null, 'responseType' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE]);
        }
        $this->stopwatch->stop('serialize');

        // Lets create the actual response
        $this->stopwatch->start('newResponse', 'createResponse');
        $response = new Response(
            $result,
            $status,
//            ['content-type' => $this->acceptHeaderToSerialiazation[array_search($acceptType, $this->acceptHeaderToSerialiazation)]]
            //todo: should be ^ for taalhuizen we need accept = application/json to result in content-type = application/json
            ['content-type' => array_search($acceptType, $this->acceptHeaderToSerialiazation)]
        );
        $this->stopwatch->stop('newResponse');

        // Lets handle file responses
        $this->stopwatch->start('routeParameters', 'createResponse');
        $routeParameters = $this->request->attributes->get('_route_params');
        if (array_key_exists('extension', $routeParameters) && $extension = $routeParameters['extension']) {
            $date = new \DateTime();
            $date = $date->format('Ymd_His');
            $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$routeParameters['route']}_{$date}.{$acceptType}");
            $response->headers->set('Content-Disposition', $disposition);
        }
        $this->stopwatch->stop('routeParameters');

        $this->stopwatch->start('prepareResponse', 'createResponse');
        $response->prepare($this->request);
        $this->stopwatch->stop('prepareResponse');

        return $response;
    }

    /**
     * Validates content or accept type from request.
     *
     * @param string $type 'content-type' or 'accept'
     *
     * @return string Accept or content-type
     */
    public function getRequestType(string $type, ?Endpoint $endpoint = null): string
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
        if ((!isset($typeValue) || $typeValue === '*/*' || empty($typeValue)) && isset($endpoint)) {
            $typeValue = $endpoint->getDefaultContentType() ?: 'application/json';
        } else {
            (!isset($typeValue) || $typeValue === '*/*' || empty($typeValue)) && $typeValue = 'application/json';
        }
        //todo: temp fix for taalhuizen, should be removed after front-end changes
        if ($typeValue == 'text/plain;charset=UTF-8') {
            return 'json';
        }
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
        $this->logService->saveLog($this->request, null, 5, json_encode($data));

        if (!empty($handler->getTranslationsIn())) {
            // Then we want to do translations on the incomming request
            $transRepo = $this->entityManager->getRepository('App:Translation');

            $translations = $transRepo->getTranslations($handler->getTranslationsIn());

            if (!empty($translations)) {
                $data = $this->translationService->parse($data, true, $translations);
            }
        }
        // Update current Log
        $this->logService->saveLog($this->request, null, 6, json_encode($data));

        return $data;
    }

    private function handleDataAfterEAV(array $data, Handler $handler): array
    {
        $data = $this->translationService->addPrefix($data, $handler->getPrefix());

        // Then we want to do to mapping on the outgoing response
        $skeleton = $handler->getSkeletonOut();
        if (!$skeleton || empty($skeleton)) {
            $skeleton = $data;
        }
        $this->stopwatch->start('dotHydrator2', 'handleDataAfterEAV');
        $data = $this->translationService->dotHydrator($skeleton, $data, $handler->getMappingOut());

        $this->stopwatch->stop('dotHydrator2');

        // Update current Log
        $this->stopwatch->start('saveLog7', 'handleDataAfterEAV');
        $this->logService->saveLog($this->request, null, 7, json_encode($data));
        $this->stopwatch->stop('saveLog7');

        if (!empty($handler->getTranslationsOut())) {
            // Then we want to do  translations on the outgoing response
            $transRepo = $this->entityManager->getRepository('App:Translation');

            $this->stopwatch->start('getTranslations2', 'handleDataAfterEAV');
            $translations = $transRepo->getTranslations($handler->getTranslationsOut());
            $this->stopwatch->stop('getTranslations2');

            if (!empty($translations)) {
                $this->stopwatch->start('parse2', 'handleDataAfterEAV');
                $data = $this->translationService->parse($data, true, $translations);
                $this->stopwatch->stop('parse2');
            }
        }

        // Update current Log
        $this->stopwatch->start('saveLog8', 'handleDataAfterEAV');
        $this->logService->saveLog($this->request, null, 8, json_encode($data));
        $this->stopwatch->stop('saveLog8');

        // Lets see if we need te use a template
        if ($handler->getTemplatetype() && $handler->getTemplate()) {
            $this->stopwatch->start('renderTemplate', 'handleDataAfterEAV');
            $data = $this->renderTemplate($handler, $data);
            $this->stopwatch->stop('renderTemplate');
        }

        return $data;
    }

    /**
     * Gets a handler for an endpoint method combination.
     *
     * @param Endpoint $endpoint
     * @param string   $method
     *
     * @return Handler|bool
     */
    public function getHandler(Endpoint $endpoint, string $method)
    {
        foreach ($endpoint->getHandlers() as $handler) {
            if (in_array('*', $handler->getMethods())) {
                return $handler;
            }

            // Check if handler should be used for this method
            if (in_array($method, $handler->getMethods())) {
                return $handler;
            }
        }

        return false;
    }
}
