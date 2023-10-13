<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Endpoint;
use App\Entity\Handler;
use App\Event\ActionEvent;
use App\Exception\GatewayException;
use CommonGateway\CoreBundle\Service\RequestService;
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
// Hack van de maand award
use Symfony\Component\Stopwatch\Stopwatch;
// Hack van de maand award
use Twig\Environment;

/**
 * @Author Barry Brands <barry@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 *
 * @deprecated
 */
class HandlerService
{
    private EntityManagerInterface $entityManager;
    private TranslationService $translationService;
    private LogService $logService;
    private ProcessingLogService $processingLogService;
    private TemplateService $templateService;
    private CacheInterface $cache;
    private GatewayService $gatewayService;
    private Stopwatch $stopwatch;
    private EventDispatcherInterface $eventDispatcher;
    private RequestService $requestService;

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
        TranslationService $translationService,
        EavService $eavService,
        SerializerInterface $serializer,
        LogService $logService,
        ProcessingLogService $processingLogService,
        Environment $twig,
        TemplateService $templateService,
        CacheInterface $cache,
        GatewayService $gatewayService,
        Stopwatch $stopwatch,
        EventDispatcherInterface $eventDispatcher,
        RequestService $requestService
    ) {
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();
        $this->translationService = $translationService;
        $this->eavService = $eavService;
        $this->serializer = $serializer;
        $this->logService = $logService;
        $this->processingLogService = $processingLogService;
        $this->templating = $twig;
        $this->templateService = $templateService;
        $this->cache = $cache;
        $this->gatewayService = $gatewayService;
        $this->stopwatch = $stopwatch;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestService = $requestService;
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
