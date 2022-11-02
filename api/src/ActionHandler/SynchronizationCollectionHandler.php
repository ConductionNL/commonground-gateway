<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\SynchronizationService;
use Cassandra\Exception\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\Cache\Exception\CacheException;

class SynchronizationCollectionHandler implements ActionHandlerInterface
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
            'required'   => ['ServiceDNS'],
            'properties' => [
                'serviceDNS' => [
                    'type'        => 'string',
                    'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                    'example'     => 'native://default',
                ],
            ],
        ];
    }

    /**
     * Run the actual business logic in the appropriate server.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws GatewayException|InvalidArgumentException|ComponentException|CacheException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        var_dump('Sync triggered: ' . $configuration['location']);
        $this->synchronizationService->SynchronizationCollectionHandler($data, $configuration);

        return $data;
    }
}
