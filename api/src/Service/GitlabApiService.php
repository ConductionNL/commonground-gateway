<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

class GitlabApiService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;
    private ObjectEntityService $objectEntityService;

    public function __construct() {}

}
