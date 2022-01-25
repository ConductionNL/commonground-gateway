<?php

namespace App\Controller;

use App\Entity\Document;
use App\Exception\GatewayException;
use App\Service\DocumentService;
use App\Service\EavService;
use App\Service\HandlerService;
use App\Service\LogService;
use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class ZZController extends AbstractController
{
    /**
     * @Route("/api/{entity}", name="dynamic_route_entity")
     * @Route("/api/{entity}/{id}", name="dynamic_route_collection")
     */
    public function dynamicAction(
        ?string $entity,
        ?string $id,
        Request $request,
        EavService $eavService,
        DocumentService $documentService,
        ValidationService $validationService,
        HandlerService $handlerService,
        SerializerInterface $serializer,
        LogService $logService
    ): Response {
        // Below is hacky tacky
        $document = $this->getDoctrine()->getRepository('App:Document')->findOneBy(['route' => $entity]);
        if ($document instanceof Document && $id) {
            return $documentService->handleDocument($document, $id);
        }
        if ($entity == 'postalCodes') {
            return $validationService->dutchPC4ToJson();
        }
        // End of hacky tacky

        // Let determine an endpoint (new way)
        if ($endpoint = $this->getDoctrine()->getRepository('App:Endpoint')->findOneBy(['path' => $entity])) {
            // Try handler proces and catch exceptions
            try {
                return $handlerService->handleEndpoint($endpoint);
            } catch (GatewayException $gatewayException) {
                $options = $gatewayException->getOptions();
                $acceptType = $handlerService->getRequestType('accept');
                try {
                    $response = new Response(
                        $serializer->serialize(['message' =>  $gatewayException->getMessage(), 'data' => $options['data'], 'path' => $options['path']], $acceptType),
                        $options['responseType'] ?? Response::HTTP_INTERNAL_SERVER_ERROR,
                        ['content-type' => $acceptType]
                    );
                } catch (NotEncodableValueException $e) {
                    $response = new Response(
                        $serializer->serialize(['message' =>  $gatewayException->getMessage(), 'data' => $options['data'], 'path' => $options['path']], 'json'),
                        $options['responseType'] ?? Response::HTTP_INTERNAL_SERVER_ERROR,
                        ['content-type' => 'json']
                    );
                }
                $logService->saveLog($request, $response);

                return $response->prepare($request);
            }
        }

        // Continue as normal (old way)
        return $eavService->handleRequest($request);
    }
}
