<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\SynchronizationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SynchronizationCollectionHandler implements ActionHandlerInterface
{
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

    //@todo define config


    public function __run(array $data, array $configuration): array
    {
        $result = $this->synchronizationService->SynchronizationCollectionHandler($data, $configuration);

        return $data;
    }
}
