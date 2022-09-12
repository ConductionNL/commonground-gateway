<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\NotificationService;
use Psr\Container\ContainerInterface;

/**
 * This function provides the logic for setting up a proxy to a source (bypassing the datalayer)
 */
class ProxyHandler implements ActionHandlerInterface
{

    public function __construct(ContainerInterface $container)
    {
    }

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action schould comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'Notification Action',
            'required'   => ['sourceId'],
            'properties' => [
                'sourceId' => [
                    'type'        => 'string',
                    'format'      => 'uuid',
                    'description' => 'The id of the source where to send the proxy to',
                    'example'     => 'f3dce042-cd6e-4977-844d-05be8dce7cea',
                ],
                'endpoint' => [
                    'type'        => 'string',
                    'description' => 'The endpoint on the source where the proxy is send to',
                    'example'     => '/webhook',
                ],
                'query' => [
                    'type'        => 'array',
                    'description' => 'Any additional query parameters that need to be included (for example for validation)',
                    'example'     => ['token'=>'123'],
                ],
                'headers' => [
                    'type'        => 'array',
                    'description' => 'Any additional header parameters that need to be included (for example for validation)',
                    'example'     => ['token'=>'123'],
                ],
                'mappingIn' => [
                    'type'        => 'string',
                    'format'      => 'uuid',
                    'description' => 'Any mapping that needs to be done bofore sending the object to the source',
                    'example'     => 'f3dce042-cd6e-4977-844d-05be8dce7cea',
                ],
                'mappingOur' => [
                    'type'        => 'string',
                    'format'      => 'uuid',
                    'description' => 'Any mapping that needs to be done between the original source and the return on the endpoint',
                    'example'     => 'f3dce042-cd6e-4977-844d-05be8dce7cea',
                ],
            ],
        ];
    }

    /**
     * This function runs the notificationService plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
        return $this->notificationService->NotificationHandler($data, $configuration);
    }
}
