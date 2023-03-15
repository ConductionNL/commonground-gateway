<?php

namespace App\Service;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use App\Entity\File;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Author Gino Kok, Wilco Louwerse <wilco@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class FileService
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private AuthorizationService $authorizationService;

    public function __construct(EntityManagerInterface $entityManager, ObjectEntityService $objectEntityService, AuthorizationService $authorizationService)
    {
        $this->entityManager = $entityManager;
        $this->objectEntityService = $objectEntityService;
        $this->authorizationService = $authorizationService;
    }

    public function handleFileDownload(string $id): Response
    {
        $file = $this->retrieveFileObject($id);
        $this->checkUserScopesForFile($file->getValue()->getObjectEntity());

        return $this->createFileResponse($file);
    }

    public function retrieveFileObject(string $id): File
    {
        if (!Uuid::isValid($id)) {
            throw new InvalidArgumentException('Invalid uuid');
        }

        $file = $this->entityManager->getRepository('App\Entity\File')->findOneBy(['id' => $id]);

        if (!$file instanceof File) {
            throw new NotFoundHttpException('Unable to find file');
        }

        return $file;
    }

    public function checkUserScopesForFile(ObjectEntity $objectEntity)
    {
        if (!$this->objectEntityService->checkOwner($objectEntity)) {
            $this->authorizationService->checkAuthorization(['entity' => $objectEntity->getEntity(), 'object' => $objectEntity]);
        }
    }

    public function createFileResponse(File $file)
    {
        $base64 = explode(',', $file->getBase64());
        $base64 = end($base64);

        $decoded = base64_decode($base64);

        $response = new Response(
            $decoded,
            200,
            ['content-type' => $file->getMimeType()]
        );

        $name = str_replace(".{$file->getExtension()}", '', $file->getName());

        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$name}.{$file->getExtension()}");
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
