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

    /**
     * Wrapper function to prevent service loading on container autowiring
     *
     * @param MapSimXMLService $mapSimXMLService
     * @return MapSimXMLService
     */
    private function getMapSimXMLService(MapSimXMLService $mapSimXMLService):MapSimXMLService {
        if(isset($this->mapSimXMLService)) {$this->mapSimXMLService = $mapSimXMLService;}
        return  $this->mapSimXMLService;
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
            'title'       => 'MapSimXMLHandler',
            'description' => 'This handler customly maps sim xml to zgw zaak and document ',
            'required'    => ['simXMLEntityId'],
            'properties'  => [
                'entities' => [
                    'type'        => 'object',
                    'description' => 'The id of the character entity',
                    'properties'  => [
                        'rolTypeEntityId' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the rolTypeEntityId entity',
                            'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                            'required'    => true,
                        ],
                        'ZaakType' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the ZaakType entity',
                            'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                            'required'    => true,
                        ],
                        'Zaak' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the zaak entity',
                            'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                            'required'    => true,
                        ],
                        'ObjectInformatieObject' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the ObjectInformatieObject entity',
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
        return $this->getMapSimXMLService()->mapSimXMLHandler($data, $configuration);
    }
}
