<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\MapRelocationService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Respect\Validation\Exceptions\ComponentException;

class MapRelocationHandler
{
    private MapRelocationService $mapRelocationService;

    public function __construct(MapRelocationService $mapRelocationService)
    {
        $this->mapRelocationService = $mapRelocationService;
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
            'title'       => 'Map Relocation Action',
            'description' => 'This handler customly maps zgw zaak to vrijbrp relocation',
            'required'    => [],
            'properties'  => [],
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
        return $this->mapRelocationService->mapRelocationHandler($data, $configuration);
    }
}
