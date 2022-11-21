<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Exception;

class ZaakInformatieObjectHandler implements ActionHandlerInterface
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
            'title'       => 'ZaakInformatieObjectHandler',
            'description' => 'Updates all zaakInformatieObjecten with correct URLs before syncing them to ZGW.',
            'required'    => ['zaakInformatieObjectEntityId'],
            'properties'  => [
                'zaakInformatieObjectEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the zaakInformatieObject entity',
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
     * @throws Exception
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->getZdsZaakService()->zaakInformatieObjectHandler($data, $configuration);
    }
}
