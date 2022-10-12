<?php

namespace App\Subscriber;

use App\ActionHandler\ActionHandlerInterface;
use App\Entity\Action;
use App\Event\ActionEvent;
use App\Service\ObjectEntityService;
use Doctrine\ORM\EntityManagerInterface;
use JWadhams\JsonLogic;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ActionSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private ContainerInterface $container;
    private ObjectEntityService $objectEntityService;
    private SessionInterface $session;
    private SymfonyStyle $io;

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

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container, ObjectEntityService $objectEntityService, SessionInterface $session)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
        $this->objectEntityService = $objectEntityService;
        $this->session = $session;
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
                if (isset($this->io)) {
                    $this->io->info("Action {$action->getName()} is lockable and locked = {$action->getLocked()}");
                }
                return $event;
            }
        }

        if (JsonLogic::apply($action->getConditions(), $event->getData())) {
            //todo: make this a function
            if (isset($this->io) &&
                $this->session->get('currentCronJobThrow') &&
                $this->session->get('currentCronJobThrow') === $event->getType()
            ) {
                $currentCronJobThrow = true;
                $this->io->definitionList(
                    'The conditions of the following Action match with the ActionEvent data',
                    new TableSeparator(),
                    ['Id' => $action->getId()->toString()],
                    ['Name' => $action->getName()],
                    ['Description' => $action->getDescription()],
                    ['Listens' => implode(", ", $action->getListens())],
                    ['Throws' => implode(", ", $action->getThrows())],
                    ['Class' => $action->getClass()],
                    ['Priority' => $action->getPriority()],
                    ['Async' => is_null($action->getAsync()) ? null: ($action->getAsync() ? 'True' : 'False')],
                    ['IsLockable' => is_null($action->getIsLockable()) ? null: ($action->getIsLockable() ? 'True' : 'False')],
                    ['LastRun' => $action->getLastRun() ? $action->getLastRun()->format('Y-m-d H:i:s') : null],
                    ['LastRunTime' => $action->getLastRunTime()],
                    ['Status' => is_null($action->getStatus()) ? null: ($action->getStatus() ? 'True' : 'False')],
                );
            } elseif (isset($this->io)) {
                $currentCronJobThrow = false;
                $this->io->text("The conditions of the Action {$action->getName()} match with the 'sub'-ActionEvent data");
            }

            $event->setData($this->runFunction($action, $event->getData()));

            //todo: make this a function
            if (isset($this->io) && $currentCronJobThrow) {
                $this->io->definitionList(
                    'Finished handling the following Action that matched the ActionEvent data',
                    new TableSeparator(),
                    ['Id' => $action->getId()->toString()],
                    ['Name' => $action->getName()],
                    ['LastRun' => $action->getLastRun() ? $action->getLastRun()->format('Y-m-d H:i:s') : null],
                    ['LastRunTime' => $action->getLastRunTime()],
                    ['Status' => is_null($action->getStatus()) ? null: ($action->getStatus() ? 'True' : 'False')],
                );
            } elseif (isset($this->io)) {
                $this->io->text("Finished handling the Action {$action->getName()} that matched the 'sub'-ActionEvent data");
            }
            // throw events
            foreach ($action->getThrows() as $throw) {
                $this->objectEntityService->dispatchEvent('commongateway.action.event', $event->getData(), $throw);
            }
        }

        return $event;
    }

    public function handleEvent(ActionEvent $event): ActionEvent
    {
        if ($this->session->get('io')) {
            $this->io = $this->session->get('io');
            if ($this->session->get('currentCronJobThrow') && $this->session->get('currentCronJobThrow') === $event->getType()) {
                $currentCronJobThrow = true;
                $this->io->section("Handle ActionEvent \"{$event->getType()}\"".($event->getSubType() ? " With SubType: \"{$event->getSubType()}\"" : ''));
            } else {
                $currentCronJobThrow = false;
                $this->io->text("Handle 'sub'-ActionEvent \"{$event->getType()}\"".($event->getSubType() ? " With SubType: \"{$event->getSubType()}\"" : ''));
            }
        }

        // bij normaal gedrag
        if (!$event->getSubType()) {
            $actions = $this->entityManager->getRepository('App:Action')->findByListens($event->getType());
        }
        // Anders als er wel een subtype is
        else {
            $actions = $this->entityManager->getRepository('App:Action')->findByListens($event->getSubType());
        }

        $totalActions = is_countable($actions) ? count($actions) : 0;
        if (isset($this->io)) {
            $ioMessage = "Found $totalActions Action".($totalActions !== 1 ?'s':'')." listening to \"{$event->getType()}\"";
            $currentCronJobThrow ? $this->io->block($ioMessage) : null; // todo: optionally add $this->io->text($ioMessage) instead of null
        }
        foreach ($actions as $action) {
            $this->handleAction($action, $event);
        }

        return $event;
    }
}
