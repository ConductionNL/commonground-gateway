<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Service\GatewayDocumentationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class GatewayOasCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'gateway:documentation';
    protected GatewayDocumentationService $gatewayDocumentationService;
    protected EntityManagerInterface $em;

    public function __construct(GatewayDocumentationService $gatewayDocumentationService, EntityManagerInterface $em)
    {
        $this->gatewayDocumentationService = $gatewayDocumentationService;
        $this->em = $em;

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

        $rows = [];

        foreach ($gateways as $gateway) {
            $gateway = $this->gatewayDocumentationService->getPathsForGateway($gateway);
            $this->em->persist($gateway);
            $progressBar->advance();
            //$this->em->persist($gateway);

            // Lets build a nice table
            foreach ($gateway->getPaths() as $key=>$path) {
                if (!array_key_exists('properties', $path)) {
                    $path['properties'] = [];
                } // want we kennen schemas zonder properties
                $rows[] = [$gateway->getName(), $key, count($path['properties'])];
            }
        }

        $output->writeln('Found the following paths to parse');

        $table = new Table($output);
        $table
            ->setHeaders(['Gateway', 'Path', 'Properties'])
            ->setRows($rows);
        $table->render();

        $this->em->flush();

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
