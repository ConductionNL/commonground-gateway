<?php

namespace App\Service;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use App\Entity\Document;
use App\Entity\File;
use App\Entity\Endpoint;
use App\Service\EavService;
use App\Service\ValidationService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\AcceptHeader;

class EndpointService
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
     * Get the data for a document and send it to the document creation service.
     */
    public function handleEndpoint(Endpoint $enpoint): Response
    {
        /* @todo endpoint toevoegen aan sessie */
        $session = new Session();
        $session->set('endpoint', $enpoint);
        // @tod creat logicdata, generalvaribales uit de translationservice

        foreach($enpoint->getHandlers() as $handler){
            // Check the JSON logic (voorbeeld van json logic in de validatie service)
            if(true){
                $session->set('handler', $handler);
                return $this->handleHandler($handler);
            }
        }

        // @todo we should end here so lets throw an error
    }


    /**
     * Get the data for a document and send it to the document creation service.
     */
    public function handleHandler(Handler $handler): Response
    {
        $request = new Request();
        // To start it al off we need the data from the incomming request
        $data = $this->getDataFromRequest($request);

        // Then we want to do the mapping in the incomming request
        $skeleton = $handler->getSkeletonIn();
        if(!$skeleton || empty($skeleton)){
            $skeleton = $data;
        }
        $data = $this->translationService->dotHydrator($skeleton, $data, $handler->getMappingIn());

        // The we want to do  translations on the incomming request
        $translations =  $this->getDoctrine()->getRepository('App:Translation')->getTranslations($handler->getTranslationsIn);
        $data = $this->translationService->parse($data, true, $translations);

        // If the handler is teid to an EAV object we want to resolve that in all of it glory
        if($entity = $handler->getEntity()){
            $data = $this->eavService->generateResult($request, $entity);
        }

        // The we want to do  translations on the outgoing responce
        $translations =  $this->getDoctrine()->getRepository('App:Translation')->getTranslations($handler->getTranslationsOut);
        $data = $this->translationService->parse($data, true, $translations);

        // Then we want to do to mapping on the outgoing responce
        $skeleton = $handler->getSkeletonOut();
        if(!$skeleton || empty($skeleton)){
            $skeleton = $data;
        }
        $data = $this->translationService->dotHydrator($skeleton, $data, $handler->getMappingOut());

        // An lastly we want to create a responce
        $response = $this->createResponse($data);

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

    public function createResponse(array $data): Response
    {
        // Let grap the request
        $request = new Request();

        // Let create the actual responce
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
        // Let grap the request
        $request = new Request();

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
            // 'text/yaml'            => 'pdf',
        ];

        // If we have an extension and the extension is a valid serialization format we will use that
        if ($extension && $search =  array_search($extension, $acceptHeaderToSerialiazation)) {
            return $extension;
        }

        // Lets pick the first accaptable content type that we support
        foreach($request->getAcceptableContentTypes() as $contentType){
            if(array_key_exists($contentType, $acceptHeaderToSerialiazation)){
                return $acceptHeaderToSerialiazation[$contentType];
            }
        }

        // If we end up here we are dealing with an unsupported content type
        /* @todo throw error */
    }
}
