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
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;

class ZZController extends AbstractController
{
    /**
     * @Route("/api/{path}", name="dynamic_route_entity")
     * @Route("/api/{path}/{subPath2}")
     * @Route("/api/{path}/{subPath2}/{subPath3}")
     * @Route("/api/{path}/{subPath2}/{subPath3}/{subPath4}")
     * @Route("/api/{entity}/{id}", name="dynamic_route_collection")
     */
    public function dynamicAction(
        ?string $path,
        ?string $subPath2,
        ?string $subPath3,
        ?string $subPath4,
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

        // Get full path
        // @TODO wont work with more than 4 subpaths
        $fullPath = '';
        isset($path) && $fullPath .= '/'.$path;
        isset($subPath2) && $fullPath .= '/'.$subPath2;
        isset($subPath3) && $fullPath .= '/'.$subPath3;
        isset($subPath4) && $fullPath .= '/'.$subPath4;

        $allEndpoints = $this->getDoctrine()->getRepository('App:Endpoint')->findAll();

        // Match path to regex of Endpoints
        foreach ($allEndpoints as $endpoint) {
            if ($endpoint->getPathRegex() !== null && preg_match($endpoint->getPathRegex(), $fullPath)) {
                $matchedEndpoint = $endpoint;
                break;
            }
        }

        // Let determine an endpoint (new way)
        if (isset($matchedEndpoint)) {

            // Create array for filtering (in progress, should be moved to the correct service)
            $filterArray = [];
            $fullPathArray = array_values(array_filter(explode('/', $fullPath)));
            foreach ($matchedEndpoint->getPath() as $key => $pathPart) {
                if (substr($pathPart, 0)[0] == '{') {
                    $variable = str_replace('{', '', $pathPart);
                    $variable = str_replace('}', '', $variable);
                    $filterArray[$variable] = $fullPathArray[$key];
                }
            }
            var_dump($filterArray);
            exit;

            // Try handler proces and catch exceptions
            try {
                return $handlerService->handleEndpoint($matchedEndpoint);
            } catch (GatewayException $gatewayException) {
                $options = $gatewayException->getOptions();
                $acceptType = $handlerService->getRequestType('accept');

                try {
                    $response = new Response(
                        $serializer->serialize(['message' =>  $gatewayException->getMessage(), 'data' => $options['data'], 'path' => $options['path']], $acceptType),
                        $options['responseType'] ?? Response::HTTP_INTERNAL_SERVER_ERROR,
                        ['content-type' => $acceptType]
                    );
                    // Catch NotEncodableValueException from symfony serializer
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
