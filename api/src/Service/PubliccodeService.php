<?php

namespace App\Service;

use App\Entity\CollectionEntity;
use Doctrine\ORM\EntityManagerInterface;
use EasyRdf\Literal\Date;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class PubliccodeService
{
    private EntityManagerInterface $entityManager;
    private ParameterBagInterface $params;

    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface $params
    ) {
        $this->entityManager = $entityManager;
        $this->params = $params;
        $this->github = $this->params->get('github_key') ? new Client(['base_uri' => 'https://api.github.com/', 'headers'=>['Authorization'=>'Bearer '.$this->params->get('github_key')]]) : null;
    }

    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @throws GuzzleException
     *
     * @return string
     */
    public function discoverGithub(): string
    {
        if (!$this->github) {
            return new Response(
                'Missing github_key in env',
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'json']
            );
        }
        $query = [
            'page'    => 1,
            'per_page'=> 100,
            'order'   => 'desc',
            'sort'    => 'author-date',
            'q'       => 'publiccode in:path path:/  extension:yaml', // so we are looking for a yaml file called public code based in the repo root
        ];

        $response = $this->github->request('GET', '/search/code', ['query' => $query]);

        return $response->getBody()->getContents();
    }

    // Lets get the content of a public github file
    public function getGithubRepositoryContent(string $id): string
    {
        if (!$this->github) {
            return new Response(
                'Missing github_key in env',
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'json']
            );
        }
        // Get repository on github -> via repo id
        $response = $this->github->request('GET', 'https://api.github.com/repositories/'.$id);

        return $response->getBody()->getContents();
    }

    // creates a collection entity
    public function createCollection(string $id)
    {
        if (!$this->github) {
            return new Response(
                'Missing github_key in env',
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'json']
            );
        }

        // Get repository on github -> via id
        $repository = json_decode($this->getGithubRepositoryContent($id), true);

        // Lets look for an publiccode.yaml / yml
        $publiccode = $this->getGithubFileContent($repository, 'publiccode.yml');
        if(!$publiccode){
            $publiccode = $this->getGithubFileContent($repository, 'publiccode.yaml');
        }

        // Lets parse the public code yaml/yml
        try {
            $publiccode = Yaml::parse($publiccode);
        } catch (ParseException $exception) {
            return new Response(
                $exception,
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'json']
            );
        }

        $collection = new CollectionEntity();

        $collection->setName($repository['name']);
        $collection->setDescription($repository['description']);
        $collection->setSourceType('url');
        $collection->setSourceUrl($repository['html_url']);
        $collection->setSourceBranch($repository['default_branch']);
        $collection->setLocationOAS($publiccode['description']['en']['apiDocumentation']);
        $collection->setDateModified(new \DateTime());
        $collection->setDateCreated(new \DateTime());

        $this->entityManager->persist($collection);
        $this->entityManager->flush();

        return $collection;
    }

    // Let's get the content of a public github file
    public function getGithubFileContent($repository, $file)
    {
        $path = $this->getRepoPath($repository);
        $client = new Client(['base_uri' => 'https://raw.githubusercontent.com/'.$path.'/'. $repository['default_branch'] .'/', 'http_errors' => false]);

        $response = $client->get($file);

        // Let's see if we can get the file
        if($response->getStatusCode() == 200){
            $result = strval ($response->getBody());
        }
        else{
            return false;
        }

        // Lets grab symbolic links
        if(!substr_compare($result, $file, -strlen($file), strlen($file))){
            return $this->getGithubFileContent($repository, $result);
        }

        return $result;
    }

    public function getRepoPath($repository)
    {
        $parse = parse_url($repository['html_url']);
        $path = $parse['path'];
        $path = str_replace(['.git'], "",$path);

        return rtrim($path, '/');
    }
}
