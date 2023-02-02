<?php

// src/Controller/SearchController.php

namespace App\Controller;

use CommonGateway\CoreBundle\Service\RequestService;
use MongoDB\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Fires the cronjon service from an api endpoint.
 *
 *
 * @Route("admin")
 */
class LogController extends AbstractController
{

    private function realRequestQueryAll(string $method = 'get', ?string $queryString = ''): array
    {
        $vars = [];
        if (strtolower($method) === 'get' && empty($queryString)) {
            return $vars;
        }

        $pairs = explode('&', $_SERVER['QUERY_STRING']);
        foreach ($pairs as $pair) {
            $nv = explode('=', $pair);
            $name = urldecode($nv[0]);
            $value = '';
            if (count($nv) == 2) {
                $value = urldecode($nv[1]);
            }

            $this->recursiveRequestQueryKey($vars, $name, explode('[', $name)[0], $value);
        }

        return $vars;
    }

    private function recursiveRequestQueryKey(array &$vars, string $name, string $nameKey, string $value)
    {
        $matchesCount = preg_match('/(\[[^[\]]*])/', $name, $matches);
        if ($matchesCount > 0) {
            $key = $matches[0];
            $name = str_replace($key, '', $name);
            $key = trim($key, '[]');
            if (!empty($key)) {
                $vars[$nameKey] = $vars[$nameKey] ?? [];
                $this->recursiveRequestQueryKey($vars[$nameKey], $name, $key, $value);
            } else {
                $vars[$nameKey][] = $value;
            }
        } else {
            $vars[$nameKey] = $value;
        }
    }

    /**
     * This function is a wrapper for the cronjob command.
     *
     * @Route("/monologs", methods={"GET"})
     */
    public function logAction(Request $request, RequestService $requestService)
    {
        $status = 200;

        $client = new Client($this->getParameter('cache_url'));

        $collection = $client->logs->logs;
        $filter = $this->realRequestQueryAll('get', $request->getQueryString());
        $content = json_encode($collection->find($filter)->toArray());

        return new Response($content, $status, ['Content-type' => 'application/json']);
    }
}
