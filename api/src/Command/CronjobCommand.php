<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Entity\Cronjob;
use App\Event\ActionEvent;
use Cron\CronExpression;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CronjobCommand extends Command
{
    private InputInterface $input;
    private OutputInterface $output;
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'cronjob:command';
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private SessionInterface $session;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        SessionInterface $session
    ) {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->session = $session;

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
     * @param Cronjob      $cronjob
     * @param SymfonyStyle $io
     *
     * @throws Exception
     */
    public function makeActionEvent(Cronjob $cronjob, SymfonyStyle $io): void
    {
        $totalThrows = $cronjob->getThrows() ? count($cronjob->getThrows()) : 0;
        $io->section("Found $totalThrows Throw".($totalThrows !== 1 ? 's' : '').' for this Cronjob');

        ProgressBar::setFormatDefinition('throwProgressBar', ' %current%/%max% ------ %message%');
        $throwProgressBar = new ProgressBar($this->output, $totalThrows);
        $throwProgressBar->setFormat('throwProgressBar');
        $throwProgressBar->setMaxSteps($totalThrows);
        $throwProgressBar->setMessage('Start looping through all Throws of this Cronjob');
        $throwProgressBar->start();
        $io->newLine();
        $io->newLine();

        $throws = $cronjob->getThrows();
        foreach ($throws as $key => $throw) {
            $io->block("Dispatch ActionEvent for Throw: \"$throw\"");
            $this->session->set('currentCronJobThrow', $throw);
            $actionEvent = new ActionEvent($throw, ($cronjob->getData()));
            $this->eventDispatcher->dispatch($actionEvent, $actionEvent->getType());

            $io->comment("Get crontab expression ({$cronjob->getCrontab()}) and set the next and last run properties of the Cronjob");
            $cronExpression = new CronExpression($cronjob->getCrontab());
            $cronjob->setNextRun($cronExpression->getNextRunDate());
            $cronjob->setLastRun(new \DateTime('now'));

            $io->comment('Save Cronjob in the database');
            $this->entityManager->persist($cronjob);
            $this->entityManager->flush();

            if ($key !== array_key_last($throws)) {
                $throwProgressBar->setMessage('Looping through Throws of current Cronjob...');
                $throwProgressBar->advance();
                $io->newLine();
            }
            $io->newLine();
            $this->session->remove('currentCronJobThrow');
        }
        $throwProgressBar->setMessage('Finished looping through all Throws of this Cronjob');
        $throwProgressBar->finish();
        $io->newLine();
        $io->newLine();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $io = new SymfonyStyle($input, $output);
        $this->session->set('io', $io);

        $cronjobs = $this->entityManager->getRepository('App:Cronjob')->getRunnableCronjobs();
        $total = is_countable($cronjobs) ? count($cronjobs) : 0;

        $io->title('Run all Cronjobs');
        $io->section("Found $total runnable Cronjob".($total !== 1 ? 's' : ''));
        $io->progressStart($total);
        $io->newLine();

        $errorCount = 0;
        if ($cronjobs !== null) {
            foreach ($cronjobs as $cronjob) {
                try {
                    $this->handleCronjobIoStart($io, $cronjob);

                    $this->makeActionEvent($cronjob, $io);

                    $this->handleCronjobIoFinish($io, $cronjob);
                } catch (Exception $exception) {
                    $io->error("Stopped running this cronjob because of the following error: {$exception->getMessage()}");
                    $io->block("Code: {$exception->getCode()}");
                    $io->block("File: {$exception->getFile()}");
                    $io->block("Line: {$exception->getLine()}");
                    $io->block("Trace: {$exception->getTraceAsString()}");
                    $errorCount++;
                }

                $io->progressAdvance();
            }
        }

        $io->progressFinish();

        return $this->handleExecuteResponse($io, $errorCount, $total);
    }

    /**
     * Write user feedback to $io before handling a Cronjob.
     *
     * @param SymfonyStyle $io
     * @param Cronjob      $cronjob
     *
     * @return void
     */
    private function handleCronjobIoStart(SymfonyStyle $io, Cronjob $cronjob)
    {
        $io->newLine();
        $io->definitionList(
            'Start running the following Cronjob',
            new TableSeparator(),
            ['Id'          => $cronjob->getId()->toString()],
            ['Name'        => $cronjob->getName()],
            ['Description' => $cronjob->getDescription()],
            ['Crontab'     => $cronjob->getCrontab()],
            ['Throws'      => implode(', ', $cronjob->getThrows())],
//                    ['Data' => "[{$this->objectEntityService->implodeMultiArray($cronjob->getData())}]"],
            ['LastRun' => $cronjob->getLastRun() ? $cronjob->getLastRun()->format('Y-m-d H:i:s') : null],
            ['NextRun' => $cronjob->getNextRun() ? $cronjob->getNextRun()->format('Y-m-d H:i:s') : null],
        );
    }

    /**
     * Write user feedback to $io after handling a Cronjob.
     *
     * @param SymfonyStyle $io
     * @param Cronjob      $cronjob
     *
     * @return void
     */
    private function handleCronjobIoFinish(SymfonyStyle $io, Cronjob $cronjob)
    {
        $io->definitionList(
            'Finished running the following cronjob',
            new TableSeparator(),
            ['Id'      => $cronjob->getId()->toString()],
            ['Name'    => $cronjob->getName()],
            ['LastRun' => $cronjob->getLastRun() ? $cronjob->getLastRun()->format('Y-m-d H:i:s') : null],
            ['NextRun' => $cronjob->getNextRun() ? $cronjob->getNextRun()->format('Y-m-d H:i:s') : null],
        );
    }

    /**
     * Determine the response of executing this command. Response depends on te amount of errors in percentage.
     * If more than 20% failed will return Failure = 1. Else returns Succes = 0.
     * Will also send a final message with SymfonyStyle $io as user feedback, depending on the failure rate this will be a Success, Warning or Error message.
     *
     * @param SymfonyStyle $io
     * @param int          $errorCount
     * @param int          $total
     *
     * @return int
     */
    private function handleExecuteResponse(SymfonyStyle $io, int $errorCount, int $total): int
    {
        $errors = $total == 0 ? 0 : (round($errorCount / $total * 100) == 0 && $errorCount > 0 ? 1 : round($errorCount / $total * 100));
        if ($errors == 0) {
            $io->success('Successfully finished running all Cronjobs');
        } elseif ($errors < 20) {
            $io->warning("Some Cronjobs did not run successfully. Failure rate is $errors%");
        } else {
            $io->error("A lot of Cronjobs did not run successfully. Failure rate is $errors%");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
