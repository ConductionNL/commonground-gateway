<?php

namespace App\Service;

use App\Entity\CollectionEntity;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use phpDocumentor\Reflection\Types\This;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class PublicCodeApiService
{
//    private EntityManagerInterface $entityManager;
//    private ParameterBagInterface $params;
    private array $query;

    public function __construct(
//        EntityManagerInterface $entityManager,
//        ParameterBagInterface $params,
    ) {
//        $this->entityManager = $entityManager;
//        $this->params = $params;
        $this->query = [
            'rowsPerPage'    => 10000,
            'page' => 1,
        ];
    }

    /**
     * This function gets all the github repository details.
     *
     * @param array $item a repository from github with a publicclode.yaml file
     * @param $type
     * @return array
     */
    public function createArray(array $item, $type): array
    {
        return [
            'id'          => $item['id'],
            'name'        => $item['name'],
            'url' => $type === 'repository' ? $item['url'] : $item['repositoryUrl'],
//            'applicationSuite'   => 'Organization' ? $this->getGithubOwnerInfo($item) : null, //object id, name
//            'landingURL'    => $item['html_url'], // ?
//            'isBasedOn'     => $item['private'], // ?
//            'softwareVersion'       => $item['owner']['type'], // ?
        ];
    }

    /**
     * This function retrieves the developer.overheid.nl repositories
     *
     * @throws GuzzleException
     *
     * @return Response
     */
    public function createPropertiesArray(): Response
    {
        $repositories = $this->getRepositoryList();
        foreach ($repositories['results'] as $repository) {
            $propertiesArray[] = [
                'properties' => $this->createArray($repository, 'repository')
            ];
        }

        $components = $this->getComponentList();
        foreach ($components as $component) {
            $propertiesArray[] = [
                'properties' => $this->createArray($component, 'component')
            ];
        }

        return new Response(json_encode($propertiesArray), 200, ['content-type'=>'json']);
    }

    /**
     * This function retrieves the developer.overheid.nl repositories
     *
     * @return array
     * @throws GuzzleException
     */
    public function getRepositoryList(): array
    {
        $client = new Client(['base_uri' => 'https://developer.overheid.nl', 'http_errors' => false]);
        $response = $client->request('GET', '/api/repositories', ['query' => $this->query]);
        $repositories = $response->getBody()->getContents();

       return json_decode($repositories, true);
    }

    /**
     * This function retrieves the componentencatalogus.commonground.nl repositories
     *
     * @return array
     * @throws GuzzleException
     */
    public function getComponentList(): array
    {
        $client = new Client(['base_uri' => 'https://componentencatalogus.commonground.nl', 'http_errors' => false]);
        $response = $client->request('GET', '/api/components', ['query' => $this->query]);
        $components = $response->getBody()->getContents();

        return json_decode($components, true);
    }
}
