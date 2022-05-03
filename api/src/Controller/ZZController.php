<?php

namespace App\Controller;

use App\Entity\Document;
use App\Exception\GatewayException;
use App\Service\DocumentService;
use App\Service\EavService;
use App\Service\HandlerService;
use App\Service\LogService;
use App\Service\ProcessingLogService;
use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ZZController extends AbstractController
{
    /**
     * @Route("/api/{path}", name="dynamic_route", requirements={"path" = ".+"})
     */
    public function dynamicAction(
        ?string $path,
        Request $request,
        EavService $eavService,
        DocumentService $documentService,
        ValidationService $validationService,
        HandlerService $handlerService,
        SerializerInterface $serializer,
        LogService $logService,
        ProcessingLogService $processingLogService,
        Stopwatch $stopwatch
    ): Response {
        $stopwatch->start('ZZController');

        // Below is hacky tacky
        // @todo refactor
        //        $document = $this->getDoctrine()->getRepository('App:Document')->findOneBy(['route' => $entity]);
        //        if ($document instanceof Document && $id) {
        //            return $documentService->handleDocument($document, $id);
        //        }
        // postalCodes list for bisc/taalhuizen
        if ($path === 'postalCodes') {
            return $validationService->dutchPC4ToJson();
        }
        // End of hacky tacky

        // Get full path
        // We should look at a better search moddel in sql
        $stopwatch->start('getEndpoint', 'ZZController');
        $endpoint = $this->getDoctrine()->getRepository('App:Endpoint')->findByMethodRegex($request->getMethod(), $path);
        $stopwatch->stop('getEndpoint');

        // exit here if we do not have an endpoint
        if (!isset($endpoint)) {
            $acceptType = $handlerService->getRequestType('accept');
            $acceptType === 'form.io' && $acceptType = 'json';

            return new Response(
                $serializer->serialize(['message' =>  'Could not find an Endpoint with this path and/or method', 'data' => ['path' => $path, 'method' => $request->getMethod()], 'path' => $path], $acceptType),
                Response::HTTP_BAD_REQUEST,
                ['content-type' => $acceptType]
            );

            return $response->prepare($request);
        }

        // Let create the variable
        // Create array for filtering (in progress, should be moved to the correct service)
        $parameters = ['path' => [], 'query' => [], 'post' => []];
        $pathArray = array_values(array_filter(explode('/', $path)));
        foreach ($endpoint->getPath() as $key => $pathPart) {
            // Let move path parts that are defined as variables to the filter array
            if (array_key_exists($key, $pathArray)) {
                $parameters['path'][$pathPart] = $pathArray[$key];
            }
        }

        // Lets add the query parameters to the variables
        $parameters['query'] = $request->query->all();

        // Lets get all the post variables
        $parameters['post'] = $request->request->all();

        // Lets get all the headers
        $parameters['headers'] = $request->headers->all();

        // Try handler proces and catch exceptions
        try {
            $stopwatch->start('handleEndpoint', 'ZZController');
            $result = $handlerService->handleEndpoint($endpoint, $parameters);
            $stopwatch->stop('handleEndpoint');
            $stopwatch->stop('ZZController');

            return $result;
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
            $logService->saveLog($request, $response, 10);
            $processingLogService->saveProcessingLog();

            return $response->prepare($request);
        }
    }
}
