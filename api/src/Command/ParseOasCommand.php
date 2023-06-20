<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Service\OasParserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class ParseOasCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'parse:oas';
    protected OasParserService $oasParserService;

    public function __construct(OasParserService $oasParserService, string $url = '', string $gateway = '')
    {
        $this->oasParserService = $oasParserService;
        $this->url = $url;
        $this->gateway = $gateway;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Turn an OAS file into a set of EAV entities')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command imports an external OAS file into the EAC system.')
            ->addArgument('url', $this->url ? InputArgument::REQUIRED : InputArgument::OPTIONAL, 'The url location of the OAS documentation that you want to import')
            ->addArgument('gateway', $this->gateway ? InputArgument::REQUIRED : InputArgument::OPTIONAL, 'The uuid of the gateway that you want to use');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        $this->oasParserService->getOAS($input->getArgument('url'));

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}
