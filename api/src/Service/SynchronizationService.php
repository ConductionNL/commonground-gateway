<?php

namespace App\Service;

use Adbar\Dot;
use App\Entity\Application;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Entity\User;
use App\Event\ActionEvent;
use App\Exception\AsynchronousException;
use App\Exception\GatewayException;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\FileSystemHandleService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Safe\Exceptions\UrlException;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

/**
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class SynchronizationService
{
    private CallService $callService;
    private FileSystemHandleService $fileSystemService;

    private EntityManagerInterface $entityManager;
    private SessionInterface $session;
    private GatewayService $gatewayService;
    private FunctionService $functionService;
    private LogService $logService;
    private MessageBusInterface $messageBus;
    private TranslationService $translationService;
    private ObjectEntityService $objectEntityService;
    private EavService $eavService;
    public array $configuration;
    private array $data;
    private SymfonyStyle $io;
    private Environment $twig;
    private MappingService $mappingService;
    private GatewayResourceService $resourceService;

    private ActionEvent $event;
    private EventDispatcherInterface $eventDispatcher;
    private Logger $logger;

    private bool $asyncError = false;
    private ?string $sha = null;

    /**
     * @param CallService              $callService
     * @param EntityManagerInterface   $entityManager
     * @param SessionInterface         $session
     * @param GatewayService           $gatewayService
     * @param FunctionService          $functionService
     * @param LogService               $logService
     * @param MessageBusInterface      $messageBus
     * @param TranslationService       $translationService
     * @param ObjectEntityService      $objectEntityService
     * @param EavService               $eavService
     * @param Environment              $twig
     * @param EventDispatcherInterface $eventDispatcher
     * @param MappingService           $mappingService
     * @param FileSystemHandleService  $fileSystemService
     * @param GatewayResourceService   $resourceService
     */
    public function __construct(
        CallService $callService,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        GatewayService $gatewayService,
        FunctionService $functionService,
        LogService $logService,
        MessageBusInterface $messageBus,
        TranslationService $translationService,
        ObjectEntityService $objectEntityService,
        EavService $eavService,
        Environment $twig,
        EventDispatcherInterface $eventDispatcher,
        MappingService $mappingService,
        FileSystemHandleService $fileSystemService,
        GatewayResourceService $resourceService
    ) {
        $this->callService = $callService;
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->gatewayService = $gatewayService;
        $this->functionService = $functionService;
        $this->logService = $logService;
        $this->messageBus = $messageBus;
        $this->translationService = $translationService;
        $this->objectEntityService = $objectEntityService;
        $this->objectEntityService->addServices($eavService);
        $this->eavService = $eavService;
        $this->configuration = [];
        $this->data = [];
        $this->twig = $twig;
        $this->event = new ActionEvent('', []);
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = new Logger('installation');
        $this->mappingService = $mappingService;
        $this->fileSystemService = $fileSystemService;
        $this->resourceService = $resourceService;
    }

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $io
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
    }

    /**
     * @param array $data
     * @param array $configuration
     *
     * @return array
     *
     * @todo
     */
    public function synchronizationItemHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        if ($this->session->get('io')) {
            $this->io = $this->session->get('io');
            $this->io->note('SynchronizationService->SynchronizationItemHandler()');
        }
        $this->logger->debug('SynchronizationService->SynchronizationItemHandler()');

        return $data;
    }

    /**
     * Synchronises objects in the gateway to a source.
     *
     * @param array $data          The data from the action
     * @param array $configuration The configuration given by the action
     *
     * @throws CacheException|GuzzleException|InvalidArgumentException|LoaderError|SyntaxError|AsynchronousException
     *
     * @return array The data from the action modified by the execution of the synchronization
     */
    public function synchronizationPushHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        if ($this->session->get('io')) {
            $this->io = $this->session->get('io');
            $this->io->note('SynchronizationService->synchronizationPushHandler()');
        }
        $this->logger->debug('SynchronizationService->synchronizationPushHandler()');

        $source = $this->getSourceFromConfig();
        $entity = $this->getEntityFromConfig();

        if (!($entity instanceof Entity) || !($source instanceof Source)) {
            return $this->data;
        }

        foreach ($entity->getObjectEntities() as $object) {
            $synchronization = $this->findSyncByObject($object, $source, $entity);
            // todo: replace this if elseif with handleSync! needs testing first!
//            $this->handleSync($synchronization);
            if (!$synchronization->getLastSynced()) {
                $synchronization = $this->syncToSource($synchronization, false);
            } elseif ($object->getDateModified() > $synchronization->getLastSynced() && (!isset($this->configuration['updatesAllowed']) || $this->configuration['updatesAllowed'])) {
                $synchronization = $this->syncToSource($synchronization, true);
            }
            $this->entityManager->persist($synchronization);
            $this->entityManager->flush();
        }
        if ($this->asyncError) {
            $this->asyncError = false;

            throw new AsynchronousException('Synchronization failed');
        }

        return $this->data;
    }

    /**
     * Gets all objects from the source according to configuration.
     *
     * @param array $data          Data from the request running
     * @param array $configuration Configuration for the action running
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException|LoaderError|SyntaxError|GuzzleException
     *
     * @return array The resulting data
     */
    public function SynchronizationCollectionHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;
        if ($this->session->get('io')) {
            $this->io = $this->session->get('io');
            $this->io->note('SynchronizationService->SynchronizationCollectionHandler()');
        }
        $this->logger->debug('SynchronizationService->SynchronizationCollectionHandler()');

        $source = $this->getSourceFromConfig();
        $entity = $this->getEntityFromConfig();

        if (!($entity instanceof Entity) || !($source instanceof Source)) {
            return $this->data;
        }

        $collectionDelete = false;
        if (array_key_exists('collectionDelete', $this->configuration['apiSource']) && $this->configuration['apiSource']['collectionDelete']) {
            $collectionDelete = true;
        }

        // Get json/array results based on the type of source
        $results = $this->getObjectsFromSource($source);
        // Get all existing synchronizations for the entity+source
        $collectionDelete && $existingSynchronizations = $this->entityManager->getRepository('App:Synchronization')->findBy(['gateway' => $source, 'entity' => $entity]);

        if (isset($this->io)) {
            $totalResults = is_countable($results) ? count($results) : 0;
            $totalExistingSyncs = $collectionDelete && is_countable($existingSynchronizations) ? count($existingSynchronizations) : 0;
            $this->io->block("Found $totalResults object".($totalResults == 1 ? '' : 's')." in Source and $totalExistingSyncs existing synchronization".($totalExistingSyncs == 1 ? '' : 's').' in the Gateway. Start syncing all objects found in Source to Gateway objects...');
            $totalResultsSynced = 0;
        }

        $loopResults = $this->loopThroughCollectionResults($results, [
            'source'                   => $source,
            'entity'                   => $entity,
            'collectionDelete'         => $collectionDelete,
            'existingSynchronizations' => $existingSynchronizations ?? null,
            'totalResultsSynced'       => $totalResultsSynced ?? null,
        ]);

        if (isset($this->io)) {
            $totalExistingSyncs = $collectionDelete && is_countable($loopResults['existingSynchronizations']) ? count($loopResults['existingSynchronizations']) : 0;
            $this->io->block("Synced {$loopResults['totalResultsSynced']}/$totalResults object".($totalResults == 1 ? '' : 's').
                " from Source to Gateway. We have $totalExistingSyncs existing Synchronization".($totalExistingSyncs == 1 ? '' : 's').
                ' for an Object in the Gateway that no longer exist in the Source.'.
                ($totalExistingSyncs !== 0 ? ' Start deleting these Synchronizations and their objects...' : ''));
        }

        if ($collectionDelete) {
            // Remove all existing synchronizations (and the objects connected) that we didn't find during sync from source to gateway.
            foreach ($loopResults['existingSynchronizations'] as $existingSynchronization) {
                $this->deleteSyncAndObject($existingSynchronization);
            }
        }

        return $results;
    }

    /**
     * Loops through all objects gathered from a Source with the SynchronizationCollectionHandler function.
     *
     * @param array $results The array of objects / $results we got from a source.
     * @param array $config  A configuration array with data used by this function. This array must contain the following keys:
     *                       'source', 'entity', 'collectionDelete', 'existingSynchronizations', 'totalResultsSynced'.
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException|LoaderError|SyntaxError|GuzzleException
     *
     * @return array An array containing the 'existingSynchronizations' (an array of all existing synchronization that do exist in the gateway but aren't re-synced yet)
     *               and 'totalResultsSynced' (a count of how many objects we have synced)
     */
    private function loopThroughCollectionResults(array $results, array $config): array
    {
        foreach ($results as $result) {
            // @todo this could and should be async (nice to have)

            // Turn it in a dot array to find the correct data in $result...
            $dot = new Dot($result);
            // The place where we can find the id field when looping through the list of objects, from $result root, by object (dot notation)
            $id = $dot->get($this->configuration['apiSource']['location']['idField']);

            // The place where we can find an object when we walk through the list of objects, from $result root, by object (dot notation)
            // Todo: this should be 'objects' not 'object'... 'object' is for finding a single object not an array of objects!
            array_key_exists('object', $this->configuration['apiSource']['location']) && $result = $dot->get($this->configuration['apiSource']['location']['object'], $result);

            // Lets grab the sync object, if we don't find an existing one, this will create a new one:
            $synchronization = $this->findSyncBySource($config['source'], $config['entity'], $id, $this->configuration['location'] ?? null);
            // todo: Another search function for sync object. If no sync object is found, look for matching properties...
            // todo: ...in $result and an ObjectEntity in db. And then create sync for an ObjectEntity if we find one this way. (nice to have)
            // Other option to find a sync object, currently not used:
            //            $synchronization = $this->findSyncByObject($object, $source, $entity);

            // Lets sync (returns the Synchronization object)
            if (array_key_exists('useDataFromCollection', $this->configuration) and !$this->configuration['useDataFromCollection']) {
                $result = [];
            }
            $updatedSynchronization = $this->handleSync($synchronization, $result);

            $this->entityManager->persist($updatedSynchronization);

            $this->entityManager->flush();
            $this->entityManager->flush();

            if ($config['collectionDelete'] && ($key = array_search($synchronization, $config['existingSynchronizations'])) !== false) {
                unset($config['existingSynchronizations'][$key]);
            }
            $config['totalResultsSynced'] = $config['totalResultsSynced'] + 1;
            if (isset($this->io)) {
                $this->io->text('totalResultsSynced +1 = '.$config['totalResultsSynced']);
                $this->io->newLine();
            }
            $this->logger->debug('totalResultsSynced +1 = '.$config['totalResultsSynced']);
        }

        return [
            'existingSynchronizations' => $config['existingSynchronizations'],
            'totalResultsSynced'       => $config['totalResultsSynced'],
        ];
    }

    /**
     * Deletes a Synchronization and its object from the gateway.
     *
     * @param Synchronization $synchronization
     *
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    private function deleteSyncAndObject(Synchronization $synchronization): bool
    {
        try {
            $object = $synchronization->getObject();
            $data = null;

            if (isset($this->io)) {
                $this->io->text("Deleting Object with id: {$object->getId()->toString()} & Synchronization with id: {$synchronization->getId()->toString()}");
            }
            $this->logger->info("Deleting Object with id: {$object->getId()->toString()} & Synchronization with id: {$synchronization->getId()->toString()}");

            // Delete object (this will remove this object result from the cache)
            // This will also delete the sync because of cascade delete
            $this->functionService->removeResultFromCache = [];
            $data = $this->eavService->handleDelete($object);

            return true;
        } catch (Exception $exception) {
            if (isset($this->io)) {
                $this->io->warning("{$exception->getMessage()}");
            }

            $this->logger->error("{$exception->getMessage()}");

            return false;
        }
    }

    /**
     * Searches and returns the source of the configuration in the database.
     *
     * @param string $configKey The key to use when looking for an uuid of a Source in the Action->Configuration.
     *
     * @return Source|null The found source for the configuration
     */
    private function getSourceFromConfig(string $configKey = 'source'): ?Source
    {
        if (isset($this->configuration[$configKey])) {
            $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['id' => $this->configuration[$configKey]]);
            if ($source instanceof Source && $source->getIsEnabled()) {
                return $source;
            }
        }
        if (isset($this->io)) {
            if (isset($source) && $source instanceof Source && !$source->getIsEnabled()) {
                $this->io->warning("This source is not enabled: {$source->getName()}");
            } else {
                $this->io->error("Could not get a Source with current Action->Configuration['$configKey']");
            }
        }
        if (isset($source) && $source instanceof Source && !$source->getIsEnabled()) {
            $this->logger->warning("This source is not enabled: {$source->getName()}");
        } else {
            $this->logger->error("Could not get a Source with current Action->Configuration['$configKey']");
        }

        return null;
    }

    /**
     * Searches and returns the entity of the configuration in the database.
     *
     * @param string $configKey The key to use when looking for an uuid of an Entity in the Action->Configuration.
     *
     * @return Entity|null The found entity for the configuration
     */
    public function getEntityFromConfig(string $configKey = 'entity'): ?Entity
    {
        if (isset($this->configuration[$configKey])) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $this->configuration[$configKey]]);
            if ($entity instanceof Entity) {
                return $entity;
            }
        }
        if (isset($this->io)) {
            $this->io->error("Could not get an Entity with current Action->Configuration['$configKey']");
        }

        $this->logger->error("Could not get an Entity with current Action->Configuration['$configKey']");

        return null;
    }

    /**
     * Determines the configuration for using the callservice for the source given.
     *
     * @param Source      $source      The source to call
     * @param string|null $id          The id to request (optional)
     * @param array|null  $objectArray
     *
     * @throws LoaderError|SyntaxError
     *
     * @return array The configuration for the source to call
     */
    private function getCallServiceConfig(Source $source, string $id = null, ?array $objectArray = []): array
    {
        return [
            'source'    => $source,
            'endpoint'  => $this->getCallServiceEndpoint($id, $objectArray),
            'query'     => $this->getCallServiceOverwrite('query') ?? $this->getQueryForCallService($id), //todo maybe array_merge instead of ??
            'headers'   => array_merge(
                ['Content-Type' => 'application/json'],
                $this->getCallServiceOverwrite('headers') ?? $source->getHeaders() //todo maybe array_merge instead of ??
            ),
            'method'    => $this->getCallServiceOverwrite('method'),
        ];
    }

    /**
     * This function checks if a specific key exists in $this->configuration['callService'] and returns it's value if it does.
     * If we want to overwrite how we do a callService call to a source, use $this->configuration['callService'].
     *
     * @param string $key
     *
     * @return mixed|null
     */
    private function getCallServiceOverwrite(string $key)
    {
        if (array_key_exists('callService', $this->configuration) && array_key_exists($key, $this->configuration['callService'])) {
            return $this->configuration['callService'][$key];
        }

        return null;
    }

    /**
     * Determines the URL to request.
     *
     * @param string|null $id          The id to request (optional)
     * @param array|null  $objectArray
     *
     * @throws LoaderError|SyntaxError
     *
     * @return string The resulting URL
     */
    private function getCallServiceEndpoint(string $id = null, ?array $objectArray = []): string
    {
        // What if we do not have an action?
        if (!isset($this->configuration['location'])) {
            return '';
        }

        $renderData = array_key_exists('replaceTwigLocation', $this->configuration) && $this->configuration['replaceTwigLocation'] === 'objectEntityData' ? $objectArray : $this->data;

        $location = $this->twig->createTemplate($this->configuration['location'])->render($renderData);

        if (isset($this->configuration['queryParams']['syncSourceId'])) {
            $id = null;
        }

        return $location.($id ? '/'.$id : '');
    }

    /**
     * Determines the query parameters for a request done with the callservice.
     *
     * @param string|null $id The id to request (optional)
     *
     * @return array The resulting query parameters.
     */
    private function getQueryForCallService(string $id = null): array
    {
        // What if we do not have an action?
        if (!isset($this->configuration['apiSource'])) {
            return [];
        }

        $query = [];
        // todo: maybe move this specific option to fetchObjectsFromSource, because it is specifically used for get collection calls on the source.
        if (array_key_exists('sourceLimit', $this->configuration['apiSource'])) {
            $key = array_key_exists('sourceLimitKey', $this->configuration['apiSource']) ? $this->configuration['apiSource']['sourceLimitKey'] : 'limit';
            $query[$key] = $this->configuration['apiSource']['sourceLimit'];
        }
        if (isset($this->configuration['queryParams']['syncSourceId'])) {
            $query[$this->configuration['queryParams']['syncSourceId']] = $id;
        }

        return $query;
    }

    /**
     * Gets the configuration for the source and fetches the results on the source.
     *
     * @param Source $source The source to get the data from
     *
     *@throws LoaderError|SyntaxError|GuzzleException
     *
     * @return array The results found on the source
     */
    private function getObjectsFromSource(Source $source): array
    {
        $callServiceConfig = $this->getCallServiceConfig($source);
        if (isset($this->io)) {
            $this->io->definitionList(
                'getObjectsFromSource with this callServiceConfig data',
                new TableSeparator(),
                ['Source'    => "Source \"{$source->getName()}\" ({$source->getId()->toString()})"],
                ['Endpoint'  => $callServiceConfig['endpoint']],
                ['Query'     => is_array($callServiceConfig['query']) ? "[{$this->objectEntityService->implodeMultiArray($callServiceConfig['query'])}]" : $callServiceConfig['query']],
                ['Headers'   => is_array($callServiceConfig['headers']) ? "[{$this->objectEntityService->implodeMultiArray($callServiceConfig['headers'])}]" : $callServiceConfig['headers']],
                ['Method'    => $callServiceConfig['method'] ?? 'GET'],
            );
        }

        // Right now there are two options, either api source is paginated or it is not
        $results = $this->fetchObjectsFromSource($callServiceConfig);

        // todo: more options ...

        return $results;
    }

    /**
     * A function used to send userFeedback with SymfonyStyle $io when an Exception is caught during a try-catch.
     *
     * @param Exception $exception The Exception we caught.
     * @param array     $config    A configuration array for this function, if one of the keys 'file', 'line' or 'trace' is present
     *                             this function will show that data of the Exception with $this->io->block. It is also possible to configure the message
     *                             with this array through the key 'message' => [Here you have the options to add the keys 'preMessage' & 'postMessage'
     *                             to expend on the Exception message and choose the type of the io message, io->error or io->warning with the key
     *                             'type' => 'error', default = warning].
     *
     * @return void
     */
    public function ioCatchException(Exception $exception, array $config)
    {
        if (!isset($this->io) && $this->session->get('io')) {
            $this->io = $this->session->get('io');
        }
        if (isset($this->io)) {
            $errorMessage = ($config['message']['preMessage'] ?? '').$exception->getMessage().($config['message']['postMessage'] ?? '');
            (isset($config['message']['type']) && $config['message']['type'] === 'error') ?
                $this->io->error($errorMessage) : $this->io->warning($errorMessage);
            (isset($config['message']['type']) && $config['message']['type'] === 'error') ?
                $this->logger->error($errorMessage) : $this->logger->warning($errorMessage);
            isset($config['file']) && $this->io->block("File: {$exception->getFile()}");
            isset($config['line']) && $this->io->block("Line: {$exception->getLine()}");
            isset($config['trace']) && $this->io->block("Trace: {$exception->getTraceAsString()}");
        }
    }

    /**
     * Fetches the objects stored on the source.
     *
     * @param array $callServiceConfig The configuration for the source
     * @param int   $page              The current page to be requested
     * @param ?int  $errorsInARowCount The amount of failed fetches in a row
     *
     * @throws GuzzleException
     *
     * @return array
     */
    private function fetchObjectsFromSource(array $callServiceConfig, int $page = 1, ?int $errorsInARowCount = 0): array
    {
        // Get a single page
        if (is_array($callServiceConfig['query'])) {
            $query = array_merge($callServiceConfig['query'], $page !== 1 ? ['page' => $page] : []);
        } else {
            $query = $callServiceConfig['query'].'&page='.$page;
        }

        try {
            if (isset($this->io)) {
                $this->io->text("fetchObjectsFromSource with \$page = $page");
            }
            $this->logger->debug("fetchObjectsFromSource with \$page = $page");
            $response = $this->callService->call(
                $callServiceConfig['source'],
                $callServiceConfig['endpoint'],
                $callServiceConfig['method'] ?? 'GET',
                [
                    'body'    => strtoupper($callServiceConfig['method']) == 'POST' ? '{}' : '',
                    'query'   => $query,
                    'headers' => $callServiceConfig['headers'],
                ]
            );
        } catch (Exception|GuzzleException $exception) {
            // If no next page with this $page exists...
            if ($errorsInARowCount == 3) {
                $this->ioCatchException($exception, ['line', 'file', 'message' => [
                    'preMessage' => '(This might just be the final page!) -  Error while doing fetchObjectsFromSource, tried to fetch page 3 times: ',
                ]]);

                return [];
            }

            $this->ioCatchException($exception, ['line', 'file', 'message' => [
                'preMessage' => "Failed fetching page $page ",
            ]]);

            $errorsInARowCount++;

            return $this->fetchObjectsFromSource($callServiceConfig, $page + 1, $errorsInARowCount);
        }

        $pageResult = $this->callService->decodeResponse($callServiceConfig['source'], $response);

        $dot = new Dot($pageResult);
        $results = $dot->get($this->configuration['apiSource']['location']['objects'], $pageResult);

        if (array_key_exists('sourceLimit', $this->configuration['apiSource']) && count($results) >= $this->configuration['apiSource']['sourceLimit']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($callServiceConfig, $page + 1));
        } elseif (!empty($results) && isset($this->configuration['apiSource']['sourcePaginated']) && $this->configuration['apiSource']['sourcePaginated']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($callServiceConfig, $page + 1));
        }

        return $results;
    }

    /**
     * Gets a single object from the source.
     *
     * @param Synchronization $synchronization The synchronization object with the related source object id
     *
     * @throws LoaderError|SyntaxError|GuzzleException
     *
     * @return array|null The resulting object
     */
    public function getSingleFromSource(Synchronization $synchronization): ?array
    {
        $callServiceConfig = $this->getCallServiceConfig($synchronization->getSource(), $synchronization->getSourceId());

        $url = \Safe\parse_url($synchronization->getSource()->getLocation());

        $endpoint = $synchronization->getEndpoint().'/'.$synchronization->getSourceId();
        if (str_contains('http', $synchronization->getSourceId()) === true) {
            $endpoint = $synchronization->getEndpoint();
        }
        if (isset($this->configuration['location']) === true) {
            $endpoint = $this->configuration['location'];
            $synchronization->setEndpoint($endpoint);
        }

        if ($url['scheme'] === 'http' || $url['scheme'] === 'https') {
            // Get object form source with callservice
            try {
                $this->logger->info("getSingleFromSource with Synchronization->sourceId = {$synchronization->getSourceId()}");
                $response = $this->callService->call(
                    $callServiceConfig['source'],
                    $endpoint,
                    $callServiceConfig['method'] ?? 'GET',
                    [
                        'body'    => '',
                        'query'   => $callServiceConfig['query'],
                        'headers' => $callServiceConfig['headers'],
                    ]
                );
            } catch (Exception|GuzzleException $exception) {
                $this->ioCatchException($exception, ['line', 'file', 'message' => [
                    'preMessage' => 'Error while doing getSingleFromSource: ',
                ]]);

                return null;
            }
            $result = $this->callService->decodeResponse($callServiceConfig['source'], $response);
        } elseif ($url['scheme'] === 'ftp') {
            // This only works if a file data equals a single Object(Entity). Or if the mapping on the Source or Synchronization results in data for just a single Object.
            $result = $this->fileSystemService->call($synchronization->getSource(), isset($this->configuration['location']) === true ? $callServiceConfig['endpoint'] : $endpoint);
        }
        $dot = new Dot($result);
        // The place where we can find the id field when looping through the list of objects, from $result root, by object (dot notation)
        //        $id = $dot->get($this->configuration['locationIdField']); // todo, not sure if we need this here or later?

        // The place where we can find the object, from $result root, by object (dot notation)
        if (isset($this->configuration['apiSource']['location']['object'])) {
            return $dot->get($this->configuration['apiSource']['location']['object'], $result);
        }

        return $dot->jsonSerialize();
    }

    /**
     * Finds a synchronization object if it exists for the current object in the source, or creates one if it doesn't exist.
     *
     * @param Source $source        The source that is requested
     * @param Entity $entity        The entity that is requested
     * @param string $sourceId      The id of the object in the source
     * @param string|null $endpoint The endpoint of the synchronization.
     *
     * @return Synchronization|null A synchronization object related to the object in the source
     */
    public function findSyncBySource(Source $source, Entity $entity, string $sourceId, ?string $endpoint = null): ?Synchronization
    {
        $criteria = ['gateway' => $source, 'entity' => $entity, 'sourceId' => $sourceId];
        if (empty($endpoint) === false) {
            $criteria['endpoint'] = $endpoint;
        }
        $synchronization = $this->entityManager->getRepository('App:Synchronization')->findOneBy($criteria);

        if ($synchronization instanceof Synchronization) {
            if (isset($this->io)) {
                $this->io->text("findSyncBySource() Found existing Synchronization with SourceId = $sourceId");
            }
            $this->logger->debug("findSyncBySource() Found existing Synchronization with SourceId = $sourceId");

            return $synchronization;
        }

        $synchronization = new Synchronization($source, $entity);
        $synchronization->setEndpoint($endpoint);
        $synchronization->setSourceId($sourceId);
        $this->entityManager->persist($synchronization);
        // We flush later

        if (isset($this->io)) {
            $this->io->text("findSyncBySource() Created new Synchronization with SourceId = $sourceId");
        }
        $this->logger->debug("findSyncBySource() Created new Synchronization with SourceId = $sourceId");

        return $synchronization;
    }

    /**
     * Finds a synchronization object if it exists for the current object in the gateway, or creates one if it doesn't exist.
     *
     * @param ObjectEntity $objectEntity The current object in the gateway
     * @param Source       $source       The current source
     * @param Entity       $entity       The current entity
     *
     * @return Synchronization|null A synchronization object related to the object in the gateway
     */
    public function findSyncByObject(ObjectEntity $objectEntity, Source $source, Entity $entity): ?Synchronization
    {
        $synchronization = $this->entityManager->getRepository('App:Synchronization')->findOneBy(['object' => $objectEntity->getId(), 'gateway' => $source, 'entity' => $entity]);
        if ($synchronization instanceof Synchronization) {
            if (isset($this->io)) {
                $this->io->text("findSyncByObject() Found existing Synchronization with object = {$objectEntity->getId()->toString()}");
            }
            $this->logger->debug("findSyncByObject() Found existing Synchronization with object = {$objectEntity->getId()->toString()}");

            return $synchronization;
        }

        $synchronization = new Synchronization($source, $entity);
        $synchronization->setObject($objectEntity);
        $synchronization->setSourceId($objectEntity->getId());
        $synchronization->setBlocked(false);
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        if (isset($this->io)) {
            $this->io->text("findSyncByObject() Created new Synchronization with object = {$objectEntity->getId()->toString()}");
        }
        $this->logger->debug("findSyncByObject() Created new Synchronization with object = {$objectEntity->getId()->toString()}");

        return $synchronization;
    }

    /**
     * Adds a new ObjectEntity to a synchronization object.
     *
     * @param Synchronization $synchronization The synchronization object without object
     *
     * @return string The method for populateObject, POST or PUT depending on if we created a new ObjectEntity.
     */
    public function checkObjectEntity(Synchronization $synchronization): string
    {
        if (!$synchronization->getObject()) {
            $object = new ObjectEntity();
            $synchronization->getSourceId() && $object->setExternalId($synchronization->getSourceId());
            $object->setEntity($synchronization->getEntity());
            $object = $this->setApplication($object);
            $object->addSynchronization($synchronization);
            $this->entityManager->persist($object);
            if (isset($this->io)) {
                $this->io->text("Created new ObjectEntity for Synchronization with id = {$synchronization->getId()->toString()}");
            }
            $this->logger->debug("Created new ObjectEntity for Synchronization with id = {$synchronization->getId()->toString()}");
            $this->event = new ActionEvent('commongateway.object.create', []);

            return 'POST';
        }

        $this->event = new ActionEvent('commongateway.object.update', []);

        return 'PUT';
    }

    /**
     * Sets the last changed date from the source object and creates a hash for the source object.
     *
     * @param Synchronization $synchronization The synchronization object to update
     * @param array           $sourceObject    The object returned from the source
     *
     * @throws Exception
     *
     * @return Synchronization The updated source object
     */
    private function setLastChangedDate(Synchronization $synchronization, array $sourceObject): Synchronization
    {
        if (!$synchronization->getSource()->getTest()) {
            $hash = hash('sha384', serialize($sourceObject));
        } else {
            $hash = serialize($sourceObject);
        }
        $dot = new Dot($sourceObject);
        if (isset($this->configuration['apiSource']['location']['dateChangedField'])) {
            $lastChanged = $dot->get($this->configuration['apiSource']['location']['dateChangedField']);
            if (!empty($lastChanged)) {
                $synchronization->setSourcelastChanged(new DateTime($lastChanged));
                $synchronization->setHash($hash);

                return $synchronization;
            }
        }
        if ($synchronization->getHash() != $hash) {
            $lastChanged = new DateTime();
            $synchronization->setSourcelastChanged($lastChanged);
        }
        $synchronization->setHash($hash);

        return $synchronization;
    }

    /**
     * Executes the synchronization between source and gateway.
     *
     * @param Synchronization $synchronization The synchronization object before synchronization
     * @param array           $sourceObject    The object in the source
     *
     * @throws CacheException|ComponentException|GatewayException|GuzzleException|InvalidArgumentException|LoaderError|SyntaxError|Exception
     *
     * @return Synchronization The updated synchronization object
     */
    public function handleSync(Synchronization $synchronization, array $sourceObject = [], ?array $customConfig = null): Synchronization
    {
        isset($customConfig) && $this->configuration = $customConfig;

        if (isset($this->io)) {
            $this->io->text("handleSync for Synchronization with id = {$synchronization->getId()->toString()}");
        }
        $this->logger->info("handleSync for Synchronization with id = {$synchronization->getId()->toString()}");
        $method = $this->checkObjectEntity($synchronization);

        // If we don't have an sourced object, we need to get one
        $sourceObject = $sourceObject ?: $this->getSingleFromSource($synchronization);

        if ($sourceObject === null) {
            if (isset($this->io)) {
                $this->io->warning("Can not handleSync for Synchronization with id = {$synchronization->getId()->toString()} if \$sourceObject === null");
            }
            $this->logger->warning("Can not handleSync for Synchronization with id = {$synchronization->getId()->toString()} if \$sourceObject === null");

            return $synchronization;
        }

        // Let check
        $now = new DateTime();
        $synchronization->setLastChecked($now);

        $synchronization = $this->setLastChangedDate($synchronization, $sourceObject);

        //Checks which is newer, the object in the gateway or in the source, and synchronise accordingly
        // todo: this if, elseif, else needs fixing, conditions aren't correct for if we ever want to syncToSource with this handleSync function
        if (!$synchronization->getLastSynced() || ($synchronization->getLastSynced() < $synchronization->getSourceLastChanged() && $synchronization->getSourceLastChanged() >= $synchronization->getObject()->getDateModified())) {
            // Counter
            $counter = $synchronization->getTryCounter() + 1;
            $synchronization->setTryCounter($counter);

            // Set dont try before, expensional so in minutes  1,8,27,64,125,216,343,512,729,1000
            $addMinutes = pow($counter, 3);
            if ($synchronization->getDontSyncBefore()) {
                $dontTryBefore = $synchronization->getDontSyncBefore()->add(new DateInterval('PT'.$addMinutes.'M'));
            } else {
                $dontTryBefore = new DateTime();
            }
            $synchronization->setDontSyncBefore($dontTryBefore);

            $synchronization = $this->syncToGateway($synchronization, $sourceObject, $method);
        }

        // todo: we currently never use handleSync to do syncToSource, so let's make sure we aren't trying to by accident
//        elseif ((!$synchronization->getLastSynced() || $synchronization->getLastSynced() < $synchronization->getObject()->getDateModified()) && $synchronization->getSourceLastChanged() < $synchronization->getObject()->getDateModified()) {
//            $synchronization = $this->syncToSource($synchronization, true);
//        }
        else {
            if (isset($this->io)) {
                //todo: temp, maybe put something else here later
                $this->io->text("Nothing to sync because source and gateway haven't changed");
            }
            $this->logger->info("Nothing to sync because source and gateway haven't changed");
            $synchronization = $this->syncThroughComparing($synchronization);
        }

        return $synchronization;
    }

    /**
     * This function checks if the sha of $synchronization matches the given $sha.
     * When $synchronization->getSha() doesn't match with the given $sha, the given $sha will be stored in the SynchronizationService.
     * Always call the ->synchronize() function after this, because only then the stored $sha will be used to update $synchronization->setSha().
     *
     * @param Synchronization $synchronization The Synchronization to check the sha of.
     * @param string $sha The sha to check / compare.
     *
     * @return bool Returns True if sha matches, and false if it does not match.
     */
    public function doesShaMatch(Synchronization $synchronization, string $sha): bool
    {
        $this->sha = null;

        if ($synchronization->getSha() === $sha) {
            return true;
        }

        $this->sha = $sha;

        return false;
    }

    /**
     * Executes the synchronization between source and gateway.
     *
     * @param Synchronization $synchronization The synchronization to update
     * @param array           $sourceObject    The object in the source
     * @param bool            $unsafe          Unset attributes that are not included in the hydrator array when calling the hydrate function
     *
     * @throws GuzzleException
     * @throws LoaderError
     * @throws SyntaxError
     *
     * @return Synchronization The updated synchronization
     */
    public function synchronize(Synchronization $synchronization, array $sourceObject = [], bool $unsafe = false): Synchronization
    {
        if (isset($this->io)) {
            $this->io->text("handleSync for Synchronization with id = {$synchronization->getId()->toString()}");
        }
        $this->logger->info("handleSync for Synchronization with id = {$synchronization->getId()->toString()}");

        //create new object if no object exists
        if (!$synchronization->getObject()) {
            isset($this->io) && $this->io->text('creating new objectEntity');
            $this->logger->info('creating new objectEntity');
            $object = new ObjectEntity($synchronization->getEntity());
            $object->addSynchronization($synchronization);
            $this->entityManager->persist($object);
            $this->entityManager->persist($synchronization);
            $oldDateModified = null;
        } else {
            $oldDateModified = $synchronization->getObject()->getDateModified()->getTimestamp();
        }
        $sourceObject = $sourceObject ?: $this->getSingleFromSource($synchronization);

        if ($sourceObject === null) {
            if (isset($this->io)) {
                $this->io->warning("Can not handleSync for Synchronization with id = {$synchronization->getId()->toString()} if \$sourceObject === null");
            }
            $this->logger->warning("Can not handleSync for Synchronization with id = {$synchronization->getId()->toString()} if \$sourceObject === null");

            return $synchronization;
        }

        // Let check
        $now = new DateTime();
        $synchronization->setLastChecked($now);

        // Todo: we never check if we actually have to sync anything... see handleSync functie for an example:
        // if (!$synchronization->getLastSynced() || ($synchronization->getLastSynced() < $synchronization->getSourceLastChanged() && $synchronization->getSourceLastChanged() >= $synchronization->getObject()->getDateModified())) {
        // Todo: @ruben i heard from @robert we wanted to do this check somewhere else?

        // Counter
        $counter = $synchronization->getTryCounter() + 1;
        if ($counter > 10000) {
            $counter = 10000;
        }
        $synchronization->setTryCounter($counter);

        // Set dont try before, expensional so in minutes  1,8,27,64,125,216,343,512,729,1000
        $addMinutes = pow($counter, 3);
        if ($synchronization->getDontSyncBefore()) {
            $dontTryBefore = $synchronization->getDontSyncBefore()->add(new DateInterval('PT'.$addMinutes.'M'));
        } else {
            $dontTryBefore = new DateTime();
        }
        $synchronization->setDontSyncBefore($dontTryBefore);

        if ($synchronization->getMapping()) {
            $sourceObject = $this->mappingService->mapping($synchronization->getMapping(), $sourceObject);
        }
        $synchronization->getObject()->hydrate($sourceObject, $unsafe);

        if ($this->sha !== null) {
            $synchronization->setSha($this->sha);
            $this->sha = null;
        }

        $this->entityManager->persist($synchronization->getObject());
        $this->entityManager->persist($synchronization);

        if ($oldDateModified !== $synchronization->getObject()->getDateModified()->getTimestamp()) {
            $date = new DateTime();
            isset($this->io) ?? $this->io->text("set new dateLastChanged to {$date->format('d-m-YTH:i:s')}");
            $synchronization->setLastSynced(new DateTime());
            $synchronization->setTryCounter(0);
        } else {
            isset($this->io) ?? $this->io->text("lastSynced is still {$synchronization->getObject()->getDateModified()->format('d-m-YTH:i:s')}");
        }
        //@TODO: write to source if internal timestamp > external timestamp & sourceobject is new

        return $synchronization;
    }

    /**
     * This function populates a pre-existing objectEntity with data that has been validated.
     * This function is only meant for synchronization.
     *
     * @param array        $data         The data that has to go into the objectEntity
     * @param ObjectEntity $objectEntity The ObjectEntity to populate
     *
     * @throws CacheException|InvalidArgumentException|ComponentException|GatewayException
     *
     * @return ObjectEntity The populated ObjectEntity
     */
    public function populateObject(array $data, ObjectEntity $objectEntity, ?string $method = 'POST'): ObjectEntity
    {
        // todo: move this function to ObjectEntityService to prevent duplicate code...

        $objectEntity->hydrate($data);

        $this->entityManager->persist($objectEntity);

        $this->event->setData(['response' => $objectEntity->toArray(), 'entity' => $objectEntity->getEntity()->getId()->toString()]);
        $this->eventDispatcher->dispatch($this->event, $this->event->getType());

        return $objectEntity;
    }

    /**
     * Translates the input according to the translation table referenced in the configuration of the action.
     *
     * @param array $sourceObject The external object found in the source
     * @param bool  $translateOut Default = false, if set to true will use translationsOut instead of translationsIn.
     *
     * @return array The translated external object
     */
    private function translate(array $sourceObject, bool $translateOut = false): array
    {
        $translationsRepo = $this->entityManager->getRepository('App:Translation');
        $translations = $translationsRepo->getTranslations($translateOut ? $this->configuration['apiSource']['translationsOut'] : $this->configuration['apiSource']['translationsIn']);
        if (!empty($translations)) {
            $sourceObject = $this->translationService->parse($sourceObject, true, $translations);
        }

        return $sourceObject;
    }

    /**
     * Sets an application and organization for new ObjectEntities.
     * todo: setting organization is done by the new ObjectEntitySubscriber. We should set application through this way as well...
     *
     * @param ObjectEntity $objectEntity The ObjectEntity to update
     *
     * @return ObjectEntity The updated ObjectEntity
     */
    public function setApplication(ObjectEntity $objectEntity): ObjectEntity
    {
        $application = $this->entityManager->getRepository('App:Application')->findOneBy(['name' => 'main application']);
        if ($application instanceof Application) {
            $objectEntity->setApplication($application);
        } elseif (
            ($applications = $this->entityManager->getRepository('App:Application')->findAll()
                && !empty($applications)
                && $application = $applications[0])
                && $application instanceof Application
        ) {
            $objectEntity->setApplication($application);
        }

        return $objectEntity;
    }

    /**
     * Removes objects that should not be send or received from the data.
     *
     * @param array $object The object that should be cleared
     *
     * @return array The cleared objects
     */
    private function clearUnavailableProperties(array $object): array
    {
        $object = new Dot($object);
        // The properties to clear
        $properties = $this->configuration['apiSource']['unavailablePropertiesOut'];
        foreach ($properties as $property) {
            if ($object->has($property)) {
                $object->delete($property);
            }
        }

        return $object->jsonSerialize();
    }

    /**
     * Adds prefixes to fields that are configured to be prefixed.
     *
     * @param array $objectArray The data from the object to be edited
     *
     * @return array The updated prefix array
     */
    private function addPrefixes(array $objectArray): array
    {
        // The prefixes to add in the format 'field' => 'prefix'
        $prefixes = $this->configuration['apiSource']['prefixFieldsOut'];
        foreach ($prefixes as $id => $prefix) {
            $objectArray[$id] = $prefix.$objectArray[$id];
        }

        return $objectArray;
    }

    /**
     * Removes null fields from data.
     *
     * @param array $objectArray The data to clear
     *
     * @return array The cleared data
     */
    public function clearNull(array $objectArray): array
    {
        foreach ($objectArray as $key => $value) {
            if (!$value) {
                unset($objectArray[$key]);
            } elseif (is_array($value)) {
                $objectArray[$key] = $this->clearNull($value);
            }
        }

        return $objectArray;
    }

    /**
     * Maps output according to configuration.
     *
     * @param array $objectArray The data to map
     *
     * @return array The mapped data
     */
    private function mapOutput(array $objectArray): array
    {
        if (array_key_exists('mappingOut', $this->configuration['apiSource']) && array_key_exists('skeletonOut', $this->configuration['apiSource'])) {
            $objectArray = $this->translationService->dotHydrator(array_merge($objectArray, $this->configuration['apiSource']['skeletonOut']), $objectArray, $this->configuration['apiSource']['mappingOut']);
        } elseif (array_key_exists('mappingOut', $this->configuration['apiSource'])) {
            $objectArray = $this->translationService->dotHydrator($objectArray, $objectArray, $this->configuration['apiSource']['mappingOut']);
        } elseif (array_key_exists('skeletonOut', $this->configuration['apiSource'])) {
            $objectArray = $this->translationService->dotHydrator(array_merge($objectArray, $this->configuration['apiSource']['skeletonOut']), $objectArray, $this->configuration['apiSource']['mappingOut'] ?? []);
        }

        // Filter out unwanted properties before converting extern object to a gateway ObjectEntity
        if (array_key_exists('allowedPropertiesOut', $this->configuration['apiSource'])) {
            $objectArray = array_filter($objectArray, function ($propertyName) {
                return in_array($propertyName, $this->configuration['apiSource']['allowedPropertiesOut']);
            }, ARRAY_FILTER_USE_KEY);
        }
        if (array_key_exists('notAllowedPropertiesOut', $this->configuration['apiSource'])) {
            $objectArray = array_filter($objectArray, function ($propertyName) {
                return !in_array($propertyName, $this->configuration['apiSource']['notAllowedPropertiesOut']);
            }, ARRAY_FILTER_USE_KEY);
        }

        // todo: replace key name with notAllowedPropertiesOut because they have the same purpose
        // NOTE: this must be done after dotHydrator! ^ So that we can first do correct mapping.
        if (array_key_exists('unavailablePropertiesOut', $this->configuration['apiSource'])) {
            $objectArray = $this->clearUnavailableProperties($objectArray);
        }

        if (array_key_exists('translationsOut', $this->configuration['apiSource'])) {
            $objectArray = $this->translate($objectArray, true);
        }

        if (array_key_exists('prefixFieldsOut', $this->configuration['apiSource'])) {
            $objectArray = $this->addPrefixes($objectArray);
        }

        if (array_key_exists('clearNull', $this->configuration['apiSource']) && $this->configuration['apiSource']['clearNull']) {
            $objectArray = $this->clearNull($objectArray);
        }

        return $objectArray;
    }

    /**
     * Stores the result of a synchronization in the synchronization object.
     *
     * @param Synchronization $synchronization The synchronization object for the object that is made or updated
     * @param array           $body            The body of the call to synchronise to a source
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return Synchronization The updated synchronization object
     */
    private function storeSynchronization(Synchronization $synchronization, array $body): Synchronization
    {
        if (isset($this->configuration['apiSource']['mappingIn'])) {
            $body = $this->translationService->dotHydrator($body, $body, $this->configuration['apiSource']['mappingIn']);
        }

        try {
            $synchronization->setObject($this->populateObject($body, $synchronization->getObject(), 'PUT'));
        } catch (Exception $exception) {
            $this->ioCatchException($exception, ['line', 'file', 'message' => [
                'preMessage' => 'Error while doing syncToSource: ',
            ]]);
        }

        $body = new Dot($body);
        $now = new DateTime();

        $synchronization->setLastSynced($now);
        $synchronization->setSourceLastChanged($now);
        $synchronization->setLastChecked($now); //todo this should not be here but only in the handleSync function. But this needs to be here because we call the syncToSource function instead of handleSync function in the synchronizationPushHandler
        if ($body->has($this->configuration['apiSource']['location']['idField'])) {
            $synchronization->setSourceId($body->get($this->configuration['apiSource']['location']['idField']));
        }
        if (!$synchronization->getSource()->getTest()) {
            $synchronization->setHash(hash('sha384', serialize($body->jsonSerialize())));
        } else {
            $synchronization->setHash(serialize($body->jsonSerialize()));
        }

        return $synchronization;
    }

    /**
     * Check if the Action triggers on a specific entity and if so check if the given $entityId matches the entity from the Action conditions.
     *
     * @param string $entityId The entity id to compare to the action configuration entity id
     *
     * @return bool True if the entities match and false if they don't
     */
    private function checkActionConditionsEntity(string $entityId): bool
    {
        if (
            !isset($this->configuration['actionConditions']) ||
            (isset($this->configuration['actionConditions']['=='][0]['var']) &&
                isset($this->configuration['actionConditions']['=='][1]) &&
                $this->configuration['actionConditions']['=='][0]['var'] === 'entity' &&
                $this->configuration['actionConditions']['=='][1] === $entityId)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Encodes the object dependent on the settings for the synchronization action.
     *
     * @param array $objectArray The object array to encode
     *
     * @return string The encoded object
     */
    public function getObjectString(array $objectArray = []): string
    {
        if (!$objectArray) {
            return '';
        }
        $mediaType = $this->configuration['apiSource']['location']['contentType'] ?? 'application/json';
        switch ($mediaType) {
            case 'text/xml':
            case 'application/xml':
                $xmlEncoder = new XmlEncoder(['xml_root_node_name' => $this->configuration['apiSource']['location']['xmlRootNodeName'] ?? 'response']);

                return $xmlEncoder->encode($objectArray, 'xml');
            case 'application/json':
            default:
                return json_encode($objectArray);
        }
    }

    /**
     * Synchronises a new object in the gateway to it source, or an object updated in the gateway.
     *
     * @param Synchronization $synchronization The synchronization object for the created or updated object
     * @param bool            $existsInSource  Determines if a new synchronization should be made, or an existing one should be updated
     *
     * @throws CacheException|InvalidArgumentException|LoaderError|SyntaxError|GuzzleException
     *
     * @return Synchronization The updated synchronization object
     */
    private function syncToSource(Synchronization $synchronization, bool $existsInSource): Synchronization
    {
        if (isset($this->io)) {
            $this->io->text("syncToSource for Synchronization with id = {$synchronization->getId()->toString()}");
        }
        $this->logger->info("syncToSource for Synchronization with id = {$synchronization->getId()->toString()}");

        if ($synchronization->isBlocked()) {
            return $synchronization;
        }
        $object = $synchronization->getObject();
        $objectArray = $object->toArray();

        //        $objectArray = $this->objectEntityService->checkGetObjectExceptions($data, $object, [], ['all' => true], 'application/ld+json');
        // todo: maybe move this to foreach in getAllFromSource() (nice to have)
        $callServiceConfig = $this->getCallServiceConfig($synchronization->getSource(), $existsInSource ? $synchronization->getSourceId() : null, $objectArray);
        $objectArray = $this->mapOutput($objectArray);

        $endpoint = $synchronization->getEndpoint();
        if ($existsInSource === true) {
            $endpoint = $endpoint.'/'.$synchronization->getSourceId();
        }
        if (str_contains('http', $synchronization->getSourceId()) === true) {
            $endpoint = $synchronization->getEndpoint();
        }
        if (isset($this->configuration['location']) === true) {
            $endpoint = $this->configuration['location'];
            $synchronization->setEndpoint($endpoint);
        }

        $objectString = $this->getObjectString($objectArray);

        try {
            $result = $this->callService->call(
                $callServiceConfig['source'],
                $endpoint,
                $callServiceConfig['method'] ?? ($existsInSource ? 'PUT' : 'POST'),
                [
                    'body'    => $objectString,
                    'query'   => $callServiceConfig['query'],
                    'headers' => $callServiceConfig['headers'],
                ]
            );
        } catch (Exception|GuzzleException $exception) {
            $this->ioCatchException($exception, ['line', 'file', 'message' => [
                'preMessage' => 'Error while doing syncToSource: ',
            ]]);
            $this->asyncError = true;

            return $synchronization;
        }
        $contentType = $result->getHeader('content-type')[0];
        if (!$contentType) {
            $contentType = $result->getHeader('Content-Type')[0];
        }
        $body = $this->callService->decodeResponse($callServiceConfig['source'], $result);

        return $this->storeSynchronization($synchronization, $body);
    }

    /**
     * Maps input according to configuration.
     *
     * @param array $sourceObject The external object to synchronise from, the data to map
     *
     * @return array The mapped data
     */
    private function mapInput(array $sourceObject): array
    {
        // What if we do not have a cnnfiguration?
        if (!isset($this->configuration) || empty($this->configuration)) {
            return  $sourceObject;
        }

        if (array_key_exists('mappingIn', $this->configuration['apiSource']) && array_key_exists('skeletonIn', $this->configuration['apiSource'])) {
            $sourceObject = $this->translationService->dotHydrator(array_merge($sourceObject, $this->configuration['apiSource']['skeletonIn']), $sourceObject, $this->configuration['apiSource']['mappingIn']);
        } elseif (array_key_exists('mappingIn', $this->configuration['apiSource'])) {
            $sourceObject = $this->translationService->dotHydrator($sourceObject, $sourceObject, $this->configuration['apiSource']['mappingIn']);
        } elseif (array_key_exists('skeletonOut', $this->configuration['apiSource'])) {
            // todo: this^ should be skeletonIn ?
            $sourceObject = $this->translationService->dotHydrator(array_merge($sourceObject, $this->configuration['apiSource']['skeletonIn']), $sourceObject, $sourceObject);
        }

        // Filter out unwanted properties before converting extern object to a gateway ObjectEntity
        if (array_key_exists('allowedPropertiesIn', $this->configuration['apiSource'])) {
            $sourceObject = array_filter($sourceObject, function ($propertyName) {
                return in_array($propertyName, $this->configuration['apiSource']['allowedPropertiesIn']);
            }, ARRAY_FILTER_USE_KEY);
        }
        if (array_key_exists('notAllowedPropertiesIn', $this->configuration['apiSource'])) {
            $sourceObject = array_filter($sourceObject, function ($propertyName) {
                return !in_array($propertyName, $this->configuration['apiSource']['notAllowedPropertiesIn']);
            }, ARRAY_FILTER_USE_KEY);
        }

        if (array_key_exists('mappingIn', $this->configuration['apiSource'])) {
            $sourceObject = $this->translationService->dotHydrator(isset($this->configuration['apiSource']['skeletonIn']) ? array_merge($sourceObject, $this->configuration['apiSource']['skeletonIn']) : $sourceObject, $sourceObject, $this->configuration['apiSource']['mappingIn']);
        }

        if (array_key_exists('translationsIn', $this->configuration['apiSource'])) {
            $sourceObject = $this->translate($sourceObject);
        }

        return $sourceObject;
    }

    /**
     * Synchronises data from an external source to the internal database of the gateway.
     *
     * @param Synchronization $synchronization The synchronization object to update
     * @param array           $sourceObject    The external object to synchronise from
     *
     * @throws GatewayException|CacheException|InvalidArgumentException|ComponentException|Exception
     *
     * @return Synchronization The updated synchronization object containing an updated objectEntity
     */
    private function syncToGateway(Synchronization $synchronization, array $sourceObject, string $method = 'POST'): Synchronization
    {
        if (isset($this->io)) {
            $this->io->text("syncToGateway for Synchronization with id = {$synchronization->getId()->toString()}");
        }
        $this->logger->info("syncToGateway for Synchronization with id = {$synchronization->getId()->toString()}");

        $object = $synchronization->getObject();

        if ($synchronization->getMapping()) {
            $sourceObject = $this->mappingService->mapping($synchronization->getMapping(), $sourceObject);
        }

        $sourceObjectDot = new Dot($sourceObject);

        $object = $this->populateObject($sourceObject, $object, $method);

        if (isset($this->configuration['apiSource']['location']['dateCreatedField'])) {
            $object->setDateCreated(new DateTime($sourceObjectDot->get($this->configuration['apiSource']['location']['dateCreatedField'])));
        }
        if (isset($this->configuration['apiSource']['location']['dateChangedField'])) {
            $object->setDateModified(new DateTime($sourceObjectDot->get($this->configuration['apiSource']['location']['dateChangedField'])));
        }

        $now = new DateTime();
        $synchronization->setLastSynced($now);
        $synchronization->setTryCounter(0);

        return $synchronization->setObject($object);
    }

    /**
     * This function doesn't do anything right now.
     *
     * @param Synchronization $synchronization
     * @return Synchronization
     */
    private function syncThroughComparing(Synchronization $synchronization): Synchronization
    {
        return $synchronization;
    }

    /**
     * Find an object by URL, and synchronize it if it does not exist in the gateway.
     *
     * @param string $url The URL of the object
     * @param Entity $entity The schema the object should fit into
     *
     * @throws GuzzleException|LoaderError|SyntaxError|UrlException
     *
     * @return ObjectEntity|null
     */
    public function aquireObject(string $url, Entity $entity): ?ObjectEntity
    {
        $source = $this->resourceService->findSourceForUrl($url, 'conduction-nl/commonground-gateway', $endpoint);
        $sourceId = $this->getSourceId($endpoint, $url);

        $synchronization = $this->findSyncBySource($source, $entity, $sourceId, $endpoint);

        $this->synchronize($synchronization);

        return $synchronization->getObject();
    }

    /**
     * A function best used after resourceService->findSourceForUrl and/or before $this->findSyncBySource.
     * This function will get the uuid / int id from the end of an endpoint. This is the sourceId for a Synchronization.
     *
     * @param string|null $endpoint The endpoint to get the SourceId from.
     * @param string|null $url The url used as back-up for SourceId if no proper SourceId can be found.
     *
     * @return string|null The sourceId, will be equal to $url if end part of the endpoint isn't an uuid or integer. And will return null if $endpoint & $url ar both null.
     */
    public function getSourceId(?string &$endpoint, ?string $url = null): ?string
    {
        if ($endpoint === null) {
            return $url;
        }

        $explodedEndpoint = explode('/', $endpoint);
        $sourceId = end($explodedEndpoint);
        if (Uuid::isValid($sourceId) === true || is_int((int) $sourceId) === true) {
            $endpoint = str_replace("/$sourceId", '', $endpoint);
        } else {
            $sourceId = $url;
        }

        return $sourceId;
    }
}
