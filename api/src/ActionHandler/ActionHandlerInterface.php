<?php

namespace App\ActionHandler;

use Doctrine\ORM\EntityManagerInterface;

interface ActionHandlerInterface
{
    public function __construct(EntityManagerInterface $entityManager);

    public function __run(array $data, array $configuration): array;
}
