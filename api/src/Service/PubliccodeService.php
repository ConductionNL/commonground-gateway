<?php

namespace App\Service;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class PubliccodeService
{
    private ParameterBagInterface $params;

    public function __construct(
        ParameterBagInterface $params
    ) {
        $this->params = $params;
        $this->github =  $this->params->get('github_key') ? new Client(['base_uri' => 'https://api.github.com/', 'headers'=>['Authorization'=>'Bearer '.$this->params->get('github_key')]]) : null;
    }

    /**
     * This function is searching for repositories containing a publiccode.yaml file
     *
     * @return string
     * @throws GuzzleException
     */
    public function discoverGithub(): string
    {
        if (!$this->github) {
            throw new BadRequestException('github_key missing in .env');
        }
        $query = [
            'page' => 1,
            'per_page'=> 100,
            'order'=> 'desc',
            'sort' => 'author-date',
            'q'=>'publiccode in:path path:/  extension:yaml' // so we are looking for a yaml file called public code based in the repo root
        ];

        $response = $this->github->request('GET', '/search/code',  ['query' => $query]);

        return $response->getBody()->getContents();
    }
}
