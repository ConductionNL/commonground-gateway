<?php

namespace App\ActionHandler;

use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use App\Service\ObjectEntityService;
use App\Service\ValidatorService;
use App\Service\UhrZaakService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

class ZaakEigenschappenHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private ValidatorService $validatorService;
    private array $usedValues = [];

    private UhrZaakService $uhrZaakService;

    public function __construct(ContainerInterface $container)
    {
        $zaakService = $container->get('uhrzaakservice');
        if ($zaakService instanceof UhrZaakService) {
            $this->uhrZaakService = $zaakService;
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
     * @throws \App\Exception\GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
        return $this->uhrZaakService->zaakEigenschappenHandler($data, $configuration);
    }
}
