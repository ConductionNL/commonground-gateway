<?php

namespace App\Service;

use App\Entity\CollectionEntity;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
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
    private ValidaterService $validaterService;
    private ObjectEntityService $objectEntityService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface  $params,
        SerializerInterface    $serializer,
        ResponseService $responseService,
        EavService             $eavService,
        ValidaterService $validaterService,
        ObjectEntityService $objectEntityService
    )
    {
        $this->entityManager = $entityManager;
        $this->params = $params;
        $this->serializer = $serializer;
        $this->responseService = $responseService;
        $this->eavService = $eavService;
        $this->validaterService = $validaterService;
        $this->objectEntityService = $objectEntityService;
        $this->github = $this->params->get('github_key') ? new Client(['base_uri' => 'https://api.github.com/', 'headers' => ['Authorization' => 'Bearer ' . $this->params->get('github_key')]]) : null;
        $this->query = [
            'page' => 1,
            'per_page' => 1,
            'order' => 'desc',
            'sort' => 'author-date',
            'q' => 'publiccode in:path path:/  extension:yaml', // so we are looking for a yaml file called publiccode based in the repo root
        ];
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * This function gets the github owner details.
     *
     * @param array $item a repository from github with a publicclode.yaml file
     *
     * @return array
     * @throws GuzzleException
     *
     */
    public function getGithubOwnerInfo(array $item): array
    {
        return [
            'id' => $item['owner']['id'],
            'type' => $item['owner']['type'],
            'login' => $item['owner']['login'] ?? null,
            'html_url' => $item['owner']['html_url'] ?? null,
            'organizations_url' => $item['owner']['organizations_url'] ?? null,
            'avatar_url' => $item['owner']['avatar_url'] ?? null,
            'publiccode' => $this->findPubliccode($item),
            'repos' => json_decode($this->getGithubOwnerRepositories($item['owner']['login'])),
        ];
    }

    /**
     * This function gets all the github repository details.
     *
     * @param array $item a repository from github with a publicclode.yaml file
     *
     * @return array
     * @throws GuzzleException
     *
     */
    public function getGithubRepositoryInfo(array $item): array
    {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'full_name' => $item['full_name'],
            'description' => $item['description'],
            'html_url' => $item['html_url'],
            'private' => $item['private'],
            'owner' => $item['owner']['type'] === 'Organization' ? $this->getGithubOwnerInfo($item) : null,
            'tags' => $this->requestFromUrl($item['tags_url']),
            'languages' => $this->requestFromUrl($item['languages_url']),
            'downloads' => $this->requestFromUrl($item['downloads_url']),
            //            'releases'    => $this->requestFromUrl($item['releases_url'], '{/id}'),
            'labels' => $this->requestFromUrl($item['labels_url'], '{/name}'),
        ];
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
     * This function gets all the repositories of the owner.
     *
     * @param string $owner the name of the owner of a repository
     *
     * @return string|false
     * @throws GuzzleException
     *
     */
    public function getGithubOwnerRepositories(string $owner): ?string
    {
        if ($response = $this->github->request('GET', '/orgs/' . $owner . '/repos')) {
            return $response->getBody()->getContents();
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
        $path = str_replace(['.git'], '', $parse['path']);

        return rtrim($path, '/');
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
     * This function gets the content of a specific repository.
     *
     * @param string $id
     *
     * @return Response
     * @throws GuzzleException
     *
     */
    public function getGithubRepositoryContent(string $id): Response
    {
        if ($this->checkGithubKey()) {
            return $this->checkGithubKey();
        }
        $response = $this->github->request('GET', 'https://api.github.com/repositories/' . $id);

        return new Response(json_encode($this->getGithubRepositoryInfo(json_decode($response->getBody()->getContents(), true))), 200, ['content-type' => 'json']);
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
        if ($this->checkGithubKey()) {
            return $this->checkGithubKey();
        }
        $response = $this->github->request('GET', 'https://api.github.com/repositories/' . $id);
        $repository = json_decode($response->getBody()->getContents(), true);
        $publiccode = $this->findPubliccode($repository);

        $collection = new CollectionEntity();
        $collection->setName($repository['name']);
        $collection->setDescription($repository['description']);
        $collection->setSourceType('url');
        $collection->setSourceUrl($repository['html_url']);
        $collection->setSourceBranch($repository['default_branch']);
        $collection->setLocationOAS($publiccode ? $publiccode['description']['en']['apiDocumentation'] : null);
        isset($publiccode['description']['en']['testDataLocation']) && $collection->setTestDataLocation($publiccode['description']['en']['testDataLocation']);

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
            $publiccode = $this->findPubliccode($repository);
            $objectEntities[] = $this->findCollectionEntity($repository, $publiccode);
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
    public function findCollectionEntity($repository, $publiccode)
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Component']);
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findAndCountByEntity($entity, ['uri' => isset($publiccode['html_url'])]);

//        if (count($objectEntity) >= 1) {
////            var_dump($entity->getName());
//            return $objectEntity;
//        } else {

            $schema = $this->createComponentObject($repository['repository'], $publiccode);

//            $object = $this->createObjects($entity, $schema);
//            var_dump($object->getExternalId());die();
            return $this->createObjects($entity, $schema);
//        }
    }

    /**
     * Creates objects related to an entity.
     *
     * @param Entity $entity The entity the object should relate to
     * @param array $schema The data in the object
     *
     * @throws GuzzleException|\Exception
     */
    public function createObjects(Entity $entity, array $schema)
    {
        $object = $this->eavService->getObject(null, 'POST', $entity);
        $object = $this->objectEntityService->saveObject($object, $schema);

        $this->entityManager->persist($object);
        $this->entityManager->flush();

        return $this->responseService->renderResult($object, null, null);
    }

    /**
     * This function gets all the github repository details.
     *
     * @param array $repository
     * @param $publiccode
     * @return array
     */
    public function createComponentObject(array $repository, $publiccode): array
    {
//        var_dump($repository['html_url']);
        return [
            'id' => $repository['id'],
            'name' => $repository['name'],
            'description' => $repository['description'],
            'applicationSuite' => [
                'id' => '',
                'name' => ''
            ],
            'url' => $repository['html_url'],
            'landingURL' => $repository['url'],
            'isBasedOn' => $repository['fork'],
            'softwareVersion' => '',
            'releaseDate' => '',
            'logo' => $repository['owner']['avatar_url'] ?? null,
            'platforms' => '',
            'categories' => '',
            'usedBy' => $this->requestFromUrl($repository['forks_url']),
            'roadmap' => '',
            'developmentStatus' => '',
            'softwareType' => '',
            'legal' => [
                'license' => '',
                'mainCopyrightOwner' => '',
                'repoOwner' => $repository['owner']['html_url'],
                'authorsFile' => ''
            ],
            'maintenance' => [
                'type' => $repository['owner']['type'],
                'contractors' => '',
                'contacts' => ''
            ],
            'localisation' => [
                'localisationReady' => '',
//                'availableLanguages' => $this->requestFromUrl($repository['languages_url']),
            ],
            'dependsOn' => [
                'open' => '',
                'proprietary' => '',
                'hardware' => '',
            ],
            'nl' => [
                'installationType' => '',
                'layerType' => '',
            ]

        ];
    }
}
