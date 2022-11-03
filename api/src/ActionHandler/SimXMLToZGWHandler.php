<?php

namespace App\ActionHandler;

use App\Service\SimXMLZaakService;
use ErrorException;

class SimXMLToZGWHandler implements ActionHandlerInterface
{
    private SimXMLZaakService $simXMLZaakService;

    public function __construct(SimXMLZaakService $simXMLZaakService)
    {
        $this->simXMLZaakService = $simXMLZaakService;
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
            'title'       => 'SimXMLToZGWHandler',
            'description' => 'This handler posts a zaak from SimXML to ZGW',
            'required'    => ['zaakEntityId', 'zaakTypeEntityId'],
            'properties'  => [
                'zaakEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the zaak entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true
                ],
                'zaakTypeEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the zaaktype entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true
                ],
                'enrichData' => [
                    'type'        => 'boolean',
                    'description' => 'Boolean for enrich data',
                    'example'     => 'true',
                    'nullable'    => true
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
     * @throws ErrorException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->simXMLZaakService->simXMLToZGWHandler($data, $configuration);
    }
}
