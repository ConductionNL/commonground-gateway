<?php

namespace App\Controller;

use App\Service\EavService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ZZController extends AbstractController
{
    /**
     * @Route("/api/{entity}", name="dynamic_route_entity")
     * @Route("/api/{entity}/{id}", name="dynamic_route_collection")
     */
    public function dynamicAction(?string $entity, ?string $id, Request $request, EavService $eavService, EntityManagerInterface $em, SerializerInterface $serializer): Response
    {
        return $eavService->handleRequest($request);
    }
}
