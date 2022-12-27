<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\DashboardCard;
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
        $this->updateDashboardCard($event);
    }

    private function addObject(DashboardCard $dashboardCard): ?DashboardCard
    {
        if (!$entity = $dashboardCard->getEntity()) {
            return null;
        }

        if (strpos($entity, 'App\\Entity')) {
            $entity = 'App:'.$entity;
        }

        $object = $this->entityManager->find($entity, $dashboardCard->getEntityId());

        return $dashboardCard->setObject($object);
    }

    private function updateDashboardCard(ViewEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');

        if ($route == 'api_dashboard_cards_get_collection') {
            $dashboardCards = $this->entityManager->getRepository('App:DashboardCard')->findAll();

            $response = [];
            foreach ($dashboardCards as $dashboardCard) {
                $dashboardCard = $this->addObject($dashboardCard);
                $response[] = $dashboardCard;
            }
            $event->setControllerResult($response);
        }

        if ($route == 'api_dashboard_cards_get_item') {
            $objectId = $event->getRequest()->attributes->get('_route_params') ? $event->getRequest()->attributes->get('_route_params')['id'] : null; //The id of the resource

            $dashboardCard = $this->entityManager->getRepository('App:DashboardCard')->find($objectId);
            $dashboardCard = $this->addObject($dashboardCard);
            $event->setControllerResult($dashboardCard);
        }
    }
}
