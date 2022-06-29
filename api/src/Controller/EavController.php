<?php

namespace App\Controller;

use App\Service\AuthorizationService;
use App\Service\EavService;
use App\Service\OasDocumentationService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;

class EavController extends AbstractController
{
    private SerializerInterface $serializer;

    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface $params,
        SerializerInterface $serializer
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/openapi.{extension}")
     */
    public function OasAction(OasDocumentationService $oasDocumentationService, CacheInterface $customThingCache, Request $request, string $extension): Response
    {
        /* accept only json an yaml as extensions or throw error */
        if ($extension !== 'yaml' && $extension !== 'json') {
            return new Response(
                $this->serializer->serialize(['message' => 'The extension '.$extension.' is not valid. We only support yaml and json'], 'json'),
                400,
                ['content-type' => 'json']
            );
        }

        /* Get application id from query parameter */
        $application = $request->query->get('application');

//         Let's check the cache
        $item = $customThingCache->getItem('oas_'.base64_encode($application).'_'.$extension);

        if ($item->isHit()) {
            $oas = $item->get();
        } else {
            $oas = $oasDocumentationService->getRenderDocumentation($application !== null ? $application : null);

            if ($extension == 'json') {
                $oas = json_encode($oas);
            } else {
                $oas = Yaml::dump($oas);
            }

            // Let's stuff it into the cache
            $item->set($oas);
            $customThingCache->save($item);
        }

        $response = new Response($oas, 200, [
            'Content-type' => 'application/'.$extension,
        ]);

        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'openapi.'.$extension);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

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

        try {
            return $eavService->handleRequest($request, $entityName);
        } catch (AccessDeniedException $exception) {
            $contentType = $request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json'));
            if ($contentType == '*/*') {
                $contentType = 'application/ld+json';
            }

            return $authorizationService->serializeAccessDeniedException($contentType, $serializerService, $exception);
        }
    }

    public function deleteAction(Request $request, EavService $eavService, AuthorizationService $authorizationService, SerializerService $serializerService): Response
    {
        $entityName = $request->attributes->get('entity');

        try {
            return $eavService->handleRequest($request, $entityName);
        } catch (AccessDeniedException $exception) {
            $contentType = $request->headers->get('Accept', $request->headers->get('accept', 'application/ld+json'));
            if ($contentType == '*/*') {
                $contentType = 'application/ld+json';
            }

            return $authorizationService->serializeAccessDeniedException($contentType, $serializerService, $exception);
        }
    }
}
