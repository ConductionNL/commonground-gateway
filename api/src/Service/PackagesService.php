<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PackagesService
{
    private Client $packagistList;
    private Client $packagistRepo;

    public function __construct()
    {
        $this->packagistList = new Client(['base_uri' => 'https://packagist.org/', 'headers' => ['User-Agent' => 'info@conduction.nl']]);
        $this->packagistRepo = new Client(['base_uri' => 'https://repo.packagist.org/', 'headers' => ['User-Agent' => 'info@conduction.nl']]);
    }

    /**
     * This function gets the content of a specific package.
     *
     * @param Request $request
     *
     * @throws GuzzleException
     *
     * @return Response
     */
    public function getPackagistPackageContent(Request $request): Response
    {
        $name = $request->get('name');
        $response = $this->packagistRepo->request('GET', '/p2/'.$name.'.json');

        return new Response($response->getBody()->getContents(), 200, ['content-type'=>'json']);
    }

    /**
     * This function is searching for packages containing the 'common-gateway' tag.
     *
     * @throws GuzzleException
     *
     * @return Response
     */
    public function discoverPackagist(): Response
    {
        try {
            $response = $this->packagistList->request('GET', '/search.json?q=common-gateway');
        } catch (ClientException $exception) {
            return new Response(
                $exception->getMessage(),
                $exception->getCode(),
                ['content-type' => 'json']
            );
        }

        return new Response($response->getBody()->getContents(), 200, ['content-type'=>'json']);
    }
}
