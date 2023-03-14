<?php

// src/Controller/SearchController.php

namespace App\Controller;

use CommonGateway\CoreBundle\Service\CacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SearchControllerc.
 *
 * Authors: Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 *
 * Route("api/search")
 */
class SearchController extends AbstractController
{
    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Route("/", methods={"GET"}).
     */
    public function installedAction(Request $request)
    {
        $status = 200;

        if ($id = $request->query->get('id', false)) {
            $results = $this->cacheService->getObject($id);
        } else {
            $results = $this->cacheService->searchObjects($request->query->get('search'), $request->query->all());
        }

        return new Response(json_encode($results), $status, ['Content-type' => 'application/json']);
    }
}
