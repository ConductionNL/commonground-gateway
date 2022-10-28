<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DashboardCardDoctrineSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
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

        if (!$route == 'api_dashboard_cards_get_collection') {
            return;
        }

        $dashboardCards = $this->entityManager->getRepository('App:DashboardCard')->findAll();

        $response = [];
        foreach ($dashboardCards as $dashboardCard) {
            if(!$entity = $dashboardCard->getEntity()){
                return;
            }

            $className = 'App:'.$entity;
            $object = $this->entityManager->find($className, $dashboardCard->getEntityId());

            $dashboardCard->setObject($object);

            $response[] = $dashboardCard;
        }
        $event->setControllerResult($response);
    }
}
