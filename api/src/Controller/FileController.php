<?php

namespace App\Controller;

use App\Service\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AbstractController
{
    /**
     * @Route("/api/files/{id}", methods={"get"})
     */
    public function fileAction(string $id, FileService $fileService)
    {
        return $fileService->handleFileDownload($id);
    }
}
