<?php

namespace App\ActionHandler;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

interface ActionHandlerInterface
{
    public function __construct(ContainerInterface $container);

    public function __run(array $data, array $configuration): array;
}
