<?php

// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Service\EavDocumentationService;
use App\Service\OasDocumentationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class EavOasCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'eav:documentation';
    protected OasDocumentationService $oasDocumentationService;
    private EavDocumentationService $eavDocumentationService;
    private CacheInterface $customThingCache;

    public function __construct(OasDocumentationService $oasDocumentationService, EavDocumentationService $eavDocumentationService, CacheInterface $customThingCache)
    {
        $this->oasDocumentationService = $oasDocumentationService;
        $this->eavDocumentationService = $eavDocumentationService;
        $this->customThingCache = $customThingCache;

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
        $itemJson = $this->customThingCache->getItem('oas_'.md5(null).'_json');
        $itemYaml = $this->customThingCache->getItem('oas_'.md5(null).'_yaml');
        $oas = $this->eavDocumentationService->getRenderDocumentation();
        $json = json_encode($oas);
        $yaml = Yaml::dump($oas);

        // Let's stuff it into the cache
        $itemJson->set($json);
        $itemYaml->set($yaml);
        $this->customThingCache->save($itemJson);
        $this->customThingCache->save($itemYaml);

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}
