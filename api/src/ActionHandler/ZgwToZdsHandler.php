<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZgwToZdsHandler implements ActionHandlerInterface
{
    private ZdsZaakService $zdsZaakService;

    /**
     * Wrapper function to prevent service loading on container autowiring.
     *
     * @param ZdsZaakService $zdsZaakService
     *
     * @return ZdsZaakService
     */
    private function getZdsZaakService(ZdsZaakService $zdsZaakService): ZdsZaakService
    {
        if (isset($this->zdsZaakService)) {
            $this->zdsZaakService = $zdsZaakService;
        }

        return  $this->zdsZaakService;
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
            'title'       => 'ZgwToZdsHandler',
            'description' => 'This handler posts zaak eigenschappen from ZDS to ZGW',
            'required'    => ['zaakEntityId', 'rolEntityId', 'zdsEntityId'],
            'properties'  => [
                'zaakEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the zaak entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'rolEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the rol entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'zdsEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the zds entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
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
        return $this->getZdsZaakService()->ZgwToZdsHandler($data, $configuration);
    }
}
