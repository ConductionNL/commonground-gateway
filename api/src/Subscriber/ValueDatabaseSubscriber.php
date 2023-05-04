<?php

// src/Subscriber/DatabaseActivitySubscriber.php

namespace App\Subscriber;

use App\Entity\Value;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class ValueDatabaseSubscriber implements EventSubscriberInterface
{
    private Environment $twig;

    private EntityManagerInterface $entityManager;

    private ParameterBagInterface $param;

    public function __construct(
        Environment $twig,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $param
    ) {
        $this->twig = $twig;
        $this->entityManager = $entityManager;
        $this->param = $param;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $value = $args->getObject();
        if ($value instanceof Value && $value->getStringValue()) {
            if($value->getObjectEntity()->getUri() === null) {
                $value->getObjectEntity()->setUri(rtrim($this->param->get('app_url'), '/').$value->getObjectEntity()->getSelf());
            }
            $value->setStringValue($this->twig->createTemplate($value->getStringValue())->render(['object' => $value->getObjectEntity()]));
        }
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->postUpdate($args);
    }
}
