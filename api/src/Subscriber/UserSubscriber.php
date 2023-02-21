<?php

// src/Subscriber/DatabaseActivitySubscriber.php

namespace App\Subscriber;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserSubscriber implements EventSubscriberInterface
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(
        UserPasswordHasherInterface $hasher
    ) {
        $this->hasher = $hasher;
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

    // callback methods must be called exactly like the events they listen to;
    // they receive an argument of type LifecycleEventArgs, which gives you access
    // to both the entity object of the event and the entity manager itself
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->hashPassword($args);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->hashPassword($args);
    }

    private function hashPassword(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof User === true
            && password_get_info($object->getPassword())['algoName'] === 'unknown') {
            $hash = $this->hasher->hashPassword($object, $object->getPassword());
            $object->setPassword($hash);
        }
    }
}
