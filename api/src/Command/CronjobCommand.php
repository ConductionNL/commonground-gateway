<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Entity\Cronjob;
use App\Event\ActionEvent;
use Cron\CronExpression;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CronjobCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'cronjob:command';
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates a cronjob and set the action events on the stack')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to create a cronjob');
    }

    /**
     * This function makes action events.
     *
     * @param Cronjob $cronjob
     *
     * @throws Exception
     */
    public function makeActionEvent(Cronjob $cronjob): void
    {
        foreach ($cronjob->getThrows() as $throw) {
            $actionEvent = new ActionEvent($throw, ($cronjob->getData()));
            $this->eventDispatcher->dispatch($actionEvent, $actionEvent->getType());

            // Get crontab expression and set the next and last run properties
            // Save cronjob in the database
            $cronExpression = new CronExpression($cronjob->getCrontab());
            $cronjob->setNextRun($cronExpression->getNextRunDate());
            $cronjob->setLastRun(new \DateTime('now'));

            $this->entityManager->persist($cronjob);
            $this->entityManager->flush();
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cronjobs = $this->entityManager->getRepository('App:Cronjob')->getRunnableCronjobs();

        if ($cronjobs !== null) {
            foreach ($cronjobs as $cronjob) {
                $this->makeActionEvent($cronjob);
            }
        }

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}
