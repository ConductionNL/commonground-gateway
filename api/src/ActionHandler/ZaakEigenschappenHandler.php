<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\ZdsZaakService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Respect\Validation\Exceptions\ComponentException;

class ZaakEigenschappenHandler implements ActionHandlerInterface
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
    public function __run(array $data, array $configuration): array
    {
        return $this->zdsZaakService->zaakEigenschappenHandler($data, $configuration);
    }
}
