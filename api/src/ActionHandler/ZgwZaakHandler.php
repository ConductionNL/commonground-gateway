<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZgwService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Respect\Validation\Exceptions\ComponentException;

class ZgwZaakHandler implements ActionHandlerInterface
{
    private zgwService $zgwService;

    public function __construct(ContainerInterface $container)
    {
        $zgwService = $container->get('zgwservice');
        if ($zgwService instanceof ZgwService) {
            $this->zgwService = $zgwService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
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
            'title'       => 'ZGW Zaak Action',
            'description' => 'This handler checks if zaak is valid conform zgw standaard ',
            'required'    => ['zaakEntityId'],
            'properties'  => [
                'zaakEntityId' => [
                    'type'        => 'string',
                    'description' => 'The UUID of the case entitEntity on the gateway',
                    'example'     => '',
                ],
            ],
        ];
    }

    /**
     * This function runs the service for validating cases
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
    public function __run(array $data, array $configuration): array
    {
        return $this->zgwService->zdsValidationHandler($data, $configuration);
    }
}
