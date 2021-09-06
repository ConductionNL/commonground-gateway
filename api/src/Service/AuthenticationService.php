<?php

namespace App\Service;

use App\Entity\Gateway;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthenticationService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
    }

}
