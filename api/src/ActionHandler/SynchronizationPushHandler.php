<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SynchronizationPushHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;

    public function __construct(ContainerInterface $container)
    {
        $synchronizationService = $container->get('synchronizationservice');
        if ($synchronizationService instanceof SynchronizationService) {
            $this->synchronizationService = $synchronizationService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
    }

    public function __run(array $data, array $configuration): array
    {
        $this->synchronizationService->getAllForObjects($data, $configuration);

        return $data;
    }
}
