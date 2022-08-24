<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class GitlabApiService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;
    private ObjectEntityService $objectEntityService;

    public function __construct()
    {
    }
}
