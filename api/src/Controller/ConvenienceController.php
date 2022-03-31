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
        OasParserService $oasParser,
        PubliccodeService $publiccodeService,
        ParseDataService $dataService
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->oasParser = $oasParser;
        $this->publiccodeService = $publiccodeService;
        $this->dataService = $dataService;
    }

    /**
     * @Route("/admin/load/{collectionId}", name="dynamic_route_load_type")
     */
    public function loadAction(Request $request, string $collectionId): Response
    {
        // Get CollectionEntity to retrieve OAS from
        $collectionRepo = $this->entityManager->getRepository('App:CollectionEntity');
        $collection = $collectionRepo->find($collectionId);

        // Check collection, if not found throw error
        if (!isset($collection)) {
            return new Response($this->serializer->serialize(['message' => 'No collection found with given id: '.$collectionId], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // Check if not loaded/synced before
        if ($collection->getSyncedAt() !== null) {
            return new Response($this->serializer->serialize(['message' => 'This collection has already been loaded, syncing again is not yet supported'], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // Check collection->source->locationOAS and set url
        $collection->getLocationOAS() !== null && $url = $collection->getLocationOAS();

        // Check url, if not set throw error
        if (!isset($url)) {
            return new Response($this->serializer->serialize(['message' => 'No location OAS found for given collection'], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // Send GET to fetch redoc
        $client = new Client();
        $response = $client->get($url);
        $body = $response->getBody()->getContents();
        $redoc = Yaml::parse($body);

        // try {
        // Persist yaml to objects
        $this->oasParser->parseOas($redoc, $collection);
        // } catch (\Exception $e) {
        // return new Response(
        //         $this->serializer->serialize(['message' => $e->getMessage()], 'json'),
        //         Response::HTTP_BAD_REQUEST,
        //         ['content-type' => 'json']
        //     );
        // }

        // Set synced at
        $collection->setSyncedAt(new \DateTime('now'));
        $this->entityManager->persist($collection);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->dataService->loadData($collection->getTestDataLocation(), $collection->getLocationOAS());

        return new Response(
            $this->serializer->serialize(['message' => 'Configuration succesfully loaded from: '.$url], 'json'),
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
