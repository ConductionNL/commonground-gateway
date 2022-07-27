<?php

namespace App\Subscriber;

use App\Entity\Action;
use App\Entity\Endpoint;
use App\Event\ActionEvent;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class ActionSubscriber implements EventSubscriberInterface
{

    private EntityManagerInterface $entityManager;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'commongateway.handler.pre' => 'handleEvent',
            'commongateway.handler.post' => 'handleEvent',
        ];
    }

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function handleEvent(ActionEvent $event): ActionEvent
    {
        $actions = $this->entityManager->getRepository("App:Action")->findByListens($event->getType());
        foreach($actions as $action) {
            
        }

        return $event;
    }
}
