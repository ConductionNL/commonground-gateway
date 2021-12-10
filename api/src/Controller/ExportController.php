<?php

namespace App\Controller;

use App\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ExportController extends AbstractController
{
    /**
     * @Route("/admin/export/{type}")
     */
    public function dynamicAction(?string $type, ExportService $exportService)
    {
        return $exportService->handleExports($type);
    }
}
