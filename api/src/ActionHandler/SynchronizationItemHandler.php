<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\SynchronizationService;

class SynchronizationItemHandler implements ActionHandlerInterface
{
    /**
     * Gets the SynchronizationService trough autowiring
     *
     * @param SynchronizationService $synchronizationService
     * @return SynchronizationService
     */
    private function getSynchronizationService(SynchronizationService $synchronizationService):SynchronizationService
    {
        return $synchronizationService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
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
        $result = $this->getSynchronizationService()->synchronizationItemHandler($data, $configuration);

        return $data;
    }
}
