<?php

namespace App\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\KernelEvents;
use ApiPlatform\Core\EventListener\EventPriorities;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CallIdSubscriber implements EventSubscriberInterface
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['OnFirstEvent', EventPriorities::PRE_DESERIALIZE],
        ];
    }

    public function OnFirstEvent(RequestEvent $event)
    {
        !$this->session->get('callId') && $this->session->set('callId', Uuid::uuid4()->toString());
    }
}
