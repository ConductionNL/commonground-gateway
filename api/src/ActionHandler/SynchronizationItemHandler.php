<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\SynchronizationService;

class SynchronizationItemHandler implements ActionHandlerInterface
{
    private SynchronizationService $synchronizationService;

    /**
     * @param SynchronizationService $synchronizationService
     */
    public function __construct(SynchronizationService $synchronizationService)
    {
        $this->synchronizationService = $synchronizationService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://commongateway.nl/ActionHandler/SynchronizationItemHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'synchronizationItemHandler',
            'description'=> 'Todo',
            'required'   => [],
            'properties' => [],
        ];
    }

    /**
     * Run the actual business logic in the appropriate server.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws GatewayException|InvalidArgumentException|ComponentException|CacheException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        $result = $this->synchronizationService->synchronizationItemHandler($data, $configuration);

        return $data;
    }
}
