<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class IdentificationHandler implements ActionHandlerInterface
{
    private ZdsZaakService $zdsZaakService;

    /**
     * Wrapper function to prevent service loading on container autowiring
     *
     * @param ZdsZaakService $zdsZaakService
     * @return ZdsZaakService
     */
    private function getZdsZaakService(ZdsZaakService $zdsZaakService):ZdsZaakService {
        if(isset($this->zdsZaakService)) {$this->zdsZaakService = $zdsZaakService;}
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
            'title'       => 'IdentificationHandler',
            'description' => 'This handler checks if the case has an identification field and if not, launches a Di02 request to add it.',
            'required'    => ['entityType'],
            'properties'  => [
                'entityType' => [
                    'type'        => 'string',
                    'description' => 'The entity type',
                    'example'     => 'ZAK',
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
        return $this->getZdsZaakService()->identificationHandler($data, $configuration);
    }
}
