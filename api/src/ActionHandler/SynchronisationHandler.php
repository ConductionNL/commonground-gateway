<?php

namespace App\ActionHandler;

use App\Entity\Entity;
use App\Entity\Gateway;
use App\Exception\GatewayException;
use App\Service\SynchronisationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SynchronisationHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private SynchronisationService $synchronisationService;

    public function __construct(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $synchronisationService = $container->get('synchronisationservice');
        if ($entityManager instanceof EntityManagerInterface && $synchronisationService instanceof SynchronisationService) {
            $this->entityManager = $entityManager;
            $this->synchronisationService = $synchronisationService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }

    }

    public function getGateway($configuration): ?Gateway
    {
        $gateway = $this->entityManager->getRepository('App:Gateway')->findOneBy(['id' => $configuration['source']]);

        if($gateway instanceof Gateway) {
            return $gateway;
        } else {
            return null;
        }
    }

    public function getEntity($configuration): ?Entity
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $configuration['eavObject']]);

        if($entity instanceof Entity) {
            return $entity;
        } else {
            return null;
        }
    }

    public function __run(array $data, array $configuration): array
    {
        $gateway =  $this->getGateway($configuration);
        $entity =   $this->getEntity($configuration);

        $result = $this->synchronisationService->getFromSource($gateway, $entity, $configuration['location'], $configuration['apiSource']);

        return $data;
    }
}
