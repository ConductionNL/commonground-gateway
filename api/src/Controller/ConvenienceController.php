<?php

namespace App\Controller;

use App\Entity\CollectionEntity;
use App\Service\OasParserService;
use App\Service\PackagesService;
use App\Service\ParseDataService;
use App\Service\PubliccodeService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ConvenienceController extends AbstractController
{
    private PubliccodeService $publiccodeService;
    private EntityManagerInterface $entityManager;
    private OasParserService $oasParser;
    private SerializerInterface $serializer;
    private ParseDataService $dataService;
    private PackagesService $packagesService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface $params,
        SerializerInterface $serializer,
        ParseDataService $dataService
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->oasParser = new OasParserService($entityManager);
        $this->publiccodeService = new PubliccodeService($entityManager, $params, $serializer);
        $this->packagesService = new PackagesService();
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
        } elseif (!$collection->getLocationOAS()) {
            return new Response($this->serializer->serialize(['message' => 'No location OAS found for given collection'], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // Persist OAS to objects and load data if the user has asked for that
        $collection = $this->oasParser->parseOas($collection);
        $collection->getLoadTestData() ? $this->dataService->loadData($collection->getTestDataLocation(), $collection->getLocationOAS()) : null;

        $collection = $this->entityManager->getRepository('App:CollectionEntity')->find($collectionId);
        $collection->setSyncedAt(new \DateTime("now"));
        $this->entityManager->persist($collection);
        $this->entityManager->flush();

        return new Response(
            $this->serializer->serialize(['message' => 'Configuration succesfully loaded from: ' . $collection->getLocationOAS()], 'json'),
            Response::HTTP_OK,
            ['content-type' => 'json']
        );
    }

    /**
     * @Route("/admin/entity-crud-endpoint/{id}")
     */
    public function getEntityCrudEndpoint(string $id): Response
    {
        $entity = $this->entityManager->getRepository('App:Entity')->find($id);

        if (!$entity) {
            return new Response(
                $this->serializer->serialize(['message' => 'No entity found with id: '.$id], 'json'),
                Response::HTTP_NOT_FOUND,
                ['content-type' => 'json']
            );
        }

        $methods = [
            'hasGETCollection' => false,
            'hasPOST'          => false,
            'hasGETItem'       => false,
            'hasPUT'           => false,
        ];

        $crudEndpoint = null;
        $isValid = true;

        foreach ($entity->getHandlers() as $handler) {
            if ($handler->getEndpoints()) {
                foreach ($handler->getEndpoints() as $endpoint) {
                    switch ($endpoint->getMethod()) {
                        case 'get':
                            if ($endpoint->getOperationType() == 'collection') {
                                $methods['hasGETCollection'] = true;
                                $crudEndpoint = '';
                                foreach ($endpoint->getPath() as $key => $path) {
                                    $crudEndpoint .= $key < 1 ? $path : '/'.$path;
                                }
                                break;
                            }
                            $endpoint->getOperationType() == 'item' && $methods['hasGETItem'] = true;
                            break;
                        case 'post':
                            $methods['hasPOST'] = true;
                            break;
                        case 'put':
                            $methods['hasPUT'] = true;
                            break;
                    }
                }
            }
        }

        if (!$crudEndpoint) {
            $isValid = false;
        } else {
            foreach ($methods as $method) {
                $method === false && $isValid = false;
                break;
            }
        }

        return new Response(
            $this->serializer->serialize(['endpoint' => $isValid === false ? $isValid : $crudEndpoint], 'json'),
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
        return $this->publiccodeService->discoverGithub();
    }

    /**
     * @Route("/admin/publiccode/github/{id}")
     *
     * @throws GuzzleException
     */
    public function getGithubRepository(string $id): Response
    {
        return $this->publiccodeService->getGithubRepositoryContent($id);
    }

    /**
     * @Route("/admin/publiccode/github/install/{id}")
     *
     * @throws GuzzleException
     */
    public function installRepository(string $id): Response
    {
        return $this->publiccodeService->createCollection($id);
    }

    /**
     * @Route("/admin/packages")
     *
     * @throws GuzzleException
     */
    public function getPackages(): Response
    {
        return $this->packagesService->discoverPackagist();
    }

    /**
     * @Route("/admin/packages/packagist")
     *
     * @throws GuzzleException
     */
    public function getPackagistPackage(Request $request): Response
    {
        return $this->packagesService->getPackagistPackageContent($request);
    }
}
