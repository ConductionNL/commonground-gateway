<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class GithubApiService
{
    private ParameterBagInterface $parameterBag;
    private ?Client $github;
    private ?Client $githubusercontent;

    public function __construct(
        ParameterBagInterface $parameterBag
    ) {
        $this->parameterBag = $parameterBag;
        $this->github = $this->parameterBag->get('github_key') ? new Client(['base_uri' => 'https://api.github.com/', 'headers' => ['Authorization' => 'Bearer '.$this->parameterBag->get('github_key')]]) : null;
        $this->githubusercontent = new Client(['base_uri' => 'https://raw.githubusercontent.com/']);
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
     * This function gets the content of the given url.
     *
     * @param string      $url
     * @param string|null $path
     *
     * @throws GuzzleException
     *
     * @return array|null
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
     * This function gets all the github repository details.
     *
     * @param array $item a repository from github with a publicclode.yaml file
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function getGithubRepositoryInfo(array $item): array
    {
        return [
            'source'                  => 'github',
            'name'                    => $item['name'],
            'url'                     => $item['html_url'],
            'avatar_url'              => $item['owner']['avatar_url'],
            'last_change'             => $item['updated_at'],
            'stars'                   => $item['stargazers_count'],
            'fork_count'              => $item['forks_count'],
            'issue_open_count'        => $item['open_issues_count'],
            //            'merge_request_open_count'   => $this->requestFromUrl($item['merge_request_open_count']),
            'programming_languages'   => $this->requestFromUrl($item['languages_url']),
            'organisation'            => $item['owner']['type'] === 'Organization' ? $this->getGithubOwnerInfo($item) : null,
            //            'topics' => $this->requestFromUrl($item['topics'], '{/name}'),
            //                'related_apis' => //
        ];
    }

    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @param string $slug
     *
     * @throws GuzzleException
     *
     * @return array|null|Response
     */
    public function getRepositoryFromUrl(string $slug)
    {
        if ($this->checkGithubKey()) {
            return $this->checkGithubKey();
        }

        try {
            $response = $this->github->request('GET', 'repos/'.$slug);
        } catch (ClientException $exception) {
            var_dump($exception->getMessage());

            return null;
        }

        $response = json_decode($response->getBody()->getContents(), true);

        return $this->getGithubRepositoryInfo($response);
    }

    public function getRepositoryFileFromUrl(string $url): array
    {
        return [];
    }

    /**
     * This function gets the content of the given url.
     *
     * @param string      $url
     * @param string|null $path
     *
     * @throws GuzzleException
     *
     * @return array|null
     */
    public function getGithubOwnerRepositories(string $url, ?string $path = null): ?array
    {
        if ($path !== null) {
            $parse = parse_url($url);
            $url = str_replace([$path], '', $parse['path']);
        }

        if ($response = $this->github->request('GET', $url)) {
            $responses = json_decode($response->getBody()->getContents(), true);

            $urls = [];
            foreach ($responses as $item) {
                $urls[] = $item['html_url'];
            }

            return $urls;
        }

        return null;
    }

    /**
     * This function gets the github owner details.
     *
     * @param array $item a repository from github
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function getGithubOwnerInfo(array $item): array
    {
        return [
            'id'          => $item['owner']['id'],
            'name'        => $item['owner']['login'],
            'description' => null,
            'logo'        => $item['owner']['avatar_url'] ?? null,
            'owns'        => $this->getGithubOwnerRepositories($item['owner']['repos_url']),
            'token'       => null,
            'github'      => $item['owner']['html_url'] ?? null,
            'website'     => null,
            'phone'       => null,
            'email'       => null,
        ];
    }

    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @param string $slug
     *
     * @throws GuzzleException
     *
     * @return array|null|Response
     */
    public function getGithubRepoFromOrganization(string $organizationName): ?array
    {
        if ($this->checkGithubKey()) {
            return $this->checkGithubKey();
        }

        try {
            $response = $this->github->request('GET', 'repos/'.$organizationName.'/.github');
        } catch (ClientException $exception) {
            var_dump($exception->getMessage());

            return null;
        }

        return Yaml::parse($response->getBody()->getContents());
    }

    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @param string $slug
     *
     * @throws GuzzleException
     *
     * @return array|null|Response
     */
    public function getOpenCatalogiFromGithubRepo(string $organizationName): ?array
    {
        if ($this->checkGithubKey()) {
            return $this->checkGithubKey();
        }

        try {
            $response = $this->githubusercontent->request('GET', $organizationName.'/.github/main/openCatalogi.yaml');
        } catch (ClientException $exception) {
            var_dump($exception->getMessage());

            return null;
        }

        return Yaml::parse($response->getBody()->getContents());
    }

    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @param string $organizationName
     * @param string $repositoryName
     * @return array|null
     * @throws GuzzleException
     */
    public function getPubliccodeForGithubEvent(string $organizationName, string $repositoryName): ?array
    {
        $response = null;
        try {
            $response = $this->githubusercontent->request('GET', $organizationName.'/'.$repositoryName.'/main/publiccode.yaml');
        } catch (ClientException $exception) {
            var_dump($exception->getMessage());
        }

        if ($response == null) {
            try {
                $response = $this->githubusercontent->request('GET', $organizationName.'/'.$repositoryName.'/master/publiccode.yaml');
            } catch (ClientException $exception) {
                var_dump($exception->getMessage());

                return null;
            }
        }

        return Yaml::parse($response->getBody()->getContents());
    }

    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @param string $url
     *
     * @throws GuzzleException
     *
     * @return array|null|Response
     */
    public function getPubliccode(string $url)
    {
        $parseUrl = parse_url($url);
        $code = explode('/blob/', $parseUrl['path']);

        try {
            $response = $this->githubusercontent->request('GET', $code[0].'/'.$code[1]);
        } catch (ClientException $exception) {
            var_dump($exception->getMessage());

            return new Response(
                $exception,
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'json']
            );
        }

        return Yaml::parse($response->getBody()->getContents());
    }

    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @param string $slug
     *
     * @throws GuzzleException
     *
     * @return array|null|Response
     */
    public function checkPublicRepository(string $slug)
    {
        if ($this->checkGithubKey()) {
            return $this->checkGithubKey();
        }

        try {
            $response = $this->github->request('GET', 'repos/'.$slug);
        } catch (ClientException $exception) {
            return new Response(
                $exception,
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'json']
            );
        }

        $response = json_decode($response->getBody()->getContents(), true);

        return $response['private'];
    }
}
