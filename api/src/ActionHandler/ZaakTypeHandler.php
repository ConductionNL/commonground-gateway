<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Psr\Container\ContainerInterface;

class ZaakTypeHandler implements ActionHandlerInterface
{
    private ZdsZaakService $zdsZaakService;

    public function __construct(ContainerInterface $container)
    {
        $zdsZaakService = $container->get('zdszaakservice');
        if ($zdsZaakService instanceof ZdsZaakService) {
            $this->zdsZaakService = $zdsZaakService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
    }

    /**
     * This function runs the zaak type plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws \App\Exception\GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
        return $this->zdsZaakService->zaakTypeHandler($data, $configuration);
    }
}
