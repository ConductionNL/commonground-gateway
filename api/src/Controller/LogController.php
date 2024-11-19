<?php

// src/Controller/SearchController.php

namespace App\Controller;

use CommonGateway\CoreBundle\Service\Cache\MongoDbClient;
use CommonGateway\CoreBundle\Service\Cache\MongoDbCollection;
use CommonGateway\CoreBundle\Service\Cache\PostgresqlClient;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\RequestService;
use DateTime;
use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Driver\Exception\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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

    private string $databaseType = 'mongodb';

    public function __construct(
        private readonly CacheService $cacheService,
        private readonly RequestService $requestService,
        private readonly ParameterBagInterface $parameterBag,

    )
    {
    }

    /**
     * This function is a wrapper for the cronjob command.
     *
     * @Route("/monologs", methods={"GET"})
     */
    public function logAction(Request $request): Response
    {
        $status = 200;

        if (substr($this->parameterBag->get('cache_url', false), offset: 0, length: 5) === 'mongo') {
            $client = new MongoDbClient($this->parameterBag->get('cache_url'), entityManager: $this->entityManager, objectEntityService: $this->objectEntityService, cacheLogger: $this->logger);
            $this->databaseType = 'mongodb';
        }
        if (substr($this->parameterBag->get('cache_url', false), offset: 4, length: 5) === 'pgsql' || substr($this->parameters->get('cache_url', false), offset: 4, length: 4) === 'psql') {
            $client = new PostgresqlClient($this->parameterBag->get('cache_url'));
            $this->databaseType = 'postgresql';
        }
        $filter = $this->requestService->realRequestQueryAll($request->getQueryString());

        $completeFilter = $filter;

        if (isset($filter['_id']) && $this->databaseType === 'mongodb') {
            try {
                $filter['_id'] = new ObjectId($filter['_id']);
            } catch (InvalidArgumentException $exception) {
                $content = json_encode([
                    'message' => 'Invalid _id given, please give a valid 24-character hexadecimal string. '.$exception->getMessage(),
                    'type'    => 'Bad Request',
                    'path'    => '/amdin/monologs',
                    'data'    => ['_id' => $filter['_id']],
                ]);

                return new Response($content, 400, ['Content-type' => 'application/json']);
            }
        }

        unset($filter['_start'], $filter['_offset'], $filter['_limit'], $filter['_page'],
            $filter['_extend'], $filter['_search'], $filter['_order'], $filter['_fields']);

        // 'normal' Filters (not starting with _ )
        foreach ($filter as $key => &$value) {
            // todo: maybe re-use cacheService->handleFilter somehow... ?
            $this->handleFilterArray($key, $value);
        }

        $limit = 30;
        $start = 0;

        $completeFilter = $this->cacheService->setPagination($limit, $start, $completeFilter);

        $order = isset($completeFilter['_order']) === true ? str_replace(['ASC', 'asc', 'DESC', 'desc'], [1, 1, -1, -1], $completeFilter['_order']) : [];
        !empty($order) && $order[array_keys($order)[0]] = (int) $order[array_keys($order)[0]];

        $collection = $client->logs->logs;

        if($collection instanceof MongoDbCollection === true) {
            $results = $collection->find($filter, ['limit' => $limit, 'skip' => $start, 'sort' => $order])->toArray();
            $total = $collection->count($filter);
        } else {
            $results = $collection->find(filter: $completeFilter);
            $total = $collection->count(filter: $filter);

            $results = array_map(
                function ($value) {
                    $value['datetime'] = ['$date' => ['$numberLong' => (new DateTime($value['datetime']))->format('Uv')]];
                    $value['_id'] = ['$oid' => $value['_id']];
                    return $value;
                },
                iterator_to_array($results)
            );
        }

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
                $newValue = null;
                // Compare datetime
                if (!empty(array_intersect_key($value, array_flip(['after', 'strictly_after'])))) {
                    $after = array_key_exists('strictly_after', $value) ? 'strictly_after' : 'after';
                    $compareDate = new DateTime($value[$after]);
                    $compareKey = $after === 'strictly_after' ? '$gt' : '$gte';

                    // Todo: re-use the CacheService code to do this, but add in someway an option for comparing string datetime or mongoDB datetime.
                    // $newValue["$compareKey"] = "{$compareDate->format('c')}";
                    $newValue["$compareKey"] = new UTCDateTime($compareDate);
                }
                if (!empty(array_intersect_key($value, array_flip(['before', 'strictly_before'])))) {
                    $before = array_key_exists('strictly_before', $value) ? 'strictly_before' : 'before';
                    $compareDate = new DateTime($value[$before]);
                    $compareKey = $before === 'strictly_before' ? '$lt' : '$lte';

                    // Todo: re-use the CacheService code to do this, but add in someway an option for comparing string datetime or mongoDB datetime.
                    // $newValue["$compareKey"] = "{$compareDate->format('c')}";
                    $newValue["$compareKey"] = new UTCDateTime($compareDate);
                }

                $value = $newValue;

                return true;
            }
        }

        return false;
    }
}
