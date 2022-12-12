<?php

namespace App\Controller;

use App\Entity\Document;
use App\Exception\GatewayException;
use App\Service\DocumentService;
use App\Service\HandlerService;
use App\Service\LogService;
use App\Service\ProcessingLogService;
use App\Service\ValidationService;
use CommonGateway\CoreBundle\Service\RequestService;
use Doctrine\ORM\NonUniqueResultException;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;

class ZZController extends AbstractController
{
    /**
     * @Route("/api/{path}", name="dynamic_route", requirements={"path" = ".+"})
     */
    public function dynamicAction(
        ?string $path,
        Request $request,
        DocumentService $documentService,
        ValidationService $validationService,
        HandlerService $handlerService,
        SerializerInterface $serializer,
        LogService $logService,
        ProcessingLogService $processingLogService,
        RequestService $requestService
    ): Response {
        // Below is hacky tacky
        // @todo refactor
        $id = substr($path, strrpos($path, '/') + 1);

        /*
        if (Uuid::isValid($id)) {
            $document = $this->getDoctrine()->getRepository('App:Document')->findOneBy(['route' => str_replace('/'.$id, '', $path)]);
            if ($document instanceof Document) {
                return $documentService->handleDocument($document, $id);
            }
        }
        // postalCodes list for bisc/taalhuizen
        if ($path === 'postalCodes') {
            return $validationService->dutchPC4ToJson();
        }
        */
        // End of hacky tacky

        // default acceptType for if we throw an error response.
        $acceptType = $handlerService->getRequestType('accept');
        in_array($acceptType, ['form.io', 'jsonhal']) && $acceptType = 'json';

        // Get full path
        try {
            $endpoint = $this->getDoctrine()->getRepository('App:Endpoint')->findByMethodRegex($request->getMethod(), $path);
//            if(in_array($endpoint->getMethods(), $request->getMethod()))
        } catch (NonUniqueResultException $exception) {
            return new Response(
                $serializer->serialize(['message' =>  'Found more than one Endpoint with this path and/or method', 'data' => ['path' => $path, 'method' => $request->getMethod()], 'path' => $path], $acceptType),
                Response::HTTP_BAD_REQUEST,
                ['content-type' => $acceptType]
            );
        }

        // exit here if we do not have an endpoint
        if (!isset($endpoint)) {
            return new Response(
                $serializer->serialize(['message' =>  'Could not find an Endpoint with this path and/or method', 'data' => ['path' => $path, 'method' => $request->getMethod()], 'path' => $path], $acceptType),
                Response::HTTP_BAD_REQUEST,
                ['content-type' => $acceptType]
            );
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
        //todo use eavService->realRequestQueryAll(), maybe replace this function to another service than eavService?

        $parameters['querystring'] = $request->getQueryString();
        $parameters['endpoint'] = $endpoint;

        try {
            $parameters['body'] = $request->toArray();
        } catch (\Exception $exception) {
        }

        $parameters['crude_body'] = $request->getContent();

        $parameters['method'] = $request->getMethod();
        $parameters['query'] = $request->query->all();

        // Lets get all the headers
        $parameters['headers'] = $request->headers->all();

        // Lets get all the post variables
        $parameters['post'] = $request->request->all();

        if ($endpoint->getProxy()) {
            return $requestService->proxyHandler($parameters, []);
        }

        // Try handler proces and catch exceptions
        try {
            $result = $handlerService->handleEndpoint($endpoint, $parameters);

            return $result;
        } catch (GatewayException $gatewayException) {
            $options = $gatewayException->getOptions();
            $acceptType = $handlerService->getRequestType('accept');

            try {
                $response = new Response(
                    $serializer->serialize(['message' =>  $gatewayException->getMessage(), 'data' => $options['data'], 'path' => $options['path']], $acceptType),
                    $options['responseType'] ?? Response::HTTP_INTERNAL_SERVER_ERROR,
//                    ['content-type' => $this->acceptHeaderToSerialiazation[array_search($acceptType, $handlerService->acceptHeaderToSerialiazation)]]
                    //todo: should be ^ for taalhuizen we need accept = application/json to result in content-type = application/json
                    ['content-type' => array_search($acceptType, $handlerService->acceptHeaderToSerialiazation)]
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
