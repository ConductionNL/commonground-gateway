<?php

namespace App\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\KernelEvents;
use ApiPlatform\Core\EventListener\EventPriorities;

class CallIdSubscriber implements EventSubscriberInterface
{
    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['gateway', EventPriorities::PRE_DESERIALIZE],
        ];
    }

    public function OnFirstEvent(RequestEvent $event)
    {
        unset($_SESSION['callId']);
        $callId = Uuid::uuid4()->toString();
        var_dump($callId);die;
        $_SESSION['callId'] = $callId;
    }
}
