<?php

namespace App\Controller;

use App\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Authors: Gino Kok, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 */
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
