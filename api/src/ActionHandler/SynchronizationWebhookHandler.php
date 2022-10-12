<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\SynchronizationService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SynchronizationWebhookHandler
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
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'Notification Action',
            'required'   => ['source', 'entity', 'locationIdField'],
            'properties' => [
                'source' => [
                    'type'        => 'string',
                    'description' => 'The source where to sink from',
                    'example'     => 'native://default',
                ],
                'entity' => [
                    'type'        => 'string',
                    'description' => 'The enitity to sink',
                    'example'     => '',
                ],
                'locationIdField' => [
                    'type'        => 'string',
                    'description' => 'The location of the id field in the external object',
                    'example'     => 'id',
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
        $result = $this->synchronizationService->SynchronizationWebhookHandler($data, $configuration);

        return $data;
    }
}
