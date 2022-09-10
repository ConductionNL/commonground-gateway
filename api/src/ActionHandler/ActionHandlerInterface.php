<?php

namespace App\ActionHandler;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface ActionHandlerInterface
{
    public function __construct(ContainerInterface $container);

    /**
     *  This function returns the event types that are suported by this handler
     *
     * @throws array a list of event types
     */
    public function getEvents(): array;

    /**
     *  This function returns the configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array;

    public function __run(array $data, array $configuration): array;
}
