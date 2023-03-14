<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Service\ExportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Gino Kok
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class ExportConfigurationCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'export:configuration';
    protected ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Export on or more configurations as a yaml file or json file')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command exports the configuration of the api gateway into either an yaml or json file.');
        // ->addArgument('type', $this->url ? InputArgument::REQUIRED : InputArgument::OPTIONAL, 'yaml or json (defaults to yaml)')
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        $this->exportService->handleExports('all', 'file');

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}
