<?php

namespace App\ActionHandler;

use Doctrine\ORM\EntityManagerInterface;

class ZaakEigenschappenHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    public function __run(array $data, array $configuration): array
    {
        var_dump("joeeee");

        return $data;
    }
}
