<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mime\Email;

final class EntityToSchemaSubscriber implements EventSubscriberInterface
{
//    private $mailer;

    public function __construct()
    {
//        $this->mailer = $mailer;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['toSchema', EventPriorities::PRE_VALIDATE],
        ];
    }

    public function toSchema(ViewEvent $event): void
    {
        $entityType = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();
        $headers = $event->getRequest()->headers->get('accept');

        var_dump($headers);

        if ($entityType instanceof ObjectEntity && Request::METHOD_GET == $method) {
            $objectEntity = $entityType;

            var_dump($objectEntity->getEntity()->toSchema($objectEntity));

            $objectEntity->getEntity()->toSchema($objectEntity);

            return;
        }

        if (!$entityType instanceof Entity && Request::METHOD_GET !== $method) {
            return;
        }

        var_dump($entityType->toSchema(null));die();

        $entity->toSchema(null);
    }
}
