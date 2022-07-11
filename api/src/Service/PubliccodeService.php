<?php

namespace App\Service;

use App\Entity\CollectionEntity;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use phpDocumentor\Reflection\Types\Collection;
use phpDocumentor\Reflection\Types\This;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class PubliccodeService
{
    private EntityManagerInterface $entityManager;
    private ParameterBagInterface $params;
    private ?Client $github;
    private array $query;
    private SerializerInterface $serializer;
    private ResponseService $responseService;
    private EavService $eavService;
    private ObjectEntityService $objectEntityService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface  $params,
        SerializerInterface    $serializer,
        ResponseService        $responseService,
        EavService             $eavService,
        ObjectEntityService    $objectEntityService
    )
    {
        $this->entityManager = $entityManager;
        $this->params = $params;
        $this->serializer = $serializer;
        $this->responseService = $responseService;
        $this->eavService = $eavService;
        $this->objectEntityService = $objectEntityService;
        $this->github = $this->params->get('github_key') ? new Client(['base_uri' => 'https://api.github.com/', 'headers' => ['Authorization' => 'Bearer ' . $this->params->get('github_key')]]) : null;
        $this->query = [
            'page' => 1,
            'per_page' => 200,
            'order' => 'desc',
            'sort' => 'author-date',
            'q' => 'publiccode in:path path:/  extension:yaml', // so we are looking for a yaml file called publiccode based in the repo root
        ]; // @todo get new query params for a better result
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * This function gets the content of the given url.
     *
     * @param string $url
     * @param string|null $path
     *
     * @return array|null
     * @throws GuzzleException
     *
     */
    public function requestFromUrl(string $url, ?string $path = null): ?array
    {
        if ($path !== null) {
            $parse = parse_url($url);
            $url = str_replace([$path], '', $parse['path']);
        }

        if ($response = $this->github->request('GET', $url)) {
            return json_decode($response->getBody()->getContents(), true);
        }

        return null;
    }

    /**
     * This function gets the content of a github file.
     *
     * @param array $repository a github repository
     * @param string $file the file that we want to search
     *
     * @return string|null
     * @throws GuzzleException
     *
     */
    public function getGithubFileContent(array $repository, string $file): ?string
    {
        $path = $this->getRepoPath($repository['html_url']);
        $client = new Client(['base_uri' => 'https://raw.githubusercontent.com/' . $path . '/main/', 'http_errors' => false]);
        $response = $client->get($file);

        if ($response->getStatusCode() == 200) {
            $result = strval($response->getBody());
        } else {
            return null;
        }

        if (!substr_compare($result, $file, -strlen($file), strlen($file))) {
            return $this->getGithubFileContent($repository, $result);
        }

        return $result;
    }

    /**
     * This function finds a publiccode yaml file in a repository.
     *
     * @param array $repository a github repository
     *
     * @return array|null
     * @throws GuzzleException
     *
     */
    public function findPubliccode(array $repository): ?array
    {
        $publiccode = $this->getGithubFileContent($repository, 'publiccode.yml');

        if (!$publiccode) {
            $publiccode = $this->getGithubFileContent($repository, 'publiccode.yaml');
        }

        // Lets parse the public code yaml/yml
        try {
            $publiccode ? $publiccode = Yaml::parse($publiccode) : $publiccode = null;
        } catch (ParseException $exception) {
            return null;
        }

        return $publiccode;
    }

    /**
     * This function gets the path of a github repository.
     *
     * @param string $html_url a github repository html_url
     *
     * @return string
     */
    public function getRepoPath(string $html_url): string
    {
        $parse = parse_url($html_url);
        $path = explode('/blob', $parse['path']);
        return array_shift($path);
    }

    /**
     * This function check if the github key is provided.
     *
     * @return Response|null
     */
    public function checkGithubKey(): ?Response
    {
        if (!$this->github) {
            return new Response(
                'Missing github_key in env',
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'json']
            );
        }

        return null;
    }

    /**
     * This function creates a Collection from a github repository.
     *
     * @param string $id id of the github repository
     *
     * @return Response Uuid of created Collection
     * @throws GuzzleException
     *
     */
    public function createCollection(string $id): Response
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Component']);
        $object = $this->objectEntityService->getObject($entity, $id);

        $collection = new CollectionEntity();
        $collection->setName($object['name']);
        $collection->setDescription($object['description'] ?? null);
        $collection->setSourceType('url');
        $collection->setSourceUrl($object['url'] ?? null);

        // @todo:
