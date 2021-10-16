<?php

namespace App\Controller;

use App\Service\EavService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class EavController extends AbstractController
{
    private SerializerService $serializerService;

    public function __contstruct(SerializerInterface $serializer)
    {
        $this->serializerService = new SerializerService($serializer);
    }

    /**
     * @Route("/eav/docs", name="blog_list")
     */
    public function DocsAction(): Response
    {
        return $this->render('eav/docs.html.twig');
    }

    public function extraAction(?string $id, Request $request, EavService $eavService): Response
    {
        $offset = strlen('dynamic_eav_');
        $entityName = substr($request->attributes->get('_route'), $offset, strpos($request->attributes->get('_route'), strtolower($request->getMethod())) - 1 - $offset);

        return $eavService->handleRequest($request, $entityName);
    }

    public function deleteAction(Request $request, EavService $eavService)
    {
        $entityName = $request->attributes->get('entity');

        return $eavService->handleRequest($request, $entityName);
    }
}
