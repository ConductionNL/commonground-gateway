<?php

namespace App\Service;

use App\Entity\Action;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ActionService
{
    private ContainerInterface $container;

    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    /**
     * Get the action handler for an action.
     *
     * @param Action $action
     *
     * @return object|null
     */
    public function getHandlerForAction(Action $action): ?object
    {
        // If the action has a class
        if ($class = $action->getClass()) {
            return $this->container->get($class);
        }

        // if the action doesn't have class we want to return null
        return null;
    }

    /**
     * Generates a list of all action handlers.
     *
     * @return array
     */
    public function getAllHandler(): array
    {
        $handlers = $this->container->findTaggedServiceIds('commongateway.action_handlers');

        return $handlers;
    }
}
