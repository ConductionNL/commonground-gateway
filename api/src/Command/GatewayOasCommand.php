<?php

// src/Command/CreateUserCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\GatewayDocumentationService;
use Symfony\Component\Console\Helper\ProgressBar;

class GatewayOasCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'gateway:documentation';
    protected GatewayDocumentationService $gatewayDocumentationService;


    public function __construct(GatewayDocumentationService $gatewayDocumentationService)
    {
        $this->gatewayDocumentationService = $gatewayDocumentationService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates a the OAS files for EAV entities.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to create a OAS files for your EAV entities')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable
        $output->writeln([
            'Gateway Document Creator',
            '============',
            'This script will get the OAS documentation for gateway\'s (if provided) and parse them to usable information in order to help user configure gateways',
        ]);

        $gateways = $this->em->getRepository('App:Gateway')->findAll();

        $output->writeln('Found '.count($gateways).' gateways to parse');

        // creates a new progress bar (50 units)
        $progressBar = new ProgressBar($output, count($gateways));

        // starts and displays the progress bar
        $progressBar->start();

        foreach ($gateways as $gateway){
            $gateway = $this->getPathsForGateway->getPaths($gateway);
            $progressBar->advance();
        }


        // (it's equivalent to returning int(0))

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}
