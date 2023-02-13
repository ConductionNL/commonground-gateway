<?php

namespace App\Controller;

use App\Exception\GatewayException;
use App\Service\DocumentService;
use App\Service\HandlerService;
use App\Service\LogService;
use App\Service\ProcessingLogService;
use CommonGateway\CoreBundle\Service\EndpointService;
use CommonGateway\CoreBundle\Service\RequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ZZController extends AbstractController
{
    /**
     * This function handles objects in line with the new request service.
     *
     * @Route("/admin/objects")
     * @Route("/admin/objects/{id}", requirements={"path" = ".+"})
     *
     * @param string|null         $path
     * @param Request             $request
     * @param SerializerInterface $serializer
     * @param HandlerService      $handlerService
     * @param RequestService      $requestService
     *
     * @return Response
     */
    public function objectAction(
        ?string $id,
        Request $request,
        SerializerInterface $serializer,
        HandlerService $handlerService,
        RequestService $requestService
    ): Response {
        $parameters = $this->getParametersFromRequest([], $request);

        // We should check if we have an id
        if ($id) {
            $parameters['path']['{id}'] = $id;
        }

        return $requestService->requestHandler($parameters, []);
    }

    /**
     * This function dynamicly handles the api endpoints.
     *
     * @Route("/api/{path}", name="dynamic_route", requirements={"path" = ".+"})
     *
     * @param string|null          $path
     * @param Request              $request
     * @param DocumentService      $documentService
     * @param HandlerService       $handlerService
     * @param SerializerInterface  $serializer
     * @param LogService           $logService
     * @param ProcessingLogService $processingLogService
     * @param RequestService       $requestService
     *
     * @throws GatewayException
     *
     * @return Response
     */
    public function dynamicAction(
        ?string $path,
        Request $request,
        EndpointService $endpointService
    ): Response {
        return $endpointService->handleRequest($request);
    }

    /**
     * Builds a parameter array from the request.
     *
     * @param ?array   $parameters An optional starting array of parameters
     * @param ?Request $request    The request (autowired so doesn't need te be provided
     *
     * @return array The parameter arrau
     */
    private function getParametersFromRequest(?array $parameters = [], ?Request $request): array
    {

        // Lets make sure that we always have a path
        if (!isset($parameters['path'])) {
            $parameters['path'] = [];
        }

        $parameters['querystring'] = $request->getQueryString();

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

        return $parameters;
    }
}
