<?php

// src/Subscriber/DatabaseActivitySubscriber.php

namespace App\Subscriber;

use App\Entity\Value;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Twig\Environment;

class ValueDatabaseSubscriber implements EventSubscriberInterface
{
    private Environment $twig;

    public function __construct(
        Environment $twig
    ) {
        $this->twig = $twig;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        $value = $args->getObject();
        if ($value instanceof Value && $value->getStringValue()) {
            $value->setStringValue($this->twig->createTemplate($value->getStringValue())->render());
        }
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $this->preUpdate($args);
    }
}
