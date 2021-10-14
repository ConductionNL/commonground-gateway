<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Service\EavDocumentationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EavOasCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'eav:documentation';
    protected EavDocumentationService $eavDocumentationService;

    public function __construct(EavDocumentationService $eavDocumentationService)
    {
        $this->eavDocumentationService = $eavDocumentationService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates a the OAS files for EAV entities.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to create a OAS files for your EAV entities');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        $this->eavDocumentationService->write();

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}
