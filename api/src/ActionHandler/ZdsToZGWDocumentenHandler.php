<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZdsToZGWDocumentenHandler implements ActionHandlerInterface
{
    private ZdsZaakService $zdsZaakService;

    public function __construct(ZdsZaakService $zdsZaakService)
    {
        $this->zdsZaakService = $zdsZaakService;
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
            'title'       => 'ZdsToZGWDocumentenHandler',
            'description' => 'This handler posts a zaak document from ZDS to ZGW',
            'required'    => ['informatieObjectTypeEntityId', 'enkelvoudigInformatieObjectEntityId', 'zaakTypeInformatieObjectTypeEntityId'],
            'properties'  => [
                'informatieObjectTypeEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the informatieObject entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'enkelvoudigInformatieObjectEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the enkelvoudigInformatieObject entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'zaakTypeInformatieObjectTypeEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the zaakTypeInformatieObjectType entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'enrichData' => [
                    'type'        => 'boolean',
                    'description' => 'Boolean for enrich data',
                    'example'     => 'true',
                    'nullable'    => true,
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
        return $this->zdsZaakService->zdsToZGWDocumentenHandler($data, $configuration);
    }
}
