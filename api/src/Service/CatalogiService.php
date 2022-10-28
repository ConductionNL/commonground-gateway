<?php

namespace App\Service;

use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CallService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
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

        $newCatalogi = $this->pullCatalogi();
        // todo: new function for OC-246 here? To get all components from all known Catalogi and compare them to our known components.
        // todo: If we want to do this on an hourly basis as well^ else, create a new cronjob/action for a new handler function in this service?

        // todo: how do we ever remove a Catalogi? If the existing Catalogi keep adding the removed Catalogi?

        // todo: return $newCatalogi?
        return $data;
    }

    /**
     * Checks all known Catalogi (or one newly added Catalogi) for unknown/new Catalogi.
     *
     * @param array|null $newCatalogi A newly added Catalogi, default to null in this case we get all Catalogi we know.
     *
     * @return array An array of all newly added Catalogi or an empty array.
     */
    private function pullCatalogi(array $newCatalogi = null): array
    {
        // Get all the Catalogi we know of
        $knownCatalogi = $newCatalogi ? [$newCatalogi] : $this->getAllKnownCatalogi();

        // Check for new unknown Catalogi
        $unknownCatalogi = $this->getUnknownCatalogi($knownCatalogi);

        // Add any unknown Catalogi so we know them as well
        return $this->addNewCatalogi($unknownCatalogi);
    }

    /**
     * Get all the Catalogi we know of in this Commonground-Gateway.
     *
     * @return array An array of all Catalogi we know.
     */
    private function getAllKnownCatalogi(): array
    {
        $knownCatalogi = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $this->configuration['entity']]);

        if (isset($this->io)) {
            $totalKnownCatalogi = is_countable($knownCatalogi) ? count($knownCatalogi) : 0;
            $this->io->section("Found $totalKnownCatalogi known Catalogi");
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
     * @param array $knownCatalogi An array of all Catalogi we know.
     *
     * @return array An array of all Catalogi we do not know yet.
     */
    private function getUnknownCatalogi(array $knownCatalogi): array
    {
        $unknownCatalogi = [];

        if (isset($this->io)) {
            $this->io->block('Start looping through known Catalogi...');
        }

        // Get the Catalogi of all the Catalogi we know of
        foreach ($knownCatalogi as $catalogi) {
            try {
                $url = $catalogi['source']['location'].$this->configuration['location'];
                if (isset($this->io)) {
                    $this->io->text("Get Catalogi from ({$catalogi['source']['name']}) \"$url\"");
                }
                $source = $this->generateCallServiceComponent($catalogi);
                $response = $this->callService->call($source, $this->configuration['location']);

                $this->entityManager->remove($source);
            } catch (Exception|GuzzleException $exception) {
                if (isset($this->io)) {
                    $this->io->error("Error while doing getUnknownCatalogi for Catalogi: ({$catalogi['source']['name']}) \"{$catalogi['source']['location']}\": {$exception->getMessage()}");
                    $this->io->block("File: {$exception->getFile()}");
                    $this->io->block("Line: {$exception->getLine()}");
                    $this->io->block("Trace: {$exception->getTraceAsString()}");
                }

                //todo: try to remove $source on error? Or maybe just actually create a Source and re-use it instead of creating and deleting it all the time?
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
        $source = new Gateway();
        $source->setLocation($catalogi['source']['location']);
        $source->setAccept('application/json');
        $source->setAuth('none');
        $source->setName('temp source for CatalogiService');
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
            if (!$this->checkIfCatalogiExists($knownCatalogi, $checkCatalogi)) {
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
     * @return array An array of all newly added Catalogi.
     */
    private function addNewCatalogi(array $unknownCatalogi): array
    {
        $totalUnknownCatalogi = is_countable($unknownCatalogi) ? count($unknownCatalogi) : 0;
        if (isset($this->io) && $totalUnknownCatalogi > 0) {
            $this->io->block("Found $totalUnknownCatalogi unknown Catalogi, start adding them...");
        }

        $addedCatalogi = [];
        // Add unknown Catalogi
        foreach ($unknownCatalogi as $addCatalogi) {
            $object = new ObjectEntity();
            $object->setEntity($this->synchronizationService->getEntityFromConfig());
            $addCatalogi['source'] = $addCatalogi['embedded']['source'];
            $newCatalogi = $this->synchronizationService->populateObject($addCatalogi, $object);
            $newCatalogi = $newCatalogi->toArray();

            // Repeat pull for newly added Catalogi (recursion)
            if (isset($this->io)) {
                $this->io->text("Added Catalogi ({$newCatalogi['source']['name']}) \"{$newCatalogi['source']['location']}\"");
                $this->io->section("Check for new Catalogi in this newly added Catalogi: ({$newCatalogi['source']['name']}) \"{$newCatalogi['source']['location']}\"");
            }
            $addedCatalogi = array_merge($addedCatalogi, $this->pullCatalogi($newCatalogi));
        }

        if (isset($this->io) && $totalUnknownCatalogi > 0) {
            $this->io->block('Finished adding all new Catalogi');
        }

        return $addedCatalogi;
    }
}
