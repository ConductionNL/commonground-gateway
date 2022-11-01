<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\DashboardCard;
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

        if ($route == 'api_actions_get_collection') {
            $actions = $this->entityManager->getRepository('App:Action')->findAll();

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
