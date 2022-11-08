<?php

namespace App\Service;

use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Exception\GatewayException;
use CommonGateway\CoreBundle\Service\CallService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
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
        // Failsafe
        if (!Uuid::isValid($configuration['entity']) || !Uuid::isValid($configuration['componentsEntity'])) {
            return $data;
        }

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

        // todo: disabled the following code for now until this is finished and works correctly:
//
//        // todo: we might want to move this to the componentsHandler() function, see todo there!
//        if (isset($this->io)) {
//            $this->io->note('CatalogiService->pullComponents()');
//        }
//        // Get all Components from all known Catalogi and compare them to our known Components. Add unknown ones.
//        $newComponents = $this->pullComponents();

        return $data;
    }

    /**
     * @todo For now we don't use this, we could if we wanted to use a different cronjob/action to handle components than the CatalogiHandler.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return array
     */
    private function componentsHandler(array $data, array $configuration): array
    {
        // Failsafe
        if (!Uuid::isValid($configuration['componentsEntity'])) {
            return $data;
        }

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
            $knownCatalogiToCheckCount = count($knownCatalogiToCheck);
            $this->io->block("Start looping through $knownCatalogiToCheckCount known Catalogi to check for unknown Catalogi...");
        }

        // Get the Catalogi of all the Catalogi we know of
        foreach ($knownCatalogiToCheck as $catalogi) {
            // todo: make this a function? to decrease line breaks and prevent duplicate code, see getUnknownComponents()
            // todo: also use recursion to deal with pages on GET $url!
            try {
                $url = $catalogi['source']['location'].$this->configuration['location'];
                if (isset($this->io)) {
                    $this->io->text("Get Catalogi from ({$catalogi['source']['name']}) \"$url\"");
                }
                $source = $this->getOrCreateSourceForCatalogi($catalogi);
                $response = $this->callService->call($source, $this->configuration['location']);
            } catch (Exception|GuzzleException $exception) {
                $this->synchronizationService->ioCatchException($exception, ['trace', 'line', 'file', 'message' => [
                    'type'       => 'error',
                    'preMessage' => "Error while doing getUnknownCatalogi for Catalogi: ({$catalogi['source']['name']}) \"{$catalogi['source']['location']}\": ",
                ]]);
                //todo: error, log this
                continue;
            }

            $externCatalogi = json_decode($response->getBody()->getContents(), true);
            if (!isset($externCatalogi['results'])) {
                if (isset($this->io)) {
                    $this->io->warning("No \'results\' found in response from \"$url\"");
                }
                continue;
            }
            if (isset($this->io) && is_countable($externCatalogi['results'])) {
                $externCatalogiCount = count($externCatalogi['results']);
                $this->io->text("Found $externCatalogiCount Catalogi in Catalogi: ({$catalogi['source']['name']}) \"{$catalogi['source']['location']}\"");
            }
            $unknownCatalogi = $this->checkForUnknownCatalogi($externCatalogi['results'], $knownCatalogi, $unknownCatalogi);

            if (isset($this->io)) {
                $this->io->newLine();
            }
        }

        if (isset($this->io)) {
            $this->io->block("Finished looping through $knownCatalogiToCheckCount known Catalogi to check for unknown Catalogi");
        }

        return $unknownCatalogi;
    }

    /**
     * Tries to find an existing Source for the given Catalogi and if it can't be found creates a new one.
     * Used by the callService when we are going to get all Catalogi of an extern Catalogi.
     *
     * @param array $catalogi A single known Catalogi.
     *
     * @return Gateway A Gateway/Source object with data used by the callService.
     */
    private function getOrCreateSourceForCatalogi(array $catalogi): Gateway
    {
        $location = $catalogi['source']['location'];
        $accept = 'application/json';
        $auth = 'none';
        $name = "Source for Catalogi {$catalogi['source']['name']}";

        // First try to find an existing Gateway/Source with this location.
        $sources = $this->entityManager->getRepository('App:Gateway')->findBy(['location' => $location]);

        if (is_countable($sources) && count($sources) > 0) {
            $source = $sources[0];
        } else {
            // Create a new Source for this Catalogi
            $source = new Gateway();
            $source->setLocation($location);
            $source->setAccept($accept);
            $source->setAuth($auth);
            $source->setName($name);
            $this->entityManager->persist($source);
            $this->entityManager->flush();
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
            $this->io->text('Checking for unknown Catalogi...');
        }

        // Keep track of locations we already are going to add new Catalogi for.
        $unknownCatalogiLocations = array_column(array_column(array_column($unknownCatalogi, 'embedded'), 'source'), 'location');

        // Check if these extern Catalogi know any Catalogi we don't know yet
        foreach ($externCatalogi as $checkCatalogi) {
            // We dont want to add to $unknownCatalogi if it is already in there. Use $unknownCatalogiLocations to check for this.
            if (!in_array($checkCatalogi['embedded']['source']['location'], $unknownCatalogiLocations) &&
                !$this->checkIfCatalogiExists($knownCatalogi, $checkCatalogi)) {
                $unknownCatalogi[] = $checkCatalogi;
                // Make sure to also add this to $unknownCatalogiLocations
                $unknownCatalogiLocations[] = $checkCatalogi['embedded']['source']['location'];
                if (isset($this->io)) {
                    $this->io->text("Found an unknown Catalogi: ({$checkCatalogi['embedded']['source']['name']}) \"{$checkCatalogi['embedded']['source']['location']}\"");
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
        if ($totalUnknownCatalogi > 0) {
            $entity = $this->synchronizationService->getEntityFromConfig();
        }
        // Add unknown Catalogi
        foreach ($unknownCatalogi as $addCatalogi) {
            if (isset($this->io)) {
                $this->io->text("Start adding Catalogi ({$addCatalogi['embedded']['source']['name']}) \"{$addCatalogi['embedded']['source']['location']}\"");
            }
            $object = new ObjectEntity();
            $object->setEntity($entity);
            $addCatalogi['source'] = $addCatalogi['embedded']['source'];
            $newCatalogi = $this->synchronizationService->populateObject($addCatalogi, $object);
            $newCatalogi = $newCatalogi->toArray();

            // Repeat pull for newly added Catalogi (recursion)
            if (isset($this->io)) {
                $this->io->text("Added Catalogi ({$newCatalogi['source']['name']}) \"{$newCatalogi['source']['location']}\"");
                $this->io->section("Check for new Catalogi in this newly added Catalogi: ({$newCatalogi['source']['name']}) \"{$newCatalogi['source']['location']}\"");
            }
            $addedCatalogi[] = $newCatalogi;
            $addedCatalogi = array_merge($addedCatalogi, $this->pullCatalogi($newCatalogi));
        }

        if (isset($this->io) && $totalUnknownCatalogi > 0) {
            $this->io->block('Finished adding all new Catalogi');
        }

        return $addedCatalogi;
    }

    /**
     * @todo
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return array
     */
    private function pullComponents(): array
    {
        // Get the locations of all the Components we know of
        $knownComponentLocations = $this->getAllKnownComponentLocations();

        // Check for new unknown Components
        $unknownComponents = $this->getUnknownComponents($knownComponentLocations);

        // Add any unknown Component so we know them as well
        $newComponents = $this->addNewComponents($unknownComponents);

        // todo: update/sync all existing components with the SynchronizationService->handleSync() function?
        // todo: Async^ ?

        return $newComponents;
    }

    /**
     * @todo
     *
     * @return array
     */
    private function getAllKnownComponentLocations(): array
    {
        $knownComponents = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $this->configuration['componentsEntity']]);

        if (isset($this->io)) {
            $totalKnownComponents = is_countable($knownComponents) ? count($knownComponents) : 0;
            $this->io->section("Found $totalKnownComponents known Component".($totalKnownComponents !== 1 ? 's' : ''));
        }

        // Convert ObjectEntities to useable arrays
        $domain = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' ? 'https://'.$_SERVER['HTTP_HOST'] : 'http://localhost';
        foreach ($knownComponents as &$component) {
            $component = $component->toArray(1, ['id', 'synchronizations', 'self']);
            $component = $this->getComponentLocation($component, $domain);
        }

        return $knownComponents;
    }

    /**
     * @todo
     *
     * @param array  $component
     * @param string $catalogiLocation
     *
     * @return string
     */
    private function getComponentLocation(array $component, string $catalogiLocation): string
    {
        // todo: always key=0?
        if (isset($component['x-commongateway-metadata']['synchronizations'][0])) {
            $componentSync = $component['x-commongateway-metadata']['synchronizations'][0];

            return $componentSync['gateway']['location'].$componentSync['endpoint'].$componentSync['sourceId'];
        } elseif (isset($component['x-commongateway-metadata']['self']) &&
            str_contains($component['x-commongateway-metadata']['self'], $this->configuration['componentsLocation'])) {
            return $catalogiLocation.$component['x-commongateway-metadata']['self'];
        }

        return $catalogiLocation.$this->configuration['componentsLocation'].$component['id'];
    }

    /**
     * @todo
     *
     * @param array $knownComponentLocations
     *
     * @return array
     */
    private function getUnknownComponents(array $knownComponentLocations): array
    {
        // Get known Catalogi, so we can loop through them and get & check their components + synchronizations.
        $knownCatalogi = $this->getAllKnownCatalogi('text');
        $unknownComponents = [];

        if (isset($this->io)) {
            $this->io->block('Start looping through known Catalogi to get and check their known Components...');
        }

        // Get the Components of all the Catalogi we know of
        foreach ($knownCatalogi as $catalogi) {
            // todo: make this a function? to decrease line breaks and prevent duplicate code, see getUnknownCatalogi()
            // todo: also use recursion to deal with pages on GET $url!
            try {
                $url = $catalogi['source']['location'].$this->configuration['componentsLocation'];
                if (isset($this->io)) {
                    $this->io->text("Get Components from (known Catalogi: {$catalogi['source']['name']}) \"$url\"");
                }
                $source = $this->getOrCreateSourceForCatalogi($catalogi);
                $response = $this->callService->call($source, $this->configuration['componentsLocation'], 'GET', ['query' => [
                    'extend' => ['x-commongateway-metadata.synchronizations', 'x-commongateway-metadata.self', 'x-commongateway-metadata.dateModified'],
                ]]);
            } catch (Exception|GuzzleException $exception) {
                $this->synchronizationService->ioCatchException($exception, ['trace', 'line', 'file', 'message' => [
                    'type'       => 'error',
                    'preMessage' => "Error while doing getUnknownComponents for Catalogi: ({$catalogi['source']['name']}) \"{$catalogi['source']['location']}\": ",
                ]]);

                //todo: error, log this
                continue;
            }

            $externComponents = json_decode($response->getBody()->getContents(), true);
            if (!isset($externComponents['results'])) {
                if (isset($this->io)) {
                    $this->io->warning("No \'results\' found in response from \"$url\"");
                }
                continue;
            }
            if (isset($this->io) && is_countable($externComponents['results'])) {
                $externComponentsCount = count($externComponents['results']);
                $this->io->text("Found $externComponentsCount Components in Catalogi: ({$catalogi['source']['name']}) \"{$catalogi['source']['location']}\"");
            }
            $unknownComponents = $this->checkForUnknownComponents($externComponents['results'], $knownComponentLocations, $unknownComponents, $catalogi['source']['location']);

            if (isset($this->io)) {
                $this->io->newLine();
            }
        }

        if (isset($this->io)) {
            $this->io->block('Finished looping through known Catalogi to get and check their known Components');
        }

        return $unknownComponents;
    }

    /**
     * @todo
     *
     * @param array  $externComponents
     * @param array  $knownComponentLocations
     * @param array  $unknownComponents
     * @param string $catalogiLocation
     *
     * @return array
     */
    private function checkForUnknownComponents(array $externComponents, array $knownComponentLocations, array $unknownComponents, string $catalogiLocation): array
    {
        if (isset($this->io)) {
            $this->io->text('Checking for unknown Components...');
        }

        // Keep track of locations we already are going to add new Components for.
        $unknownComponentsLocations = [];
        foreach ($unknownComponents as $unknownComponent) {
            $unknownComponentsLocations[] = $this->getComponentLocation($unknownComponent, $catalogiLocation);
        }

        // Check if these extern Catalogi know any Components we don't know yet
        foreach ($externComponents as $checkComponent) {
            // We dont want to add to $unknownComponents if it is already in there. Use $unknownComponentsLocations to check for this.
            $checkComponentLocation = $this->getComponentLocation($checkComponent, $catalogiLocation);
            if (!in_array($checkComponentLocation, $unknownComponentsLocations) &&
                !in_array($checkComponentLocation, $knownComponentLocations)) {
                $unknownComponents[] = $checkComponent;
                // Make sure to also add this to $unknownComponentsLocations
                $unknownComponentsLocations[] = $checkComponentLocation;
                if (isset($this->io)) {
                    $this->io->text("Found an unknown Component: ({$checkComponent['name']}) \"$checkComponentLocation\"");
                }
            }
//            elseif (isset($this->io)) {
//                $this->io->text("Already known Component: ({$checkComponent['name']}) \"$checkComponentLocation\"");
//            }
        }

        return $unknownComponents;
    }

    /**
     * @todo
     *
     * @param array $unknownComponents
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return array
     */
    private function addNewComponents(array $unknownComponents): array
    {
        $totalUnknownComponents = is_countable($unknownComponents) ? count($unknownComponents) : 0;
        if (isset($this->io) && $totalUnknownComponents > 0) {
            $this->io->block("Found $totalUnknownComponents unknown Component".($totalUnknownComponents !== 1 ? 's' : '').', start adding them...');
        }

        $addedComponents = [];
        if ($totalUnknownComponents > 0) {
            $entity = $this->synchronizationService->getEntityFromConfig('componentsEntity');
        }
        // Add unknown Components
        foreach ($unknownComponents as $addComponent) {
            if (isset($this->io)) {
                $url = $this->getComponentLocation($addComponent, '...');
                $this->io->text("Start adding Component ({$addComponent['name']}) \"$url\"");
            }
            $object = new ObjectEntity();
            $object->setEntity($entity);
            $addComponentWithMetadata = $addComponent;
            unset($addComponent['x-commongateway-metadata']); // todo: not sure if this is needed before populateObject
            // todo: remove/replace embedded before populateObject... Try with hydrate functie
//            $addComponent = $this->synchronizationService->replaceEmbedded($addComponent);
            $newComponent = $this->synchronizationService->populateObject($addComponent, $object);
            // todo: get correct source for createSyncForComponent function. Will be a Catalogi source or a other/new source we need to create here ?
            $synchronization = $this->createSyncForComponent(['object' => $newComponent, 'source' => 'todo', 'entity' => $entity], $addComponentWithMetadata);
            $newComponent = $newComponent->toArray();

            if (isset($this->io)) {
                $this->io->text("Added Component ({$addComponent['name']}) \"$url\"");
            }
            $addedComponents[] = $newComponent;
        }

        if (isset($this->io) && $totalUnknownComponents > 0) {
            $this->io->block('Finished adding all new Components');
        }

        return $addedComponents;
    }

    /**
     * @todo
     *
     * @param array $data         An array containing an 'object' => ObjectEntity, 'source' => Gateway & 'entity' => Entity.
     * @param array $addComponent
     *
     * @throws Exception
     *
     * @return Synchronization
     */
    private function createSyncForComponent(array $data, array $addComponent): Synchronization
    {
        $componentMetaData = $addComponent['x-commongateway-metadata'];
        $componentSync = $componentMetaData['synchronizations'][0] ?? null; // todo: always key=0?

        $synchronization = new Synchronization();
        $synchronization->setObject($data['object']);
        $synchronization->setGateway($data['source']);
        $synchronization->setEntity($data['entity']);
        $synchronization->setEndpoint($componentSync ? $componentSync['endpoint'] : $this->configuration['componentsLocation']);
        $synchronization->setSourceId($componentSync ? $componentSync['sourceId'] : $addComponent['id']);
        $now = new DateTime();
        $synchronization->setLastChecked($now);
        $synchronization->setLastSynced($now);
        $synchronization->setSourcelastChanged(
            $componentSync ?
            new DateTime($componentSync['sourceLastChanged']) :
            (
                $componentMetaData['dateModified'] ?
                new DateTime($componentMetaData['dateModified']) :
                $now
            )
        );
        unset($addComponent['x-commongateway-metadata']); // todo: not sure if this is needed before we hash?
        // todo: make a choice how we hash this, it has to always be the same type of data in the hash so we can correctly compare it later
        $synchronization->setHash(hash('sha384', serialize($addComponent)));
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        return $synchronization;
    }
}
