<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PubliccodeService
{
    private ParameterBagInterface $params;

    public function __construct(
        ParameterBagInterface $params
    ) {
        $this->params = $params;
        $this->github = new Client(['base_uri' => 'https://api.github.com/', 'headers'=>['Authorization'=>'Bearer '.$this->params->get('github_key')]]);
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
}
