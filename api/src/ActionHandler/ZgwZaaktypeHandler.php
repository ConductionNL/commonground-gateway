<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZgwZaaktypeHandler implements ActionHandlerInterface
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
            'title'       => 'ZgwZaaktypeHandler',
            'description' => 'This handler posts the modified data of the call with the case type and identification',
            'required'    => ['eigenschapEntityId', 'roltypenEntityId', 'resultaattypenEntityId', 'statustypenEntityId'],
            'properties'  => [
                'eigenschapEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the eigenschap entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true
                ],
                'roltypenEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the roltypen entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true
                ],
                'resultaattypenEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the resultaattypen entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true
                ],
                'statustypenEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the statustypen entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true
                ],
            ],
        ];
    }

    /**
     * This function runs the zgw zaaktype plugin.
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
        return $this->zdsZaakService->zgwZaaktypeHandler($data, $configuration);
    }
}
