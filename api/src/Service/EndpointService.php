<?php

namespace App\Service;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use App\Entity\Document;
use App\Entity\File;
use App\Entity\Endpoint;
use App\Entity\Handler;
use App\Service\EavService;
use App\Service\ValidationService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\AcceptHeader;

class EndpointService extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private Request $request;
    private ValidationService $validationService;
    private TranslationService $translationService;
    private SOAPService $soapService;
    private EavService $eavService;

    public function __construct(
        EntityManagerInterface $entityManager,
        Request $request,
        ValidationService $validationService,
        TranslationService $translationService,
        SOAPService $soapService,
        EavService $eavService)
    {
        $this->entityManager = $entityManager;
        $this->request = $request;
        $this->validationService = $validationService;
        $this->translationService = $translationService;
        $this->soapService = $soapService;
        $this->eavService = $eavService;
    }

    /**
     * This function determines the endpoint.
     */
    public function handleEndpoint(Endpoint $endpoint): Response
    {
        /* @todo endpoint toevoegen aan sessie */
        $session = new Session();
        $session->set('endpoint', $endpoint);
        // @todo create logicData, generalVariables uit de translationService

        foreach($endpoint->getHandlers() as $handler){
            // Check the JSON logic (voorbeeld van json logic in de validatie service)
            if(true){
                $session->set('handler', $handler);
                return $this->handleHandler($handler);
            }
        }

        // @todo we should end here so lets throw an error
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

        // If the handler is tied to an EAV object we want to resolve that in all of it glory
        if($entity = $handler->getObject()){
            $data = $this->eavService->generateResult($request, $entity);
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

        // And lastly we want to create and return a response
        return $this->createResponse($data);
    }


    public function getDataFromRequest(Request $request): array
    {
        //@todo support xml messages

        if($request->getContent()) {
            $body = json_decode($request->getContent(), true);
        }

        return $body;
    }

    /**
     * This function creates a response.
     */
    public function createResponse(array $data): Response
    {
        // Let grab the request
        $request = new Request();

        // Let create the actual response
        $response = new Response(
            $data,
            Response::HTTP_OK,
            $this->getRequestContentType()
        );

        $response->prepare($request);

        return $response;
    }

    private function getRequestContentType(?string $extension): string
    {
        // Let grab the request
        $request = new Request();

        // @todo we should grap the extension from the route properties
        $extension = false;

        // This should be moved to the commonground service and called true $this->serializerService->getRenderType($contentType);
        // based on https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
        $acceptHeaderToSerialiazation = [
            'application/json'     => 'json',
            'application/ld+json'  => 'jsonld',
            'application/json+ld'  => 'jsonld',
            'application/hal+json' => 'jsonhal',
            'application/json+hal' => 'jsonhal',
            'application/xml'      => 'xml',
            'text/csv'             => 'csv',
            'text/yaml'            => 'yaml',
            // 'text/html'     => 'html',
            // 'application/pdf'            => 'pdf',
            // 'application/msword'            => 'doc',
            // 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'            => 'docx',
        ];

        // If we have an extension and the extension is a valid serialization format we will use that
        if ($extension && $search =  array_search($extension, $acceptHeaderToSerialiazation)) {
            return $extension;
        }

        // Let's pick the first acceptable content type that we support
        foreach($request->getAcceptableContentTypes() as $contentType){
            if(array_key_exists($contentType, $acceptHeaderToSerialiazation)){
                return $acceptHeaderToSerialiazation[$contentType];
            }
        }

        // If we end up here we are dealing with an unsupported content type
        /* @todo throw error */
    }
}

