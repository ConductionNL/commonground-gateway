<?php

namespace App\Subscriber;

use App\ActionHandler\ActionHandlerInterface;
use App\Entity\Action;
use App\Entity\ActionLog;
use App\Event\ActionEvent;
use App\Service\ObjectEntityService;
use App\Service\LogService;
use Doctrine\ORM\EntityManagerInterface;
use JWadhams\JsonLogic;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActionSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private ContainerInterface $container;
    private ObjectEntityService $objectEntityService;
    private LogService $logService;

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
            'commongateway.action.event'    => 'handleEvent',

        ];
    }

    public function __construct(
        EntityManagerInterface $entityManager,
        ContainerInterface $container,
        ObjectEntityService $objectEntityService,
        LogService $logService)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
        $this->objectEntityService = $objectEntityService;
        $this->logService = $logService;
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
            // Create a log

            $actionLog = New ActionLog();
            $actionLog->setAction($action);
            $actionLog->setLog($this->logService->getLog());
            $actionLog->setClass($action->getClass());
            $actionLog->setDataIn($event->getData());

            // Run the event
            $event->setData($this->runFunction($action, $event->getData()));

            // Update and persist the log
            $actionLog->setDataOut($event->getData());
            $this->entityManager->persist($actionLog);
            $this->entityManager->flush();

            // throw events
            foreach ($action->getThrows() as $throw) {
                $this->objectEntityService->dispatchEvent('commongateway.action.event', $event->getData(), $throw);
            }
        }

        return $event;
    }

    public function handleEvent(ActionEvent $event): ActionEvent
    {
        // bij normaal gedrag
        if (!$event->getSubType()) {
            $actions = $this->entityManager->getRepository('App:Action')->findByListens($event->getType());
        }
        // Anders als er wel een subtype is
        else {
            $actions = $this->entityManager->getRepository('App:Action')->findByListens($event->getSubType());
        }

        foreach ($actions as $action) {
            $this->handleAction($action, $event);
        }

        return $event;
    }
}
