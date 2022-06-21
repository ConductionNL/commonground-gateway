<?php

namespace App\Subscriber;

use App\Event\EndpointTriggeredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EndpointSubscriber implements EventSubscriberInterface
{

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            EndpointTriggeredEvent::NAME => 'handleEvent',
        ];
    }

    public function handleEvent(EndpointTriggeredEvent $event)
    {
        echo 'triggered '.$event->getEndpoint()->getName();

        return $event;
    }
}
