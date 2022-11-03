<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\ActionHandler;
use Exception;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ActionService
{
    private ContainerInterface $container;
    private iterable $actionHandlers;

    public function __construct(
        iterable $actionHandlers,
        ContainerInterface $container
    ) {
        $this->actionHandlers = $actionHandlers;
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
     * @throws Exception
     *
     * @return array
     */
    public function getAllActionHandlers(): array
    {
        $result = [];
        foreach ($this->actionHandlers as $actionHandler) {
            $newActionHandler = new ActionHandler();
            $newActionHandler->setId(Uuid::uuid4());
            $newActionHandler->setClass(get_class($actionHandler));
            $newActionHandler->setConfiguration($actionHandler->getConfiguration());
            $result[] = $newActionHandler;
        }

        return $result;
    }
}
