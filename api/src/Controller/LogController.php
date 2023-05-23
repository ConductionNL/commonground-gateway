<?php

// src/Controller/SearchController.php

namespace App\Controller;

use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\RequestService;
use DateTime;
use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Fires the cronjon service from an api endpoint.
 *
 * Authors: Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 *
 * @Route("admin")
 */
class LogController extends AbstractController
{
    private CacheService $cacheService;
    private RequestService $requestService;

    public function __construct(CacheService $cacheService, RequestService $requestService)
    {
        $this->cacheService = $cacheService;
        $this->requestService = $requestService;
    }

    /**
     * This function is a wrapper for the cronjob command.
     *
     * @Route("/monologs", methods={"GET"})
     */
    public function logAction(Request $request): Response
    {
        $status = 200;
        $client = new Client($this->getParameter('cache_url'));
        $filter = $this->requestService->realRequestQueryAll('get', $request->getQueryString());

        $completeFilter = $filter;

        if (isset($filter['_id'])) {
            $filter['_id'] = new ObjectId($filter['_id']);
        }

        unset($filter['_start'], $filter['_offset'], $filter['_limit'], $filter['_page'],
            $filter['_extend'], $filter['_search'], $filter['_order'], $filter['_fields']);

        // 'normal' Filters (not starting with _ )
        foreach ($filter as $key => &$value) {
            // todo: maybe re-use cacheService->handleFilter somehow... ?
            $this->handleFilterArray($key, $value);
        }

        $completeFilter = $this->cacheService->setPagination($limit, $start, $completeFilter);

        $order = isset($completeFilter['_order']) === true ? str_replace(['ASC', 'asc', 'DESC', 'desc'], [1, 1, -1, -1], $completeFilter['_order']) : [];
        !empty($order) && $order[array_keys($order)[0]] = (int) $order[array_keys($order)[0]];

        $collection = $client->logs->logs;

        $results = $collection->find($filter, ['limit' => $limit, 'skip' => $start, 'sort' => $order])->toArray();
        $total = $collection->countDocuments($filter);

        $content = json_encode($this->cacheService->handleResultPagination($completeFilter, $results, $total));

        return new Response($content, $status, ['Content-type' => 'application/json']);
    }

    /**
     * Handles a single filter used on a get collection api call. Specifically a filter where the value is an array.
     *
     * @param $key
     * @param $value
     *
     * @throws Exception
     *
     * @return bool
     */
    private function handleFilterArray($key, &$value): bool
    {
        // Handle filters that expect $value to be an array
        if (is_array($value)) {
            // after, before, strictly_after,strictly_before
            if (!empty(array_intersect_key($value, array_flip(['after', 'before', 'strictly_after', 'strictly_before'])))) {
                // Compare datetime
                if (!empty(array_intersect_key($value, array_flip(['after', 'strictly_after'])))) {
                    $after = array_key_exists('strictly_after', $value) ? 'strictly_after' : 'after';
                    $compareDate = new DateTime($value[$after]);
                    $compareKey = $after === 'strictly_after' ? '$gt' : '$gte';
                } else {
                    $before = array_key_exists('strictly_before', $value) ? 'strictly_before' : 'before';
                    $compareDate = new DateTime($value[$before]);
                    $compareKey = $before === 'strictly_before' ? '$lt' : '$lte';
                }

                // Todo: re-use the CacheService code to do this, but add in someway an option for comparing string datetime and mongoDB datetime.
                // In CoreBundle CacheService we do this instead:
//                $value = ["$compareKey" => "{$compareDate->format('c')}"];
                $value = ["$compareKey" => new UTCDateTime($compareDate)];

                return true;
            }
        }

        return false;
    }
}
