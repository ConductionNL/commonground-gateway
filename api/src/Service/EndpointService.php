<?php

namespace App\Service;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use App\Entity\Entity;
use App\Entity\Endpoint;
use App\Entity\Handler;
use App\Service\EavService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\Serializer\Encoder\CsvEncoder;

class EndpointService extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private TranslationService $translationService;
    private EavService $eavService;
    private SessionInterface $session;
    private LogService $logService;

    // This list is used to map content-types to extentions, these are then used for serializations and downloads
    // based on https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
    public  $acceptHeaderToSerialiazation = [
        'application/json'     => 'json',
        'application/ld+json'  => 'jsonld',
        'application/json+ld'  => 'jsonld',
        'application/hal+json' => 'jsonhal',
        'application/json+hal' => 'jsonhal',
        'application/xml'      => 'xml',
        'text/csv'             => 'csv',
        'text/yaml'            => 'yaml',
        'text/html'            => 'html',
        'application/pdf'      => 'pdf',
        'application/msword'   => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'            => 'docx',
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        TranslationService $translationService,
        EavService $eavService,
        SessionInterface $session,
        LogService $logService
    )
    {
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();
        $this->translationService = $translationService;
        $this->eavService = $eavService;
        $this->session = $session;
        $this->logService = $logService;
    }

    /**
     * This function determines the endpoint.
     */
    public function handleEndpoint(Endpoint $enpoint): Response
    {
        $this->session->set('endpoint', $enpoint);
        // @todo creat logicdata, generalvaribales uit de translationservice

        foreach($enpoint->getHandlers() as $handler){
            // Check the JSON logic (voorbeeld van json logic in de validatie service)
            /* @todo actually check for json logic */
            if(true){
                $this->session->set('handler', $handler);
                return $this->handleHandler($handler);
            }
        }

        // @todo we should not end up here so lets throw an 'no handler found' error
    }


    /**
     * This function determines the handler.
     */
    public function handleHandler(Handler $handler): Response
    {
        $request = new Request();
        // To start it al off we need the data from the incoming request
        $data = $this->getDataFromRequest($request);

        // Then we want to do the mapping in the incoming request
        $skeleton = $handler->getSkeletonIn();
        if(!$skeleton || empty($skeleton)){
            $skeleton = $data;
        }
        $data = $this->translationService->dotHydrator($skeleton, $data, $handler->getMappingIn());

        // We want to do  translations on the incoming request
        $translations =  $this->getDoctrine()->getRepository('App:Translation')->getTranslations($handler->getTranslationsIn());
        $data = $this->translationService->parse($data, true, $translations);

        // If the handler is teid to an EAV object we want to resolve that in all of it glory
        if($entity = $handler->getEntity()){
            $data = $this->eavSwitch($request, $entity);
        }

        // We want to do  translations on the outgoing response
        $translations =  $this->getDoctrine()->getRepository('App:Translation')->getTranslations($handler->getTranslationsOut());
        $data = $this->translationService->parse($data, true, $translations);

        // Then we want to do to mapping on the outgoing response
        $skeleton = $handler->getSkeletonOut();
        if(!$skeleton || empty($skeleton)){
            $skeleton = $data;
        }
        $data = $this->translationService->dotHydrator($skeleton, $data, $handler->getMappingOut());


        // Lets see if we need te use a template
        if($handler->getTemplatetype() && $handler->getTemplate()){
            $data = $this->renderTemplate($handler, $data);
        }

        // An lastly we want to create a responce
        $response = $this->createResponse($data);

        // Let log the stack
//        $this->logService->createLog($response, $request);

        return $response;
    }


    public function getDataFromRequest(Request $request): array
    {
        //@todo support xml messages

        if($request->getContent()) {
            $body = json_decode($request->getContent(), true);
        }

        return $body;
    }

    public function eavSwitch(Request $request, Entity $entity): array
    {
        // Let grap the request
        $request = new Request();

        // We only end up here if there are no errors, so we only suply best case senario's
        switch ($request->getMethod()) {
            case 'GET':
                return $this->eavService->getEntity();
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
                /* invalid method */
                /* @todo throw error */
        }
    }

    public function createResponse(array $data): Response
    {
        // Let grab the request
        $request = new Request();

        // We only end up here if there are no errors, so we only suply best case senario's
        switch ($request->getMethod()){
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
        }

        // Lets seriliaze the shizle
        $result = $this->serializerService->serialize($data, $contentType, $options);

        // Lets create the actual response
        $response = new Response(
            $result,
            $status,
            $this->acceptHeaderToSerialiazation[array_search($contentType, $this->acceptHeaderToSerialiazation)]
        );

        // Lets handle file responses
        $routeParameters = $request->attributes->get('_route_params');
        if(array_key_exists('extension') && $extension = $routeParameters['extension']){
            $date = new \DateTime();
            $date = $date->format('Ymd_His');
            $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$routeParameters['route']}_{$date}.{$contentType}");
            $response->headers->set('Content-Disposition', $disposition);
        }

        $response->prepare($request);

        return $response;
    }

    private function getRequestContentType(): string
    {
        // Let grab the request
        $request = new Request();

        // Lets grap the route parameters
        $routeParameters = $request->attributes->get('_route_params');

        // If we have an extension and the extension is a valid serialization format we will use that
        if(array_key_exists('extension', $routeParameters)){
            if(in_array($routeParameters['extension'], $this->acceptHeaderToSerialiazation)) {
                return $routeParameters['extension'];
            }
            else{
                /* @todo throw error, invalid extension requested */
            }
        }

        // Let's pick the first acceptable content type that we support
        foreach($request->getAcceptableContentTypes() as $contentType){
            if(array_key_exists($contentType, $this->acceptHeaderToSerialiazation)){
                return $this->acceptHeaderToSerialiazation[$contentType];
            }
        }

        // If we end up here we are dealing with an unsupported content type
        /* @todo throw error */
    }


    private function renderTemplate(Handler $handler, array $data): string
    {
        /* @todo add global variables */
        $variables = $data;

        // We only end up here if there are no errors, so we only suply best case senario's
        switch ($handler->getTemplateType()){
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
                /* @todo we shouldnt end up here so throw an errar */
        }
    }
}

