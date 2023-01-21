<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\MapZaakTypeService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class MapZaakTypeHandler implements ActionHandlerInterface
{
    private MapZaakTypeService $mapZaakTypeService;

    public function __construct(MapZaakTypeService $mapZaakTypeService)
    {
        $this->mapZaakTypeService = $mapZaakTypeService;
    }

    function getConditions() {
        return ['==' => [1, 1]];
    }

    function getListens() {
        return [
            'none'
        ];
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
            'title'       => 'MapZaakTypeHandler',
            'description' => 'This handler customly maps xxllnc casetype to zgw zaaktype ',
            'required'    => ['zaakTypeEntityId'],
            'properties'  => [
                'entities' => [
                    'type'        => 'string',
                    'description' => 'The UUID of the case entitEntity on the gateway',
                    'properties'  => [
                        'ZaakType' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the ZaakType entity',
                            'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                            'required'    => true,
                        ],
                    ],
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
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     * @throws GatewayException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->mapZaakTypeService->mapZaakTypeHandler($data, $configuration);
    }
}
