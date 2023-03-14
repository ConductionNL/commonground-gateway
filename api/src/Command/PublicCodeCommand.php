<?php

namespace App\Command;

use App\Entity\CollectionEntity;
use App\Service\OasParserService;
use App\Service\ParseDataService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @Author Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'app:load-publiccodes')]
class PublicCodeCommand extends Command
{
    protected static $defaultName = 'app:load-publiccodes';
    protected ParameterBagInterface $parameterBag;
    protected EntityManagerInterface $entityManager;
    protected OasParserService $oasParser;
    protected ParseDataService $dataService;

    public function __construct(
        ParameterBagInterface $parameterBag,
        EntityManagerInterface $entityManager,
        ParseDataService $dataService
    ) {
        $this->parameterBag = $parameterBag;
        $this->entityManager = $entityManager;
        $this->oasParser = new OasParserService($entityManager);
        $this->dataService = $dataService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('This command allows you to load a publiccode as a collection and configure it on this gateway.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user
        $publicCodeLinks = $this->parameterBag->get('PUBLICCODE');

        // Check if PUBLICCODE is not empty
        if (empty($publicCodeLinks)) {
            $output->writeln([
                '',
                'PUBLICCODE is not set. There are no API\'s to load.',
                '',
            ]);

            return Command::SUCCESS;
        }

        $publicCodeLinks = explode(',', $publicCodeLinks);

        $httpClient = new Client(['base_uri' => '']);
        foreach ($publicCodeLinks as $link) {
            $this->loadPublicCode($link, $httpClient, $output);
        }

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }

    /**
     * Creates a Collection and loads it oas with testdata.
     *
     * @param string          $publicCodeLink Link to the publiccode.
     * @param Client          $httpClient     Client to get the publiccode file with.
     * @param OutputInterface $output         OutputInterface to write in the console with.
     */
    protected function loadPublicCode(string $publicCodeLink, Client $httpClient, OutputInterface $output)
    {
        // Fetch publiccode
        try {
            $httpResponse = $httpClient->request('GET', trim($publicCodeLink, ' '));
        } catch (GuzzleException $exception) {
            $output->writeln([
                '',
                $publicCodeLink,
                $exception->getMessage(),
                '',
            ]);

            return Command::FAILURE;
        }

        // Parse yaml
        try {
            $publicCodeParsed = Yaml::parse($httpResponse->getBody()->getContents());
        } catch (ParseException $exception) {
            $output->writeln([
                '',
                $exception->getMessage(),
                '',
            ]);

            return Command::FAILURE;
        }

        // Prevent Collection from being a duplicate
        if (isset($publicCodeParsed['description']['en']['apiDocumentation'])) {
            $duplicatedCollections = $this->entityManager->getRepository(CollectionEntity::class)->findBy(['locationOAS' => $publicCodeParsed['description']['en']['apiDocumentation']]);
            if (count($duplicatedCollections) > 0) {
                return;
            }
        }

        // Create Collection
        $collection = new CollectionEntity();
        $collection->setName($publicCodeParsed['name']);
        isset($publicCodeParsed['description']['en']['shortDescription']) && $collection->setDescription($publicCodeParsed['description']['en']['shortDescription']);
        $collection->setSourceType('GitHub');
        $collection->setSourceUrl($publicCodeLink);
        isset($publicCodeParsed['description']['en']['apiDocumentation']) && $collection->setLocationOAS($publicCodeParsed['description']['en']['apiDocumentation']);
        $collection->setLoadTestData(isset($publicCodeParsed['description']['en']['testDataLocation']));
        isset($publicCodeParsed['description']['en']['testDataLocation']) && $collection->setTestDataLocation($publicCodeParsed['description']['en']['testDataLocation']);
        $this->entityManager->persist($collection);
        $this->entityManager->flush();

        // Parse and load testdata
        $collection = $this->oasParser->parseOas($collection);
        $message = 'Succesfully created collection '.$publicCodeParsed['name'].' and config loaded';
        if ($collection->getLoadTestData()) {
            $this->dataService->loadData($collection->getTestDataLocation(), $collection->getLocationOAS(), true);
            $message .= ' with testdata';
        }

        $collection = $this->entityManager->getRepository(CollectionEntity::class)->find($collection->getId());
        $collection->setSyncedAt(new \DateTime('now'));
        $this->entityManager->persist($collection);
        $this->entityManager->flush();

        // Write success message
        $output->writeln([
            '',
            $message,
            '',
        ]);
    }
}
