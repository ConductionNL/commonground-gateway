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

    /**
     * This function runs the synchronization push handler plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
        $this->synchronizationService->synchronisationPushHandler($data, $configuration);

        return $data;
    }
}
