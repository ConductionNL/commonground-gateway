<?php

namespace App\Subscriber;

use App\ActionHandler\ActionHandlerInterface;
use App\Entity\Action;
use App\Event\ActionEvent;
use App\Message\NotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use JWadhams\JsonLogic;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActionSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private ContainerInterface $container;
    private NotificationMessage $messageBus;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'commongateway.handler.pre'     => 'handleEvent',
            'commongateway.handler.post'    => 'handleEvent',
            'commongateway.response.pre'    => 'handleEvent',
            'commongateway.cronjob.trigger' => 'handleEvent',
            'commongateway.object.create'   => 'handleEvent',
            'commongateway.object.read'     => 'handleEvent',
            'commongateway.object.update'   => 'handleEvent',
            'commongateway.object.delete'   => 'handleEvent',
            'zwg.zaak.pushed'               => 'handleEvent',
            'zwg.zaakEigenschap.handled'    => 'handleEvent',
            'commongateway.action.event'    => 'handleEvent'

        ];
    }

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
    }

    public function runFunction(Action $action, array $data): array
    {
        $class = $action->getClass();
        $object = new $class($this->container);
        if ($object instanceof ActionHandlerInterface) {
            $data = $object->__run($data, $action->getConfiguration());
        }

        return $data;
    }

    public function checkConditions(Action $action, array $data): bool
    {
        $conditions = $action->getConditions();

        $result = JsonLogic::apply($conditions, $data);

        return (bool) $result;
    }

    public function handleAction(Action $action, ActionEvent $event): ActionEvent
    {
        if ($this->checkConditions($action, $event->getData())) {
            $event->setData($this->runFunction($action, $event->getData()));
            // throw events
            foreach ($action->getThrows() as $throw) {
                $this->messageBus->dispatch(new ActionEvent($throw, $event->getData()));

//            $this->throwEvent($throw);
            }
        }

        return $event;
    }

    public function handleEvent(ActionEvent $event): ActionEvent
    {
        $actions = $this->entityManager->getRepository('App:Action')->findByListens($event->getType());

        foreach ($actions as $action) {
            $this->handleAction($action, $event);
        }

        return $event;
    }
}
