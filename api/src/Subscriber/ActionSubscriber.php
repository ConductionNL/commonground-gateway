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

    private Client $client;
    private EntityManagerInterface $entityManager;
    private Request $request;

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

    public function handleEvent(ActionEvent $event): ActionEvent
    {
//        if(
//            $event->getRequest()->getMethod() == 'GET'
//            // @TODO: add extra conditions
//        ) {
//            return $event;
//        }
//        $this->request = $event->getRequest();
//        $eventContent = $this->getEvent($event->getEndpoint());
//        $result = $this->sendEvent($eventContent);


        return $event;
    }
}
