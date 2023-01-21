<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZgwToVrijbrpService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZgwToSoapHandler implements ActionHandlerInterface
{
    private ZgwToVrijbrpService $zgwToVrijbrpService;

    public function __construct(ZgwToVrijbrpService $zgwToVrijbrpService)
    {
        $this->zgwToVrijbrpService = $zgwToVrijbrpService;
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
            'title'       => 'ZgwToSoapHandler',
            'description' => 'This handler posts zaak eigenschappen from ZGW to VrijBrp SOAP',
            'required'    => [],
            'properties'  => [
                'entities' => [
                    'type'        => 'object',
                    'description' => '',
                    'properties'  => [
                        'Birth' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the Birth entity',
                            'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                            'nullable'    => true,
                        ],
                        'InterRelocation' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the InterRelocation entity',
                            'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                            'nullable'    => true,
                        ],
                        'Commitment' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the Commitment entity',
                            'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                            'nullable'    => true,
                        ],
                        'Death' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the Death entity',
                            'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                            'nullable'    => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * This function runs the zaakeigenschappen plugin.
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
        return $this->zgwToVrijbrpService->zgwToVrijbrpHandler($data, $configuration);
    }
}
