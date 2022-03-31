<?php

namespace App\Controller;

use App\Entity\CollectionEntity;
use App\Service\OasParserService;
use App\Service\ParseDataService;
use App\Service\PubliccodeService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

class ConvenienceController extends AbstractController
{
    private PubliccodeService $publiccodeService;
    private EntityManagerInterface $entityManager;
    private OasParserService $oasParser;
    private SerializerInterface $serializer;
    private ParseDataService $dataService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        PubliccodeService $publiccodeService,
        ParseDataService $dataService
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->oasParser = new OasParserService($entityManager);
        $this->publiccodeService = $publiccodeService;
        $this->dataService = $dataService;
    }

    /**
     * @Route("/admin/load/{collectionId}", name="dynamic_route_load_type")
     */
    public function loadAction(Request $request, string $collectionId): Response
    {
        // Get CollectionEntity to retrieve OAS from
        $collection = $this->entityManager->getRepository('App:CollectionEntity')->find($collectionId);

        // Check if collection is egligible to load
        if (!isset($collection) || !$collection instanceof CollectionEntity) {
            return new Response($this->serializer->serialize(['message' => 'No collection found with given id: '.$collectionId], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        } elseif ($collection->getSyncedAt() !== null) {
            return new Response($this->serializer->serialize(['message' => 'This collection has already been loaded, syncing again is not yet supported'], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        } elseif ($collection->getLocationOAS()) {
            return new Response($this->serializer->serialize(['message' => 'No location OAS found for given collection'], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // Persist OAS to objects and load data if the user has asked for that
        $collection = $this->oasParser->parseOas($collection);
        $collection->getLoadTestData() ? $this->dataService->loadData($collection->getTestDataLocation(), $collection->getLocationOAS()) : null;

        return new Response(
            $this->serializer->serialize(['message' => 'Configuration succesfully loaded from: '.$collection->getLocationOAS()], 'json'),
            Response::HTTP_OK,
            ['content-type' => 'json']
        );
    }

    /**
     * @Route("/admin/publiccode")
     *
     * @throws GuzzleException
     */
    public function getRepositories(): Response
    {
        return new Response($this->publiccodeService->discoverGithub(), 200, ['content-type' => 'json']);
    }

    /**
     * @Route("/admin/publiccode/github/{id}")
     *
     * @throws GuzzleException
     */
    public function getGithubRepository(string $id): Response
    {
        return new Response(json_encode($this->publiccodeService->getGithubRepositoryContent($id)), 200, ['content-type' => 'json']);
    }

    /**
     * @Route("/admin/publiccode/github/install/{id}")
     *
     * @throws GuzzleException
     */
    public function installRepository(string $id): Response
    {
        return new Response(
            $this->serializer->serialize(
                ['message' => 'Repository: '.$id.' successfully created into a '.'Collection with id: '.$this->publiccodeService->createCollection($id)],
                'json'
            ),
            200,
            ['content-type' => 'json']
        );
    }
}
