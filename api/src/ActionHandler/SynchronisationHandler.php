<?php

namespace App\ActionHandler;

use App\Entity\Gateway;
use Doctrine\ORM\EntityManagerInterface;

class SynchronisationHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

    }

    public function getGateway($configuration): Gateway
    {
        $this->entityManager->getRepository('App::Gateway')->findOneBy(['id' => $configuration['source']]);
    }

    public function __run(array $data, array $configuration): array
    {
        var_dump($configuration);

        return $data;
    }
}
