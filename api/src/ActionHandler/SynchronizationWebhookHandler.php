<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\SynchronizationService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class SynchronizationWebhookHandler implements ActionHandlerInterface
{
    /**
     * Wrapper function to prevent service loading on container autowiring.
     *
     * @param SynchronizationService $synchronizationService
     *
     * @return SynchronizationService
     */
    private function getSynchronizationService(ZgwToVrijbrpService $synchronizationService)
    {
        if (isset($this->synchronizationService)) {
            $this->synchronizationService = $synchronizationService;
        }

        return  $this->synchronizationService;
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
            'title'      => 'SynchronizationWebhookHandler',
            'description'=> '',
            'required'   => ['source', 'entity', 'locationIdField'],
            'properties' => [
                'source' => [
                    'type'        => 'string',
                    'description' => 'The source where to sink from',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'entity' => [
                    'type'        => 'string',
                    'description' => 'The enitity to sink',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,

                ],
                'apiSource' => [
                    'type'        => 'object',
                    'description' => 'The location of the id field in the external object',
                    'properties'  => [
                        'locationIdField' => [
                            'type'        => 'string',
                            'description' => 'The location of the id field in the external object',
                            'example'     => 'id',
                            'required'    => true,
                        ],
                        'object' => [
                            'type'        => 'string',
                            'description' => 'The location of the id field in the external object',
                            'example'     => 'id',
                        ],
                    ],
                ],

            ],
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
        $result = $this->getSynchronizationService->SynchronizationWebhookHandler($data, $configuration);

        return $data;
    }
}
