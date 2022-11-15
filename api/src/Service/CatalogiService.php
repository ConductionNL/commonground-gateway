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
     * Handles finding and adding unknown Catalogi. (and for now also does the same for their Components)
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
        // todo: We also might want to check if existing Catalogi have been changed and update them if needed.
        $newCatalogi = $this->pullCatalogi();

        // We might want to move this to the componentsHandler() function, see docblock comment there!
        if (isset($this->io)) {
            $this->io->note('CatalogiService->pullComponents()');
        }
        // Get all Components from all known Catalogi and compare them to our known Components. Add unknown ones.
        $newComponents = $this->pullComponents();

        return $data;
    }

    /**
     * For now we don't use this function, we could if we wanted to use a different cronjob/action to handle components than the CatalogiHandler.
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
     * And adds these unknown Catalogi.
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
     * @param string|null $ioType A type for when we want to show user feedback with SymfonyStyle. 'Section' or 'Text', Default = null.
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
     * Gets all unknown Catalogi from the Catalogi we do know.
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
            $externCatalogi = $this->getDataFromCatalogi($catalogi, 'Catalogi');
            if (empty($externCatalogi)) {
                continue;
            }
            $unknownCatalogi = $this->checkForUnknownCatalogi($externCatalogi, $knownCatalogi, $unknownCatalogi);

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
     * Gets objects of $type from the given $catalogi, using the callService.
     *
     * @param array $catalogi A Catalogi we are going to get objects from.
     * @param string $type Catalogi or Components. The type of objects we are going to get from the given Catalogi.
     *
     * @return array An array of objects of $type. Or an empty array on error or if we couldn't find anything.
     */
    private function getDataFromCatalogi(array $catalogi, string $type): array
    {
        $location = $type === 'Catalogi' ? $this->configuration['location'] : $this->configuration['componentsLocation'];
        $url = $catalogi['source']['location'].$location;
        if (isset($this->io)) {
            $this->io->text("Get $type from (known Catalogi: {$catalogi['source']['name']}) \"$url\"");
        }

        $objects = $this->getDataFromCatalogiRecursive($catalogi, [
            'type' => $type, 'location' => $location, 'url' => $url,
            'query' => $type === 'Catalogi' ? [] : [
                'extend' => [
                    'x-commongateway-metadata.synchronizations',
                    'x-commongateway-metadata.self',
                    'x-commongateway-metadata.dateModified'
                ]
            ]
        ]);

        if (isset($this->io) && is_countable($objects)) {
            $externObjectsCount = count($objects);
            $this->io->text("Found $externObjectsCount $type in Catalogi: ({$catalogi['source']['name']}) \"{$catalogi['source']['location']}\"");
            $this->io->newLine();
        }

        return $objects;
    }

    /**
     * Gets a single page of objects of $config['type'] from the given $catalogi, using the callService.
     * Recursive function, will call itself to get the next page until we don't find any results.
     *
     * @param array $catalogi A Catalogi we are going to get objects from.
     * @param array $config A configuration array containing a 'type' = Catalogi or Components,
     * a 'location' = the endpoint where we can find objects of $config['type'] from all Catalogi,
     * an 'url' = a combination of $catalogi location + $config['location']
     * and a 'query' array with query parameters to use when doing a GET api-call with the callservice.
     * @param int $page The page we are going to get.
     *
     * @return array An array of objects of $config['type']. Or an empty array on error or if we couldn't find anything.
     */
    private function getDataFromCatalogiRecursive(array $catalogi, array $config, int $page = 1): array
    {
        // todo: maybe make this function async? One message per page?
        try {
            if (isset($this->io)) {
                $this->io->text("Getting page: $page");
            }
            $source = $this->getOrCreateSource([
                'name' => "Source for Catalogi {$catalogi['source']['name']}",
                'location' => $catalogi['source']['location']
            ]);
            $response = $this->callService->call($source, $config['location'], 'GET', ['query' =>
                array_merge($config['query'], $page !== 1 ? ['page' => $page] : [])
            ]);
        } catch (Exception|GuzzleException $exception) {
            $this->synchronizationService->ioCatchException($exception, ['trace', 'line', 'file', 'message' => [
                'type'       => 'error',
                'preMessage' => "Error while doing getUnknown{$config['type']} for Catalogi: ({$catalogi['source']['name']}) \"{$config['url']}\" (Page: $page): ",
            ]]);
            //todo: error, log this
            return [];
        }

        $responseContent = json_decode($response->getBody()->getContents(), true);
        if (!isset($responseContent['results'])) {
            if (isset($this->io)) {
                $this->io->warning("No \'results\' found in response from \"{$config['url']}\" (Page: $page)");
            }
            //todo: error, log this
            return [];
        }

        $results = $responseContent['results'];
        if (!empty($results)) {
            $results = array_merge($results, $this->getDataFromCatalogiRecursive($catalogi, $config, $page + 1));
        } elseif (isset($this->io)) {
            $this->io->text("Final page reached, page $page returned 0 results");
        }

        return $results;
    }

    /**
     * Tries to find an existing Source with the given data and if it can't be found creates a new one.
     * Used by the callService when we are going to get all Catalogi of an extern Catalogi
     * or when we are going to get all Components of an extern Catalogi.
     * And used when we are creating new Synchronizations (for new Components) during Components sync.
     *
     * @param array|null $data A data array containing at least 'location' & 'name' for the Source. But can also contain the 'accept' & 'auth'.
     *
     * @return Gateway|null A Gateway/Source. (With data the CallService can use)
     */
    private function getOrCreateSource(?array $data): ?Gateway
    {
        if (!isset($data) || !isset($data['name']) || !isset($data['location'])) {
            if (isset($this->io)) {
                $this->io->error("Could not Get or Create a Source with the given data array!");
            }
            return null;
        }

        $accept = $data['accept'] ?? 'application/json';
        $auth = $data['auth'] ?? 'none';

        // First try to find an existing Gateway/Source with this location. If it helps, we could cache this
        $sources = $this->entityManager->getRepository('App:Gateway')->findBy(['location' => $data['location']]);

        if (is_countable($sources) && count($sources) > 0) {
            $source = $sources[0];
        } else {
            // Create a new Source for this Catalogi
            $source = new Gateway();
            $source->setLocation($data['location']);
            $source->setAccept($accept);
            $source->setAuth($auth);
            $source->setName($data['name']);
            $this->entityManager->persist($source);
            $this->entityManager->flush();
            if ($this->io) {
                $this->io->text("Created a new Source ({$data['name']}) \"{$data['location']}\"");
            }
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
        // Foreach with a break might be faster:
        $catalogiIsKnown = array_filter($knownCatalog, function ($catalogi) use ($checkCatalogi) {
            return $catalogi['source']['location'] === $checkCatalogi['embedded']['source']['location'];
        });

        if (is_countable($catalogiIsKnown) and count($catalogiIsKnown) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Creates a new Catalogi for each $unknownCatalogi and does a pull on this Catalogi to check for more unknown Catalogi.
     *
     * @param array $unknownCatalogi An array of all Catalogi we do not know yet.
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return array An array of all newly added Catalogi or an empty array.
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
     * Checks all known Catalogi for unknown/new Components.
     * And adds these unknown Components with corresponding Synchronizations.
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return array An array of all newly added Components or an empty array.
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
        // todo: Or do this in a separate cronjob/handler, also do this Async^ ?

        return $newComponents;
    }

    /**
     * Get all the Components we know of in this Commonground-Gateway.
     * Then also get the synchronizations and locations of these Components.
     * So that we can compare them later with other Components we might not know yet.
     *
     * @return array An array of all locations of the Components we know.
     */
    private function getAllKnownComponentLocations(): array
    {
        $knownComponents = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $this->configuration['componentsEntity']]);

        if (isset($this->io)) {
            $totalKnownComponents = is_countable($knownComponents) ? count($knownComponents) : 0;
            $this->io->section("Found $totalKnownComponents known Component".($totalKnownComponents !== 1 ? 's' : ''));
            $this->io->block("Converting all known Components to readable/usable arrays...");
        }

        // Convert ObjectEntities to useable arrays
        $domain = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' ? 'https://'.$_SERVER['HTTP_HOST'] : 'http://localhost';
        foreach ($knownComponents as &$component) {
            // We only need the metadata and/or id in this specific case to get the ComponentLocation.
            $component = $component->toArray(1, ['id', 'synchronizations', 'self'], true);
            $component = $this->getComponentLocation($component, $domain);
        }

        return $knownComponents;
    }

    /**
     * Gets the location of a single Component. First tries to look for a synchronization in x-commongateway-metadata,
     * of the given $component, to combine gateway location, endpoint and sourceId.
     * Else looks for self in x-commongateway-metadata and combines this with the given catalogiLocation.
     * And lastly if we still haven't found a location combines the given catalogiLocation with the Action configuration
     * componentsLocation and the $component id.
     *
     * @param array  $component A Component to get the location from/for.
     * @param string $catalogiLocation A location of a Catalogi where the component originally came from.
     *
     * @return string The location of the given Component.
     */
    private function getComponentLocation(array $component, string $catalogiLocation): string
    {
        // todo: always key=0?
        if (isset($component['x-commongateway-metadata']['synchronizations'][0])) {
            $componentSync = $component['x-commongateway-metadata']['synchronizations'][0];

            // Endpoint could be set to "" or null. Isset() won't pass this check so use array_key_exists!
            if (isset($componentSync['gateway']['location']) && isset($componentSync['sourceId']) &&
                array_key_exists('endpoint', $componentSync)) {
                return $componentSync['gateway']['location'].$componentSync['endpoint'].'/'.$componentSync['sourceId'];
            }
        }
        if (isset($component['x-commongateway-metadata']['self']) &&
            str_contains($component['x-commongateway-metadata']['self'], $this->configuration['componentsLocation'])) {
            return $catalogiLocation.$component['x-commongateway-metadata']['self'];
        }

        return $catalogiLocation.$this->configuration['componentsLocation'].'/'.$component['id'];
    }

    /**
     * Gets all unknown Components from the Catalogi we do know.
     *
     * @param array $knownComponentLocations An array of all locations of the Components we know.
     *
     * @return array An array of all Components we do not know yet.
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
            $externComponents = $this->getDataFromCatalogi($catalogi, 'Components');
            if (empty($externComponents)) {
                continue;
            }
            $unknownComponents = $this->checkForUnknownComponents($externComponents, $knownComponentLocations, $unknownComponents, $catalogi);

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
     * Check for new/unknown Components in the Components of an extern Catalogi.
     *
     * @param array $externComponents An array of all Components of an extern Catalogi we know.
     * @param array $knownComponentLocations An array of all locations of the Catalogi we know.
     * @param array $unknownComponents An array of all Components we do not know yet.
     * @param array $catalogi The extern Catalogi we got $externComponents from.
     *
     * @return array An array of all Components we do not know yet.
     */
    private function checkForUnknownComponents(array $externComponents, array $knownComponentLocations, array $unknownComponents, array $catalogi): array
    {
        if (isset($this->io)) {
            $this->io->text('Checking for unknown Components...');
        }

        // Keep track of locations we already are going to add new Components for.
        $unknownComponentsLocations = [];
        foreach ($unknownComponents as $unknownComponent) {
            $unknownComponentsLocations[] = $this->getComponentLocation($unknownComponent, $catalogi['source']['location']);
        }

        // Check if this extern Catalogi know any Components we don't know yet
        foreach ($externComponents as $checkComponent) {
            $checkComponentLocation = $this->getComponentLocation($checkComponent, $catalogi['source']['location']);

            // We dont want to add to $unknownComponents if it is already in there. Use $unknownComponentsLocations to check for this.
            if (!in_array($checkComponentLocation, $unknownComponentsLocations) &&
                !in_array($checkComponentLocation, $knownComponentLocations)) {
                // If $checkComponent has no synchronizations, add the catalogi source as synchronization gateway...
                // ...for when we are going to add a Synchronization (for a new Component) later.
                if (!isset($checkComponent['x-commongateway-metadata']['synchronizations'][0])) {
                    $checkComponent['x-commongateway-metadata']['synchronizations'][0]['gateway'] = $catalogi['source'];
                }
                $unknownComponents[] = $checkComponent;

                // Make sure to also add this to $unknownComponentsLocations so we don't add the same Component twice.
                $unknownComponentsLocations[] = $checkComponentLocation;
                if (isset($this->io)) {
                    $this->io->text("Found an unknown Component: ({$checkComponent['name']}) \"$checkComponentLocation\"");
                }
            }
//            elseif (isset($this->io)) {
//                $this->io->text("Already known Component (or already on 'to-add list'): ({$checkComponent['name']}) \"$checkComponentLocation\"");
//            }
        }

        return $unknownComponents;
    }

    /**
     * Creates a new Component + Synchronization for each $unknownComponents.
     *
     * @param array $unknownComponents An array of all Components we do not know yet.
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException|Exception
     *
     * @return array An array of all newly added Components or an empty array.
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
            unset($addComponent['x-commongateway-metadata']); // Not sure if this is needed before populateObject
            $addComponent = $object->includeEmbeddedArray($addComponent);
            $newComponent = $this->synchronizationService->populateObject($addComponent, $object);
            $synchronization = $this->createSyncForComponent(['object' => $newComponent, 'entity' => $entity], $addComponentWithMetadata);

            if (isset($this->io)) {
                $this->io->text("Finished adding new Component ({$addComponent['name']}) \"$url\" with id: {$newComponent->getId()->toString()}");
                $this->io->newLine();
            }
            $newComponent = $newComponent->toArray();
            $addedComponents[] = $newComponent;
        }

        if (isset($this->io) && $totalUnknownComponents > 0) {
            // We could add try catch^ and actually count errors here?
            $this->io->block("Finished adding all $totalUnknownComponents new Components");
        }

        return $addedComponents;
    }

    /**
     * Creates a Synchronization for a given Component. With the given $data.
     *
     * @param array $data         An array containing an 'object' => ObjectEntity & 'entity' => Entity.
     * @param array $addComponent A newly created Component we are going to create a Synchronization for.
     *
     * @throws Exception
     *
     * @return Synchronization The newly created Synchronization.
     */
    private function createSyncForComponent(array $data, array $addComponent): Synchronization
    {
        if (isset($this->io)) {
            $this->io->text("Creating a Synchronization for Component {$addComponent['name']}...");
        }
        $componentMetaData = $addComponent['x-commongateway-metadata'];
        $componentSync = $componentMetaData['synchronizations'][0] ?? null; // todo: always key=0?

        $synchronization = new Synchronization();
        // If a Catalogi is the source we set this in checkForUnknownComponents() and $addComponent should have this correct Source data.
        $synchronization->setGateway($this->getOrCreateSource($componentSync['gateway']));
        $synchronization->setObject($data['object']);
        $synchronization->setEntity($data['entity']);
        // Endpoint needs to be set to "" or null if $componentSync['endpoint'] === "" or null. Isset() won't pass this check, so use array_key_exists!
        $synchronization->setEndpoint(array_key_exists('endpoint', $componentSync) ? $componentSync['endpoint'] : $this->configuration['componentsLocation']);
        $synchronization->setSourceId($componentSync['sourceId'] ?? $addComponent['id']);
        $now = new DateTime();
        $synchronization->setLastChecked($now);
        $synchronization->setLastSynced($now);
        $synchronization->setSourcelastChanged(
            isset($componentSync['sourceLastChanged']) ?
            new DateTime($componentSync['sourceLastChanged']) :
            (
                // When getting the Components from other Catalogi we extend metadata.dateModified
                isset($componentMetaData['dateModified']) ?
                new DateTime($componentMetaData['dateModified']) :
                $now
            )
        );
        // Note that we hash here with the x-commongateway-metadata fields (synchronizations, self and dateModified)
        $synchronization->setHash(hash('sha384', serialize($addComponent)));
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        if (isset($this->io)) {
            $this->io->text("Finished creating a Synchronization ({$synchronization->getId()->toString()}) for Component {$addComponent['name']}");
        }

        return $synchronization;
    }
}
