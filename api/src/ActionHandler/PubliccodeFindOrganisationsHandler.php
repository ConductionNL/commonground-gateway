<?php

namespace App\ActionHandler;

use Symfony\Component\DependencyInjection\ContainerInterface;

class PubliccodeFindOrganisationsHandler implements ActionHandlerInterface
{

    public function __construct(ContainerInterface $container)
    {
    }

    public function __run(array $data, array $configuration): array
    {
        // TODO: Implement __run() method.
    }
}
