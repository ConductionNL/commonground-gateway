<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\SynchronizationService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SynchronizationWebhookHandler implements ActionHandlerInterface
{
    private SynchronizationService $synchronizationService;

    public function __construct(ContainerInterface $container)
    {
        $synchronizationService = $container->get('synchronizationservice');
        if ($synchronizationService instanceof SynchronizationService) {
            $this->synchronizationService = $synchronizationService;
        } else {
            throw new GatewayException('The service container does not contain the required services for the SynchronizationWebhookHandler');
        }
    }

    //@todo define config

    /**
     * @throws GatewayException|InvalidArgumentException|ComponentException|CacheException
     */
    public function __run(array $data, array $configuration): array
    {
        $this->validateConfiguration($configuration);

        $result = $this->synchronizationService->SynchronizationWebhookHandler($data, $configuration);

        return $data;
    }

    /**
     * Validates if the $configuration array has the correct/required keys.
     *
     * @param array $configuration
     *
     * @return void
     * @throws GatewayException
     */
    private function validateConfiguration(array $configuration)
    {
        // todo: use jsonLogic for this instead!
//        if (!empty(array_intersect_key($configuration, array_flip(['source', 'entity', 'locationIdField'])))) {
//
//        }
        if (in_array("source", $configuration) || in_array("entity", $configuration) || in_array("locationIdField", $configuration)){
            throw new GatewayException('The configuration array does not match the required keys for the SynchronizationWebhookHandler', null, null, ['requiredKeys' => ['source', 'entity', 'locationIdField']]);
        }
    }
}
