<?php

namespace App\Subscriber;

use App\ActionHandler\ActionHandlerInterface;
use App\Entity\Action;
use App\Event\ActionEvent;
use App\Service\ObjectEntityService;
use Doctrine\ORM\EntityManagerInterface;
use JWadhams\JsonLogic;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActionSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private ContainerInterface $container;
    private ObjectEntityService $objectEntityService;

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

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container, ObjectEntityService $objectEntityService)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
        $this->objectEntityService = $objectEntityService;
    }

    public function runFunction(Action $action, array $data): array
    {
        // Is the action is lockable we need to lock it
        if ($action->getIsLockable()) {
            $action->setLocked(new \DateTime());
            $this->entityManager->persist($action);
            $this->entityManager->flush();
        }

        $class = $action->getClass();
        $object = new $class($this->container);

        // timer starten
        $startTimer = microtime(true);
        if ($object instanceof ActionHandlerInterface) {
            $data = $object->__run($data, $action->getConfiguration());
        }
        // timer stoppen
        $stopTimer = microtime(true);

        // Is the action is lockable we need to unlock it
        if ($action->getIsLockable()) {
            $action->setLocked(null);
        }

        $totalTime = $stopTimer - $startTimer;

        // Let's set some results
        $action->setLastRun(new \DateTime());
        $action->setLastRunTime($totalTime);
        $action->setStatus(true); // this needs some refinement
        $this->entityManager->persist($action);
        $this->entityManager->flush();

        return $data;
    }

    public function handleAction(Action $action, ActionEvent $event): ActionEvent
    {
        // Lets see if the action prefents concurency
        if ($action->getIsLockable()) {
            // bijwerken uit de entity manger
            $this->entityManager->refresh($action);

            if ($action->getLocked()) {
                return $event;
            }
        }

        if (JsonLogic::apply($action->getConditions(), $event->getData())) {
            $event->setData($this->runFunction($action, $event->getData()));
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
