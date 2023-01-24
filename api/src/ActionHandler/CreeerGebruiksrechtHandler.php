<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class CreeerGebruiksrechtHandler implements ActionHandlerInterface
{
    private ZdsZaakService $zdsZaakService;

    public function __construct(ZdsZaakService $zdsZaakService)
    {
        $this->zdsZaakService = $zdsZaakService;
    }

    public function getConditions()
    {
        return ['==' => [1, 1]];
    }

    public function getListens()
    {
        return [
            'none',
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
            'title'       => 'CreeerGebruiksrechtHandler',
            'description' => 'This handler posts a zaak document from ZDS to ZGW',
            'required'    => ['informatieObjectTypeEntityId', 'enkelvoudigInformatieObjectEntityId', 'zaakTypeInformatieObjectTypeEntityId'],
            'properties'  => [
                'gebruiksrechtEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the Gebruiksrecht entity',
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
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->zdsZaakService->creeerGebruiksrechtHandler($data, $configuration);
    }
}
