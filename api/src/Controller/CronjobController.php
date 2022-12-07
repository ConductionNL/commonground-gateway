<?php

// src/Controller/SearchController.php

namespace App\Controller;

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
 * @Route("cronjob")
 */
class CronjobController extends AbstractController
{
    /**
     * This function is a wrapper for the cronjob command.
     *
     * @Route("/", methods={"GET"})
     */
    public function installedAction(Request $request)
    {
        $status = 200;

        // Start the procces
        $process = new Process(['bin/console','cronjob:command']);
        $process->setWorkingDirectory('/srv/api');
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            //throw new ProcessFailedException($process);
            //var_dump('error');
            $content = $process->getErrorOutput();
        } else {
            $content = $process->getOutput();
        }

        return new Response($content, $status, ['Content-type' => 'application/text']);
    }
}
