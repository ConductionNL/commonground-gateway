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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Authors: Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>.
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 */
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
        RequestService $requestService
    ): Response {
        $parameters = $this->getParametersFromRequest([], $request);
        $parameters['accept'] = $this->getAcceptType($request);

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

    private function getAcceptType(Request $request): string
    {
        // Lets first look at the accept header.
        $acceptHeader = $request->headers->get('accept');

        // As a backup we look at any file extenstion.
        $path = $request->getPathInfo();
        $pathparts = explode('.', $path);
        if (count($pathparts) >= 2) {
            $extension = end($pathparts);
            switch ($extension) {
                case 'pdf':
                    return 'pdf';
            }//end switch
        }

        // Determine the accept type.
        switch ($acceptHeader) {
            case 'application/pdf':
                return 'pdf';
            case 'application/json':
            case '*/*':
                return 'json';
            case 'application/json+hal':
            case 'application/hal+json':
                return 'jsonhal';
            case 'application/json+ld':
            case 'application/ld+json':
                return 'jsonld';
            case 'application/json+fromio':
            case 'application/formio+json':
                return 'formio';
            case 'application/json+schema':
            case 'application/schema+json':
                return 'schema';
            case 'application/json+graphql':
            case 'application/graphql+json':
                return 'graphql';
            case 'text/xml':
            case 'application/xml':
                return 'xml';
            case 'text/html':
                return 'html';
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return 'docx';
        }//end switch

        throw new BadRequestHttpException('No proper accept could be determined');
    }//end getAcceptType()
}
