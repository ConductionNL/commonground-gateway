<?php

namespace App\Service;

use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use CommonGateway\CoreBundle\Service\CallService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CatalogiService
{
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;
    private CommonGroundService $commonGroundService;
    private CallService $callService;
    private SynchronizationService $synchronizationService;
    private array $data;
    private array $configuration;
    private SymfonyStyle $io;

    public function __construct(
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        CommonGroundService $commonGroundService,
        CallService $callService,
        SynchronizationService $synchronizationService
    ) {
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->commonGroundService = $commonGroundService;
        $this->callService = $callService;
        $this->synchronizationService = $synchronizationService;
    }

    /**
     * Handles finding and adding new Catalogi.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return array
     */
    public function catalogiHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;
        $this->synchronizationService->configuration = $configuration;
        if ($this->session->get('io')) {
            $this->io = $this->session->get('io');
            $this->io->note('CatalogiService->catalogiHandler()');
        }

        // Get all Catalogi for new Catalogi.
        // todo: how do we ever remove a Catalogi? If the existing Catalogi keep adding the removed Catalogi?
        // todo: We also need to check if existing Catalogi have been changed and update them if needed.
        $newCatalogi = $this->pullCatalogi();

        // todo: we might want to move this to the componentsHandler() function, see todo there!
        if (isset($this->io)) {
            $this->io->note('CatalogiService->pullComponents()');
        }
        // Get all Components from all known Catalogi and compare them to our known Components. Add unknown ones.
        $newComponents = $this->pullComponents();

        return $data;
    }

    /**
     * @todo For now we don't use this, we could if we wanted to use a different cronjob/action to handle components than the CatalogiHandler.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     */
    private function componentsHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;
        $this->synchronizationService->configuration = $configuration;
        if ($this->session->get('io')) {
            $this->io = $this->session->get('io');
            $this->io->note('CatalogiService->componentsHandler()');
        }

        // Get all Components from all known Catalogi and compare them to our known Components. Add unknown ones.
        $newComponents = $this->pullComponents();

        return $data;
    }

    /**
     * Checks all known Catalogi (or one newly added Catalogi) for unknown/new Catalogi.
     *
     * @param array|null $newCatalogi A newly added Catalogi, default to null in this case we get all Catalogi we know.
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return array An array of all newly added Catalogi or an empty array.
     */
    private function pullCatalogi(array $newCatalogi = null): array
    {
        // Get all the Catalogi we know of or just use a single Catalogi if $newCatalogi is given.
        $knownCatalogiToCheck = $newCatalogi ? [$newCatalogi] : $this->getAllKnownCatalogi('section');

        // Check for new unknown Catalogi
        $unknownCatalogi = $this->getUnknownCatalogi($knownCatalogiToCheck);

        // Add any unknown Catalogi so we know them as well
        return $this->addNewCatalogi($unknownCatalogi);
    }

    /**
     * Get all the Catalogi we know of in this Commonground-Gateway.
     *
     * @return array An array of all Catalogi we know.
     */
    private function getAllKnownCatalogi(?string $ioType = null): array
    {
        $knownCatalogi = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $this->configuration['entity']]);

        if (isset($this->io) && $ioType !== null) {
            $totalKnownCatalogi = is_countable($knownCatalogi) ? count($knownCatalogi) : 0;
            $ioMessage = "Found $totalKnownCatalogi known Catalogi";
            $ioType === 'section' ? $this->io->section($ioMessage) : $this->io->text($ioMessage);
        }

        // Convert ObjectEntities to useable arrays
        foreach ($knownCatalogi as &$catalogi) {
            $catalogi = $catalogi->toArray();
        }

        return $knownCatalogi;
    }

    /**
     * Get all unknown Catalogi from the Catalogi we do know.
     *
     * @param array $knownCatalogiToCheck An array of Catalogi we know and want to check for new Catalogi.
     *
     * @return array An array of all Catalogi we do not know yet.
     */
    private function getUnknownCatalogi(array $knownCatalogiToCheck): array
    {
        // Get all known Catalogi, so we can check if a Catalogi already exists.
        $knownCatalogi = $this->getAllKnownCatalogi((count($knownCatalogiToCheck) > 0) ? null : 'text');
        $unknownCatalogi = [];

        if (isset($this->io)) {
            $this->io->block('Start looping through known Catalogi...');
        }

        // todo: make this a function? to decrease line breaks and prevent duplicate code, see getUnknownComponents()
        // Get the Catalogi of all the Catalogi we know of
        foreach ($knownCatalogiToCheck as $catalogi) {
            try {
                $url = $catalogi['source']['location'].$this->configuration['location'];
                if (isset($this->io)) {
                    $this->io->text("Get Catalogi from ({$catalogi['source']['name']}) \"$url\"");
                }
                $source = $this->generateCallServiceComponent($catalogi);
                $response = $this->callService->call($source, $this->configuration['location']);

                $this->entityManager->remove($source);
            } catch (Exception|GuzzleException $exception) {
                //todo: make this a function (maybe re-use it in the SynchronizationService as well?)
                if (isset($this->io)) {
                    $this->io->error("Error while doing getUnknownCatalogi for Catalogi: ({$catalogi['source']['name']}) \"{$catalogi['source']['location']}\": {$exception->getMessage()}");
                    $this->io->block("File: {$exception->getFile()}");
                    $this->io->block("Line: {$exception->getLine()}");
                    $this->io->block("Trace: {$exception->getTraceAsString()}");
                }

                // Make sure we delete the Source on error
                if (isset($source) && $source instanceof Gateway) {
                    $this->entityManager->remove($source);
                }
                //todo: error, log this
                continue;
            }

            $externCatalogi = json_decode($response->getBody()->getContents(), true);
            $unknownCatalogi = $this->checkForUnknownCatalogi($externCatalogi['results'], $knownCatalogi, $unknownCatalogi);

            if (isset($this->io)) {
                $this->io->newLine();
            }
        }

        if (isset($this->io)) {
            $this->io->block('Finished looping through known Catalogi');
        }

        return $unknownCatalogi;
    }

    /**
     * Generates a component array used by the callService when we are going to get all Catalogi of an extern Catalogi.
     *
     * @param array $catalogi A single known Catalogi.
     *
     * @return Gateway A Gateway/Source object with data used by the callService.
     */
    private function generateCallServiceComponent(array $catalogi): Gateway
    {
        $location = $catalogi['source']['location'];
        $accept = 'application/json';
        $auth = 'none';
        $name = 'temp source for CatalogiService';

        // Just in case, first try to find an existing Gateway/Source with this data. Even though we should always delete this after this function.
        $sources = $this->entityManager->getRepository('App:Gateway')->findBy(['location' => $location, 'accept' => $accept, 'auth' => $auth, 'name' => $name]);

        if (is_countable($sources) && count($sources) > 0) {
            $source = $sources[0];
        } else {
            $source = new Gateway();
            $source->setLocation($location);
            $source->setAccept($accept);
            $source->setAuth($auth);
            $source->setName($name);
        }

        return $source;
    }

    /**
     * Check for new/unknown Catalogi in the Catalogi of an extern Catalogi.
     *
     * @param array $externCatalogi  An array of all Catalogi of an extern Catalogi we know.
     * @param array $knownCatalogi   An array of all Catalogi we know.
     * @param array $unknownCatalogi An array of all Catalogi we do not know yet.
     *
     * @return array An array of all Catalogi we do not know yet.
     */
    private function checkForUnknownCatalogi(array $externCatalogi, array $knownCatalogi, array $unknownCatalogi): array
    {
        if (isset($this->io)) {
            $this->io->text('Check for unknown Catalogi...');
        }

        // Check if these extern Catalogi know any Catalogi we don't know yet
        foreach ($externCatalogi as $checkCatalogi) {
            $unknownCatalogiLocations = array_column(array_column($unknownCatalogi, 'source'), 'location');
            var_dump('unknownCatalogiLocations',$unknownCatalogiLocations);
            var_dump('checkCatalogi: '.$checkCatalogi['embedded']['source']['location']);
            // todo: make sure this array_column works, we dont want to add to $unknownCatalogi if it is already in there. If this works, delete this todo!
            if (!in_array($checkCatalogi['embedded']['source']['location'], $unknownCatalogiLocations) &&
                !$this->checkIfCatalogiExists($knownCatalogi, $checkCatalogi)) {
                $unknownCatalogi[] = $checkCatalogi;
                if (isset($this->io)) {
                    $this->io->text("Found an unknown Catalogus: ({$checkCatalogi['embedded']['source']['name']}) \"{$checkCatalogi['embedded']['source']['location']}\"");
                }
            }
        }

        return $unknownCatalogi;
    }

    /**
     * Check if a Catalogi exists in this Commonground-Gateway.
     *
     * @param array $knownCatalog  An array of all Catalogi we know.
     * @param array $checkCatalogi A single Catalogi we are going to check.
     *
     * @return bool True if we already know this Catalogi, false if not.
     */
    private function checkIfCatalogiExists(array $knownCatalog, array $checkCatalogi): bool
    {
        $catalogiIsKnown = array_filter($knownCatalog, function ($catalogi) use ($checkCatalogi) {
            //todo can we use break here? or do we need a foreach for that?
            return $catalogi['source']['location'] === $checkCatalogi['embedded']['source']['location'];
        });

        if (is_countable($catalogiIsKnown) and count($catalogiIsKnown) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Adds a new Catalogi and does a pull on this Catalogi to check for more unknown Catalogi.
     *
     * @param array $unknownCatalogi An array of all Catalogi we do not know yet.
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     */
    private function addNewCatalogi(array $unknownCatalogi): array
    {
        $totalUnknownCatalogi = is_countable($unknownCatalogi) ? count($unknownCatalogi) : 0;
        if (isset($this->io) && $totalUnknownCatalogi > 0) {
            $this->io->block("Found $totalUnknownCatalogi unknown Catalogi, start adding them...");
        }

        $addedCatalogi = [];
        // Add unknown Catalogi
//        foreach ($unknownCatalogi as $addCatalogi) {
//            $object = new ObjectEntity();
//            $object->setEntity($this->synchronizationService->getEntityFromConfig());
//            $addCatalogi['source'] = $addCatalogi['embedded']['source'];
//            $newCatalogi = $this->synchronizationService->populateObject($addCatalogi, $object);
//            $newCatalogi = $newCatalogi->toArray();
//
//            // Repeat pull for newly added Catalogi (recursion)
//            if (isset($this->io)) {
//                $this->io->text("Added Catalogi ({$newCatalogi['source']['name']}) \"{$newCatalogi['source']['location']}\"");
//                $this->io->section("Check for new Catalogi in this newly added Catalogi: ({$newCatalogi['source']['name']}) \"{$newCatalogi['source']['location']}\"");
//            }
//            $addedCatalogi = array_merge($addedCatalogi, $this->pullCatalogi($newCatalogi));
//        }

        if (isset($this->io) && $totalUnknownCatalogi > 0) {
            $this->io->block('Finished adding all new Catalogi');
        }

        return $addedCatalogi;
    }

    /**
     * @todo
     *
     * @return array
     */
    private function pullComponents(): array
    {
        // Get all the Components we know of
        $knownComponents = $this->getAllKnownComponents();

        // Check for new unknown Components
        $unknownComponents = $this->getUnknownComponents($knownComponents);

        // Add any unknown Component so we know them as well
        return $this->addNewComponent($unknownComponents);
    }

    /**
     * @todo
     *
     * @return array
     */
    private function getAllKnownComponents(): array
    {
        $knownComponents = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $this->configuration['componentsEntity']]);

        if (isset($this->io)) {
            $totalKnownComponents = is_countable($knownComponents) ? count($knownComponents) : 0;
            $this->io->section("Found $totalKnownComponents known Components");
        }

        // Convert ObjectEntities to useable arrays
        foreach ($knownComponents as &$component) {
            $component = $component->toArray(1, ['id', 'synchronizations']);
        }

        return $knownComponents;
    }

    /**
     * @todo
     *
     * @param array $knownComponents
     *
     * @return array
     */
    private function getUnknownComponents(array $knownComponents): array
    {
        // Get known Catalogi, so we can loop through them and get & check their components + synchronizations.
        $knownCatalogi = $this->getAllKnownCatalogi('text');
        $unknownComponents = [];

        if (isset($this->io)) {
            $this->io->block('Start looping through known Catalogi to get their known Components...');
        }

        // todo: make this a function? to decrease line breaks and prevent duplicate code, see getUnknownCatalogi()
        // Get the Components of all the Catalogi we know of
        foreach ($knownCatalogi as $catalogi) {
            try {
                $url = $catalogi['source']['location'].$this->configuration['componentsLocation'];
                if (isset($this->io)) {
                    $this->io->text("Get Components from (known Catalogi: {$catalogi['source']['name']}) \"$url\"");
                }
                $source = $this->generateCallServiceComponent($catalogi);
                $response = $this->callService->call($source, $this->configuration['componentsLocation'], 'GET', ['query' => ['extend[]' => 'x-commongateway-metadata.synchronizations']]);

                $this->entityManager->remove($source);
            } catch (Exception|GuzzleException $exception) {
                //todo: make this a function (maybe re-use it in the SynchronizationService as well?)
                if (isset($this->io)) {
                    $this->io->error("Error while doing getUnknownComponents for Catalogi: ({$catalogi['source']['name']}) \"{$catalogi['source']['location']}\": {$exception->getMessage()}");
                    $this->io->block("File: {$exception->getFile()}");
                    $this->io->block("Line: {$exception->getLine()}");
                    $this->io->block("Trace: {$exception->getTraceAsString()}");
                }

                //todo: error, log this
                continue;
            }

            $externComponents = json_decode($response->getBody()->getContents(), true);
            $unknownComponents = $this->checkForUnknownComponents($externComponents['results'], $knownComponents, $unknownComponents);

            if (isset($this->io)) {
                $this->io->newLine();
            }
        }

        if (isset($this->io)) {
            $this->io->block('Finished looping through known Catalogi to get their known Components');
        }

        return $unknownComponents;
    }

    /**
     * @todo
     *
     * @param array $externComponents
     * @param array $knownComponents
     * @param array $unknownComponents
     *
     * @return array
     */
    private function checkForUnknownComponents(array $externComponents, array $knownComponents, array $unknownComponents): array
    {
        if (isset($this->io)) {
            $this->io->text('Check for unknown Components...');
        }

        // Check if these extern Catalogi know any Components we don't know yet
        foreach ($externComponents as $checkComponent) {
            // todo: ... make sure we don't add to $unknownComponents if it is already in there
//            if (!in_array($checkComponent['embedded']['source']['location'], array_column($unknownComponents, 'source.location')) &&
//                !$this->checkIfComponentExists($knownComponents, $checkComponent)) {
//                $unknownComponents[] = $checkComponent;
//                if (isset($this->io)) {
//                    $this->io->text("Found an unknown Catalogus: ({$checkComponent['embedded']['source']['name']}) \"{$checkComponent['embedded']['source']['location']}\"");
//                }
//            }
        }

        return $unknownComponents;
    }

    /**
     * @todo
     *
     * @param array $unknownComponents
     *
     * @return array
     */
    private function addNewComponent(array $unknownComponents): array
    {
        //todo:
//        $totalUnknownCatalogi = is_countable($unknownCatalogi) ? count($unknownCatalogi) : 0;
//        if (isset($this->io) && $totalUnknownCatalogi > 0) {
//            $this->io->block("Found $totalUnknownCatalogi unknown Catalogi, start adding them...");
//        }
//
//        $addedCatalogi = [];
//        // Add unknown Catalogi
//        foreach ($unknownCatalogi as $addCatalogi) {
//            $object = new ObjectEntity();
//            $object->setEntity($this->synchronizationService->getEntityFromConfig());
//            $addCatalogi['source'] = $addCatalogi['embedded']['source'];
//            $newCatalogi = $this->synchronizationService->populateObject($addCatalogi, $object);
//            $newCatalogi = $newCatalogi->toArray();
//
//            // Repeat pull for newly added Catalogi (recursion)
//            if (isset($this->io)) {
//                $this->io->text("Added Catalogi ({$newCatalogi['source']['name']}) \"{$newCatalogi['source']['location']}\"");
//                $this->io->section("Check for new Catalogi in this newly added Catalogi: ({$newCatalogi['source']['name']}) \"{$newCatalogi['source']['location']}\"");
//            }
//            $addedCatalogi = array_merge($addedCatalogi, $this->pullCatalogi($newCatalogi));
//        }
//
//        if (isset($this->io) && $totalUnknownCatalogi > 0) {
//            $this->io->block('Finished adding all new Catalogi');
//        }
//
//        return $addedCatalogi;
        return [];
    }
}
