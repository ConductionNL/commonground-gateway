<?php

namespace App\Controller;

use App\Entity\Attribute;
use App\Entity\CollectionEntity;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Handler;
use App\Entity\Property;
use App\Service\OasParserService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

class ConvenienceController extends AbstractController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        OasParserService $oasParser
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->oasParser = $oasParser;
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

        // Check collection->source->locationOAS and set url
        $collection->getLocationOAS() !== null && $url = $collection->getLocationOAS();

        // Check url, if not set throw error
        if (!isset($url)) {
            return new Response($this->serializer->serialize(['message' => 'No location OAS found for given collection'], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // // Check url as query
        // $request->query->get('url') && $url = $request->query->get('url');
        // !isset($url) && $request->getContent() && $body = json_decode($request->getContent(), true);
        // if (!isset($url) && !isset($body)) return new Response($this->serializer->serialize(['message' => 'No url given in query or body'], 'json'), Response::HTTP_BAD_REQUEST ,['content-type' => 'json']);

        // // Check url in body
        // !isset($url) && isset($body['url']) && $url = $body['url'];
        // if (!isset($url)) return new Response($this->serializer->serialize(['message' => 'No url given in query or body'], 'json'), Response::HTTP_BAD_REQUEST ,['content-type' => 'json']);

        // Send GET to fetch redoc
        $client = new Client();
        $response = $client->get($url);
        $redoc = Yaml::parse($response->getBody()->getContents());

        // try {
            // Persist yaml to objects
            $this->oasParser->parseRedoc($redoc, $collection);
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

        return new Response(
            $this->serializer->serialize(['message' => 'Configuration succesfully loaded from: '.$url], 'json'),
            Response::HTTP_OK,
            ['content-type' => 'json']
        );
    }
}
