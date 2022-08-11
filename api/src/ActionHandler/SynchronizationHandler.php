<?php

namespace App\ActionHandler;

use App\Entity\Entity;
use App\Entity\Gateway;
use App\Exception\GatewayException;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SynchronizationHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;

    public function __construct(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $synchronizationService = $container->get('synchronizationservice');
        if ($entityManager instanceof EntityManagerInterface && $synchronizationService instanceof SynchronizationService) {
            $this->entityManager = $entityManager;
            $this->synchronizationService = $synchronizationService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }

    }

    public function __run(array $data, array $configuration): array
    {

        $result = $this->synchronizationService->getAllFromSource($data, $configuration);

        return $data;
    }
}
