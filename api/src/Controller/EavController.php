<?php

namespace App\Controller;

use App\Service\OasDocumentationService;
use CommonGateway\CoreBundle\Service\OasService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;

class EavController extends AbstractController
{
    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var OasService
     */
    private OasService $oasService;

    /**
     * @param SerializerInterface $serializer The serializer
     * @param OasService          $oasService The OAS service
     */
    public function __construct(
        SerializerInterface $serializer,
        OasService $oasService
    ) {
        $this->serializer = $serializer;
        $this->oasService = $oasService;
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
        $useCache = !($request->query->has('noCache') || $request->query->has('nocache'));

//         Let's check the cache
        $item = $customThingCache->getItem('oas_'.base64_encode($application).'_'.$extension);

        if ($item->isHit() && $useCache) {
            $oas = $item->get();
        } else {
            $oas = $this->oasService->createOas();

            if ($extension == 'json') {
                $oas = json_encode($oas, JSON_UNESCAPED_SLASHES);
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
}
