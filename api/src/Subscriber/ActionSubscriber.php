<?php

namespace App\Subscriber;

use App\ActionHandler\ActionHandlerInterface;
use App\Entity\Action;
use App\Entity\Endpoint;
use App\Event\ActionEvent;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use JWadhams\JsonLogic;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class ActionSubscriber implements EventSubscriberInterface
{

    private EntityManagerInterface $entityManager;

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

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function throwEvent(string $throw): void
    {

    }

    public function runFunction(Action $action, array $data): array
    {
        $class = $action->getClass();
        $object = new $class($this->entityManager);
        if($object instanceof ActionHandlerInterface)
            $data = $object->__run($data, $action->getConfiguration());
        return $data;
    }

    public function triggerActions(Action $action): void
    {
        foreach ($action->getThrows() as $throw) {
            $this->throwEvent($throw);
        }
    }

    public function checkConditions(Action $action, array $data): bool
    {
        $conditions = $action->getConditions();

        $result = JsonLogic::apply($conditions, $data);

        return (bool) $result;
    }

    public function handleAction(Action $action, ActionEvent $event): ActionEvent
    {
        if($this->checkConditions($action, $event->getData())){
            $event->setData($this->runFunction($action, $event->getData()));
            $this->triggerActions($action);
        }

        return $event;
    }

    public function handleEvent(ActionEvent $event): ActionEvent
    {
        $actions = $this->entityManager->getRepository("App:Action")->findByListens($event->getType());

        foreach($actions as $action) {
            $this->handleAction($action, $event);
        }

        return $event;
    }
}
