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
    /**
     * @var UserPasswordHasherInterface
     */
    private UserPasswordHasherInterface $hasher;

    /**
     * @param UserPasswordHasherInterface $hasher The Password Hasher.
     */
    public function __construct(
        UserPasswordHasherInterface $hasher
    ) {
        $this->hasher = $hasher;
    }//end __construct()

    /**
     * this method can only return the event names; you cannot define a custom method name to execute when each event triggers
     *
     * @return array The events to listen to
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }//end getSubscribedEvents()

    /**
     * Callback methods must be called exactly like the events they listen to;
     * they receive an argument of type LifecycleEventArgs,
     * which gives you access to both the entity object of the event and the entity manager itself
     *
     * @param LifecycleEventArgs $args The lifecycleEvents to listen to
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->hashPassword($args);
    }//end prePersist()

    /**
     * Callback methods must be called exactly like the events they listen to;
     * they receive an argument of type LifecycleEventArgs,
     * which gives you access to both the entity object of the event and the entity manager itself
     *
     * @param LifecycleEventArgs $args The lifecycleEvents to listen to
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->hashPassword($args);
    }//end preUpdate()

    /**
     * Hashes an unhashed password before writing a user to the database
     *
     * @param LifecycleEventArgs $args The arguments containing the user to update
     */
    private function hashPassword(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof User === true
            && password_get_info($object->getPassword())['algoName'] === 'unknown') {
            $hash = $this->hasher->hashPassword($object, $object->getPassword());
            $object->setPassword($hash);
        }
    }//end hashPassword()
}//end class
