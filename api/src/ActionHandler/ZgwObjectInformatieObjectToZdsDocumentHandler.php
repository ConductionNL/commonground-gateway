<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZgwObjectInformatieObjectToZdsDocumentHandler implements ActionHandlerInterface
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
            'title'       => 'ZgwObjectInformatieObjectToZdsDocumentHandler',
            'description' => 'This handler posts zaak eigenschappen from ZGW InformatieObject to ZDS Document',
            'required'    => ['documentEntityId', 'zdsEntityId'],
            'properties'  => [
                'documentEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the document entity',
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
        return $this->zdsZaakService->zgwObjectInformatieObjectToZdsDocumentHandler($data, $configuration);
    }
}