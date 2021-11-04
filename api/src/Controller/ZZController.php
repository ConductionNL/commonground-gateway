<?php

namespace App\Controller;

use App\Entity\Document;
use App\Service\DocumentService;
use App\Service\EavService;
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
    public function dynamicAction(?string $entity, ?string $id, Request $request, EavService $eavService, DocumentService $documentService): Response
    {
        $document = $this->getDoctrine()->getRepository('App:Document')->findOneBy(['route'=>$entity]);
        if ($document instanceof Document && $id) {
            return $documentService->handleDocument($document, $id);
        }

        return $eavService->handleRequest($request);
    }
}
