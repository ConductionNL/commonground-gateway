<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZdsValidationHandler implements ActionHandlerInterface
{
    private ZdsZaakService $zdsZaakService;

    /**
     * Wrapper function to prevent service loading on container autowiring
     *
     * @param ZdsZaakService $zdsZaakService
     * @return ZdsZaakService
     */
    private function getZdsZaakService(ZdsZaakService $zdsZaakService): ZdsZaakService{
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
            'title'       => 'ZdsValidationHandler',
            'description' => 'Todo',
            'required'    => ['identifierPath'],
            'properties'  => [
                'identifierPath' => [
                    'type'        => 'string',
                    'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                    'example'     => 'native://default',
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
        return $this->getZdsZaakService()->zdsValidationHandler($data, $configuration);
    }
}
