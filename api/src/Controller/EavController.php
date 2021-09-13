<?php


namespace App\Controller;


use App\Service\EavService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use function GuzzleHttp\json_decode;

class EavController extends AbstractController
{
    private SerializerService $serializerService;


    public function __contstruct(SerializerInterface $serializer)
    {
        $this->serializerService = new SerializerService($serializer);
    }

    public function extraAction(Request $request, EavService $eavService): Response
    {
        $route = $request->attributes->get('_route');
        $entityName = explode('_', $route)[2];

        $entity = $eavService->getEntity($entityName);
        $body = json_decode($request->getContent(), true);

        // Checking and validating the id
        $id = $request->attributes->get("id");
        // The id might be contained somwhere else, lets test for that
        $id = $eavService->getId($body, $id);

        return $eavService->getResponse($id, $entityName, $body, $request, $entity);
    }

}