//        $collection->setSourceBranch($object['default_branch']);
//        $collection->setLocationOAS($publiccode['description']['en']['apiDocumentation'] ?? null);
//        $collection->setTestDataLocation($publiccode['description']['en']['testDataLocation'] ?? null);

        $this->entityManager->persist($collection);
        $this->entityManager->flush();

        return new Response(
            $this->serializer->serialize(
                ['message' => 'Repository: ' . $id . ' successfully created into a ' . 'Collection with id: ' . $collection->getId()->toString()],
                'json'
            ),
            200,
            ['content-type' => 'json']
        );
    }

    /**
     * This function gets the content of a specific repository.
     *
     * @param string $id
     *
     * @return Response
     * @throws GuzzleException|\Exception
     *
     */
    public function getGithubRepositoryContent(string $id): Response
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Component']);
        $object = $this->objectEntityService->getObject($entity, $id);

        return new Response(json_encode($object), 200, ['content-type' => 'json']);
    }

    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @return Response
     * @throws GuzzleException
     *
     */
    public function discoverGithub()
    {
        if ($this->checkGithubKey()) {
            return $this->checkGithubKey();
        }

        try {
            $response = $this->github->request('GET', '/search/code', ['query' => $this->query]);
        } catch (ClientException $exception) {
            return new Response(
                $exception,
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'json']
            );
        }

        $repositories = json_decode($response->getBody()->getContents(), true);

        $objectEntities = [];
        foreach ($repositories['items'] as $repository) {
            $publiccode = $originalPubliccode = $this->findPubliccode($repository);
//            var_dump($publiccode);
            $objectEntities[] = $this->findObjectEntity($repository, $publiccode);
        }

        return new Response(
            json_encode($objectEntities),
            200,
            ['content-type' => 'json']
        );
    }

    /**
     * This function returns or creates an ObjectEntity.
     *
     * @param $repository
     * @param $publiccode
     * @return array
     * @throws GuzzleException|\Exception
     */
    public function findObjectEntity($repository, $publiccode): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Component']);
        $publiccode = $this->createComponentObject($repository['repository'], $publiccode);

//        var_dump($schema);die();
        return $this->createOrUpdateObject($entity, $publiccode);
    }

    /**
     * Creates objects related to an entity.
     *
     * @param Entity $entity The entity the object should relate to
     * @param array $schema The data in the object
     *
     * @throws GuzzleException|\Exception
     */
    public function createOrUpdateObject(Entity $entity, array $schema): array
    {
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $entity, 'uri' => isset($publiccode['html_url'])]);

//        if ($objectEntity instanceof ObjectEntity) {
//            $object = $this->eavService->getObject($objectEntity->getId(), 'POST', $entity);
//        } else {
            $object = $this->eavService->getObject(null, 'POST', $entity);
