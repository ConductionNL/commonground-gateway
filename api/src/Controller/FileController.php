<?php

namespace App\Controller;

use App\Service\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Authors: Gino Kok, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 */
class FileController extends AbstractController
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @Route("/api/files/{id}", methods={"get"})
     */
    public function fileAction(string $id, FileService $fileService)
    {
        $this->cache->invalidateTags(['grantedScopes']);

        return $fileService->handleFileDownload($id);
    }
}
