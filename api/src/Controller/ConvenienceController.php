<?php

namespace App\Controller;

use App\Entity\CollectionEntity;
use App\Exception\GatewayException;
use App\Service\HandlerService;
use App\Service\OasParserService;
use App\Service\ObjectEntityService;
use App\Service\PackagesService;
use App\Service\ParseDataService;
use App\Service\PubliccodeOldService;
use App\Service\PubliccodeService;
use App\Subscriber\ActionSubscriber;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

class ConvenienceController extends AbstractController
{
    private PubliccodeOldService $publiccodeOldService;
    private PubliccodeService $publiccodeService;
    private EntityManagerInterface $entityManager;
    private OasParserService $oasParser;
    private SerializerInterface $serializer;
    private ParseDataService $dataService;
    private PackagesService $packagesService;
    private HandlerService $handlerService;
    private ActionSubscriber $actionSubscriber;
    private ObjectEntityService $objectEntityService;
    private MappingService $mappingService;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface $params,
        SerializerInterface $serializer,
        ParseDataService $dataService,
        HandlerService $handlerService,
        ActionSubscriber $actionSubscriber,
        ObjectEntityService $objectEntityService,
        PubliccodeService $publiccodeService,
        Environment $twig
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->oasParser = new OasParserService($entityManager);
        $this->publiccodeOldService = new PubliccodeOldService($entityManager, $params, $serializer);
        $this->packagesService = new PackagesService();
        $this->dataService = $dataService;
        $this->handlerService = $handlerService;
        $this->actionSubscriber = $actionSubscriber;
        $this->objectEntityService = $objectEntityService;
        $this->publiccodeService = $publiccodeService;
        $this->twig = $twig;
        $this->mappingService = new MappingService($twig);
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

        return new Response(
            $this->serializer->serialize(['message' => 'Configuration succesfully loaded from: '.$collection->getLocationOAS()], 'json'),
            Response::HTTP_OK,
            ['content-type' => 'json']
        );
    }

    /**
     * @Route("/admin/load-testdata/{collectionId}")
     */
    public function loadTestDataAction(Request $request, string $collectionId): Response
    {
        // Get CollectionEntity to retrieve OAS from
        $collection = $this->entityManager->getRepository('App:CollectionEntity')->find($collectionId);

        // Check if collection is egligible to update
        if (!isset($collection) || !$collection instanceof CollectionEntity) {
            return new Response($this->serializer->serialize(['message' => 'No collection found with given id: '.$collectionId], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        } elseif ($collection->getSyncedAt() === null) {
            return new Response($this->serializer->serialize(['message' => 'This collection has not been loaded yet'], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        } elseif (!$collection->getTestDataLocation()) {
            return new Response($this->serializer->serialize(['message' => 'No testdata location found for this collection'], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // Load testdata
        $dataLoaded = $this->dataService->loadData($collection->getTestDataLocation(), $collection->getLocationOAS());

        return new Response(
            $this->serializer->serialize(['message' => 'Testdata succesfully loaded from: '.$collection->getTestDataLocation()], 'json'),
            Response::HTTP_OK,
            ['content-type' => 'json']
        );
    }

    /**
     * @Route("/admin/wipe-testdata/{collectionId}")
     */
    public function wipeData(Request $request, string $collectionId): Response
    {
        // Get CollectionEntity to retrieve OAS from
        $collection = $this->entityManager->getRepository('App:CollectionEntity')->find($collectionId);

        // Check if collection is egligible to update
        if (!isset($collection) || !$collection instanceof CollectionEntity) {
            return new Response($this->serializer->serialize(['message' => 'No collection found with given id: '.$collectionId], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // Wipe current data for this collection
        $errors = $this->dataService->wipeDataForCollection($collection);

        return new Response(
            $this->serializer->serialize([
                'message' => 'Testdata wiped for '.$collection->getName(),
                'info'    => [
                    'Found '.count($collection->getEntities()).' Entities for this collection',
                    'Found '.$errors['objectCount'].' Objects for this collection',
                    count($errors['errors']).' errors'.(!count($errors['errors']) ? '!' : ' (failed to delete these objects)'),
                ],
                'errors' => $errors['errors'],
            ], 'json'),
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
     * This function runs an action.
     *
     * @Route("/admin/run_action/{actionId}")
     *
     * @throws GatewayException
     * @throws Exception
     */
    public function runAction(string $actionId): Response
    {
        $action = $this->entityManager->getRepository('App:Action')->find($actionId);

        $contentType = $this->handlerService->getRequestType('content-type');
        $data = $this->handlerService->getDataFromRequest();

        $data = $this->actionSubscriber->runFunction($action, $data, $action->getListens()[0]);

        // throw events
        foreach ($action->getThrows() as $throw) {
            $this->objectEntityService->dispatchEvent('commongateway.action.event', $data, $throw);
        }

        return new Response(
            $this->serializer->serialize(['message' => 'Action '.$action->getName()], $contentType),
            Response::HTTP_OK,
            ['content-type' => $contentType]
        );
    }

    /**
     * @Route("/admin/mappings/{id}/test")
     *
     * @throws GatewayException
     */
    public function getMapping(string $id)
    {
        $contentType = $this->handlerService->getRequestType('content-type');

        if (!$mappingObject = $this->entityManager->getRepository('App:Mapping')->find($id)) {
            return new GatewayException(
                $this->serializer->serialize(['message' => 'There is no mapping found with id '.$id], $contentType),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $requestMappingArray = $this->handlerService->getDataFromRequest();

        $mapping = $this->mappingService->mapping($mappingObject, $requestMappingArray);

        return new Response(json_encode($mapping), 200, ['content-type' => 'json']);
    }

    /**
     * This function gets the event from github if something has changed in a repository.
     *
     * @Route("/github_events")
     *
     * @throws Exception|GuzzleException
     */
    public function githubEvents(Request $request): Response
    {
        if (!$content = json_decode($request->request->get('payload'), true)) {
//            var_dump($content = $this->handlerService->getDataFromRequest());

            return $this->publiccodeService->updateRepositoryWithEventResponse($this->handlerService->getDataFromRequest());
        }

        return $this->publiccodeService->updateRepositoryWithEventResponse($content);
    }

    /**
     * @Route("/admin/publiccode")
     *
     * @throws GuzzleException
     */
    public function getRepositories(): Response
    {
        return $this->publiccodeOldService->discoverGithub();
    }

    /**
     * @Route("/admin/publiccode/github/{id}")
     *
     * @throws GuzzleException
     */
    public function getGithubRepository(string $id): Response
    {
        return $this->publiccodeOldService->getGithubRepositoryContent($id);
    }

    /**
     * @Route("/admin/publiccode/github/install/{id}")
     *
     * @throws GuzzleException
     */
    public function installRepository(string $id): Response
    {
        return $this->publiccodeOldService->createCollection($id);
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