//        }

        $object = $this->objectEntityService->saveObject($object, $schema);
        $this->entityManager->persist($object);
        $this->entityManager->flush();

        return $this->responseService->renderResult($object, null, ['all']);
    }

    /**
     * This function gets all the github repository details.
     *
     * @param array $repository
     * @param $publiccode
     * @return array
     * @throws GuzzleException
     */
    public function createComponentObject(array $repository, &$publiccode): array
    {

//        is_array($repository['name']) && $repository['name']
        // @todo:
        // default_branch
        // locationOAS
        // testDataLocation
        return array_merge($publiccode, [
            'name' => $repository['full_name'],
            'description' => [
                'localisedName' => $publiccode['description']['nl']['genericName'] ?? null,
                'shortDescription' => $publiccode['description']['nl']['shortDescription'] ?? null,
                'longDescription' => $publiccode['description']['nl']['longDescription'] ?? null,
                'documentation' => $publiccode['description']['nl']['documentation'] ?? null,
                'apiDocumentation' => $publiccode['description']['nl']['apiDocumentation'] ?? null,
                'features' => $publiccode['description']['nl']['features'] ?? null,
                'screenshots' => $publiccode['description']['nl']['screenshots'] ?? null,
                'videos' => $publiccode['description']['nl']['videos'] ?? null,
                'awards' => $publiccode['description']['nl']['awards'] ?? null
            ],
            'applicationSuite' => [
                'applicationId' => $repository['id'],
                'name' => $repository['name']
            ],
            'url' => $repository['html_url'],
            'landingURL' => $repository['url'],
            'isBasedOn' => $repository['fork'],
//            'softwareVersion' => '',
            'releaseDate' => $publiccode['releaseDate'], // TBA -> check of het een datetime is anders null
            'logo' => $repository['owner']['avatar_url'] ?? null,
//            'platforms' => $publiccode['platforms'],
//            'categories' => $publiccode['categories'],
            'usedBy' => $this->requestFromUrl($repository['forks_url']),
//            'roadmap' => '',
//            'developmentStatus' => $publiccode['developmentStatus'],
//            'softwareType' => $publiccode['softwareType'],
//            'inputTypes' => $publiccode['inputTypes'],
//            'outputTypes' => $publiccode['outputTypes'],
            'nl' => [
//                'commonground' => [
//                    'intendedOrganizations' => '',
//                    'installationType' => '',
//                    'layerType' => '',
//                ],
                'gemma' => [
                    'bedrijfsfuncties' => [''],
                    'bedrijfsservices' => [''],
                    'applicatiefunctie' => '',
                    'referentieComponenten' => ['']
                ],
                'upl' => [''],
                'apm' => '',
            ],
//            'intendedAudience' => [
//                'countries' => $publiccode['intendedAudience']['countries'],
//                'unsupportedCountries' => $publiccode['intendedAudience']['unsupportedCountries'],
//                'scope' => $publiccode['intendedAudience']['scope']
//            ],
//            'legal' => [
//                'license' => $publiccode['legal']['license'],
//                'mainCopyrightOwner' => $publiccode['legal']['mainCopyrightOwner'],
//                'repoOwner' => $publiccode['legal']['repoOwner'],
//                'authorsFile' => $publiccode['legal']['authorsFile']
//            ],
//            'maintenance' => [
//                'type' => $repository['owner']['type'],
//                'contractors' => [
//                    'name' => $publiccode['contractors']['name'],
//                    'until' => $publiccode['contractors']['until'],
//                    'email' => $publiccode['contractors']['name'],
//                    'website' => $publiccode['contractors']['name'],
//                    'phone' => $publiccode['contractors']['name']
//                ],
//                'contacts' => [
//                    'name' => $publiccode['contacts']['name'],
//                    'email' => $publiccode['contacts']['email'],
//                    'phone' => $publiccode['contacts']['phone'],
//                    'affiliation' => $publiccode['contacts']['affiliation']
//                ],
//            ],
//            'localisation' => [
//                'localisationReady' => $publiccode['localisation']['localisationReady'],
//                'availableLanguages' => $publiccode['localisation']['availableLanguages'],
//            ],
//            'dependsOn' => [
//                'open' => $publiccode['dependsOn']['open'],
//                'proprietary' => '',
//                'hardware' => '',
//            ],
        ]);
    }
}
