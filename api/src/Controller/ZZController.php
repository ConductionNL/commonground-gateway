<?php

namespace App\Controller;

use App\Entity\Document;
use App\Service\DocumentService;
use App\Service\EavService;
use App\Service\ValidationService;
use App\Service\EndpointService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ZZController extends AbstractController
{
    /**
     * @Route("/api/{entity}", name="dynamic_route_entity")
     * @Route("/api/{entity}/{id}", name="dynamic_route_collection")
     */
    public function dynamicAction(?string $entity, ?string $id,
                                  Request $request,
                                  EavService $eavService,
                                  DocumentService $documentService,
                                  ValidationService $validationService,
                                  EndpointService $endpointService): Response
    {
        // Below is hacky tacky
        $document = $this->getDoctrine()->getRepository('App:Document')->findOneBy(['route'=>$entity]);
        if ($document instanceof Document && $id) {
            return $documentService->handleDocument($document, $id);
        }
        if($entity == "postalCodes"){
            return $validationService->dutchPC4ToJson();
        }
        // End of hacky tacky

        // Let determine an endpoint (new way)
        if($endpoint = $this->getDoctrine()->getRepository('App:Endpoint')->findOneBy(['route'=>$entity])){
            return $endpointService->handleRequest($endpoint);
        }

        // Continue as normal (old way)
        return $eavService->handleRequest($request);
    }
}
