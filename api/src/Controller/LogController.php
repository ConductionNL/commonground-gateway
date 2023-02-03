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

    public function setPagination(&$limit, &$start, array $filters): array
    {
        if (isset($filters['_limit'])) {
            $limit = intval($filters['_limit']);
        } else {
            $limit = 30;
        }
        if (isset($filters['_start']) || isset($filters['_offset'])) {
            $start = isset($filters['_start']) ? intval($filters['_start']) : intval($filters['_offset']);
        } elseif (isset($filters['_page'])) {
            $start = (intval($filters['_page']) - 1) * $limit;
        } else {
            $start = 0;
        }

        return $filters;
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
        $completeFilter = $filter;

        unset($filter['_start'], $filter['_offset'], $filter['_limit'], $filter['_page'],
            $filter['_extend'], $filter['_search'], $filter['_order'], $filter['_fields']);
        $completeFilter = $this->setPagination($limit, $start, $completeFilter);

        $content = json_encode(['results' => $collection->find($filter, ['limit' => $limit, 'skip' => $start])->toArray(), 'page' => intval($completeFilter['_page']), 'count' => $total = $collection->count($filter), 'pages' => floor($total/$limit)]);

        return new Response($content, $status, ['Content-type' => 'application/json']);
    }
}
