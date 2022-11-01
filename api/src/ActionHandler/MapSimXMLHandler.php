<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\MapSimXMLService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class MapSimXMLHandler implements ActionHandlerInterface
{
    private MapSimXMLService $mapSimXMLService;

    public function __construct(MapSimXMLService $mapSimXMLService)
    {
        $this->mapSimXMLService = $mapSimXMLService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://example.com/person.schema.json',
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            'title'       => 'Sim XML Action',
            'description' => 'This handler customly maps sim xml to zgw zaak and document ',
            'required'    => ['simXMLEntityId'],
            'properties'  => [
                'simXMLEntityId' => [
                    'type'        => 'string',
                    'description' => 'The UUID of the case entitEntity on the gateway',
                    'example'     => '',
                ],
            ],
        ];
    }

    /**
     * This function runs the service for validating cases.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->mapSimXMLService->mapSimXMLHandler($data, $configuration);
    }
}
