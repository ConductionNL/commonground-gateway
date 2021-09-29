<?php


namespace App\Controller;


use App\Service\AuthenticationService;
use App\Service\AuthorizationService;
use App\Service\EavService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;
use function GuzzleHttp\json_decode;


class EavController extends AbstractController
{

    /**
     * @Route("/eav/docs", name="blog_list")
     */
    public function DocsAction(): Response
    {

        return $this->render('eav/docs.html.twig');
    }

    public function extraAction(?string $id, Request $request, EavService $eavService, AuthorizationService $authorizationService, SerializerService $serializerService): Response
    {
        $offset = strlen('dynamic_eav_');
        $entityName = substr($request->attributes->get('_route'), $offset, strpos($request->attributes->get('_route'), strtolower($request->getMethod())) - 1 - $offset);
        try{
            return $eavService->handleRequest($request, $entityName);
        } catch(AccessDeniedException $exception){
            $contentType = $request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json'));
            if($contentType == '*/*'){
                $contentType = 'application/ld+json';
            }
            return $authorizationService->serializeAccessDeniedException($contentType, $serializerService, $exception);
        }
    }

    public function deleteAction(Request $request, EavService $eavService, AuthorizationService $authorizationService, SerializerService $serializerService): Response
    {
        $entityName = $request->attributes->get("entity");
        try{
            return $eavService->handleRequest($request, $entityName);
        } catch(AccessDeniedException $exception){
            $contentType = $request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json'));
            if($contentType == '*/*'){
                $contentType = 'application/ld+json';
            }
            return $authorizationService->serializeAccessDeniedException($contentType, $serializerService, $exception);
        }
    }

}
