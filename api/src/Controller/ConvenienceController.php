<?php

namespace App\Controller;

use App\Service\PubliccodeService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ConvenienceController extends AbstractController
{
    private PubliccodeService $publiccodeService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        PubliccodeService $publiccodeService
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->publiccodeService = $publiccodeService;
    }

    /**
     * @Route("/admin/publiccode", name="dynamic_route_load_type")
     *
     * @throws GuzzleException
     */
    public function getRepositories(): Response
    {
        return new Response($this->publiccodeService->discoverGithub());
    }
}
