<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\MapCommitmentService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Respect\Validation\Exceptions\ComponentException;

class MapCommitmentHandler implements ActionHandlerInterface
{
    private mapCommitmentService $mapCommitmentService;

    public function __construct(ContainerInterface $container)
    {
        $mapCommitmentService = $container->get('mapCommitmentService');
        if ($mapCommitmentService instanceof MapCommitmentService) {
            $this->mapCommitmentService = $mapCommitmentService;
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
            'title'       => 'Map Relocation Action',
            'description' => 'This handler customly maps zgw zaak to vrijbrp relocation',
            'required'    => [],
            'properties'  => [],
        ];
    }

    /**
     * This function runs the service for validating cases.
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
        return $this->mapCommitmentService->mapCommitmentHandler($data, $configuration);
    }
}
