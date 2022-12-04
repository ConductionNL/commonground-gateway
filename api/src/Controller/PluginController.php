<?php

// src/Controller/PluginController.php

namespace App\Controller;

use CommonGateway\CoreBundle\Service\ComposerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PluginControllerc.
 *
 *
 * @Route("/admin/plugins")
 */
class PluginController extends AbstractController
{
    private ComposerService $composerService;

    public function __construct(ComposerService $composerService)
    {
        $this->composerService = $composerService;
    }

    /**
     * @Route("/installed", methods={"GET"})
     */
    public function installedAction(Request $request)
    {
        $status = 200;
        $plugins = $this->composerService->getAll(['--installed']);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("/audit", methods={"GET"})
     */
    public function auditAction(Request $request)
    {
        $status = 200;
        $plugins = $this->composerService->audit(['--format=json']);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("/available", methods={"GET"})
     */
    public function availableAction(Request $request)
    {
        $status = 200;

        $search = $request->query->get('search', 'a');

        $plugins = $this->composerService->search($search, ['--type=common-gateway-plugin']);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("/view", methods={"GET"})
     */
    public function viewAction(Request $request)
    {
        $status = 200;

        $packadge = $request->query->get('plugin', 'commongateway/corebundle');

        $plugins = $this->composerService->getSingle($packadge);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("/install", methods={"POST"})
     */
    public function installAction(Request $request)
    {
        $status = 200;

        if (!$packadge = $request->query->get('plugin', false)) {
            return new Response('No plugin provided as query parameters', 400, ['Content-type' => 'application/json']);
        }

        $plugins = $this->composerService->require($packadge);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("/upgrade", methods={"POST"})
     */
    public function upgradeAction(Request $request)
    {
        $status = 200;

        if (!$packadge = $request->query->get('plugin', false)) {
            return new Response('No plugin provided as query parameters', 400, ['Content-type' => 'application/json']);
        }

        $plugins = $this->composerService->upgrade($packadge);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("/remove", methods={"DELETE"})
     */
    public function removeAction(Request $request)
    {
        $status = 200;

        if (!$packadge = $request->query->get('plugin', false)) {
            return new Response('No plugin provided as query parameters', 400, ['Content-type' => 'application/json']);
        }

        $plugins = $this->composerService->remove($packadge);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }
}
