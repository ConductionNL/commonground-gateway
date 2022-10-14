<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Exception;

class ZaakInformatieObjectHandler
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
            'title'       => 'Zaakeigenschappen Action',
            'description' => 'This handler posts zaak eigenschappen from ZDS to ZGW',
            'required'    => ['identifierPath'],
            'properties'  => [
                'identifierPath' => [
                    'type'        => 'string',
                    'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                    'example'     => 'native://default',
                ],
                'eigenschappen' => [
                    'type'        => 'array',
                    'description' => '',
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
     * @throws Exception
     *
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
        return $this->zdsZaakService->zaakInformatieObjectHandler($data, $configuration);
    }
}
