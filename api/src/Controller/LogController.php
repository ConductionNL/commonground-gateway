<?php

// src/Controller/SearchController.php

namespace App\Controller;

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
    /**
     * This function is a wrapper for the cronjob command.
     *
     * @Route("/monologs", methods={"GET"})
     */
    public function crontabAction(Request $request)
    {
        $status = 200;

        $client = new Client($this->getParameter('cache_url'));

        $collection = $client->logs->logs;
        $filter = [];
        $content = json_encode($collection->find($filter)->toArray());

        return new Response($content, $status, ['Content-type' => 'application/json']);
    }
}
