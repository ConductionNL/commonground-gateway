<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Service\ActionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ActionDoctrineSubscriber implements EventSubscriberInterface
{
    private ActionService $actionService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ActionService $actionService,
        EntityManagerInterface $entityManager
    ) {
        $this->actionService = $actionService;
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['postLoad', EventPriorities::PRE_SERIALIZE],
        ];
    }

    public function postLoad(ViewEvent $event)
    {
        $this->addActionHandlerConfig($event);
    }

    private function addActionHandlerConfig(ViewEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');

        if ($route == 'api_action_handlers_get_collection' && $class = $event->getRequest()->get('class')) {
            // get the actionHandler with the same class from the request
            $actionHandlers = $this->actionService->getAllActionHandlers();
            foreach ($actionHandlers as $actionHandler) {
                if ($class == $actionHandler->getClass()) {
                    $event->setControllerResult($actionHandler);
                }
            }
        } elseif ($route == 'api_action_handlers_get_collection') {
            // get all actionHandlers with a commongateway.action_handlers tag
            $actionHandlers = $this->actionService->getAllActionHandlers();
            $event->setControllerResult($actionHandlers);
        }

        if ($route == 'api_actions_get_collection') {
            if ($event->getRequest()->query->count() > 0) {
                $actions = $this->entityManager->getRepository('App:Action')->findBy($event->getRequest()->query->all());
            } else {
                $actions = $this->entityManager->getRepository('App:Action')->findAll();
            }

            $response = [];
            foreach ($actions as $action) {
                $handler = $this->actionService->getHandlerForAction($action);
                $config = $handler->getConfiguration();

                $action = $action->setActionHandlerConfiguration($config);
                $response[] = $action;
            }
            $event->setControllerResult($response);
        }

        if ($route == 'api_actions_get_item') {
            $actionId = $event->getRequest()->attributes->get('_route_params') ? $event->getRequest()->attributes->get('_route_params')['id'] : null; //The id of the resource
            $action = $this->entityManager->getRepository('App:Action')->find($actionId);

            $handler = $this->actionService->getHandlerForAction($action);
            $config = $handler->getConfiguration();

            $action->setActionHandlerConfiguration($config);

            $event->setControllerResult($action);
        }
    }
}
