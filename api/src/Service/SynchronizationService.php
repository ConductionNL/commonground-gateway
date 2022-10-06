<?php

namespace App\Service;

use Adbar\Dot;
use App\Entity\Application;
use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Exception\GatewayException;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use function Symfony\Component\Translation\t;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

class SynchronizationService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;
    private GatewayService $gatewayService;
    private FunctionService $functionService;
    private LogService $logService;
    private MessageBusInterface $messageBus;
    private TranslationService $translationService;
    private ObjectEntityService $objectEntityService;
    private ValidatorService $validatorService;
    private array $configuration;
    private array $data;
    private Environment $twig;

    /**
     * @param CommonGroundService    $commonGroundService
     * @param EntityManagerInterface $entityManager
     * @param SessionInterface       $session
     * @param GatewayService         $gatewayService
     * @param FunctionService        $functionService
     * @param LogService             $logService
     * @param MessageBusInterface    $messageBus
     * @param TranslationService     $translationService
     * @param ObjectEntityService    $objectEntityService
     * @param ValidatorService       $validatorService
     * @param EavService             $eavService
     * @param Environment            $twig
     */
    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, SessionInterface $session, GatewayService $gatewayService, FunctionService $functionService, LogService $logService, MessageBusInterface $messageBus, TranslationService $translationService, ObjectEntityService $objectEntityService, ValidatorService $validatorService, EavService $eavService, Environment $twig)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->gatewayService = $gatewayService;
        $this->functionService = $functionService;
        $this->logService = $logService;
        $this->messageBus = $messageBus;
        $this->translationService = $translationService;
        $this->objectEntityService = $objectEntityService;
        $this->objectEntityService->addServices($eavService);
        $this->validatorService = $validatorService;
        $this->configuration = [];
        $this->data = [];
        $this->twig = $twig;
    }

    /**
     * @param array $data
     * @param array $configuration
     *
     * @return array
     *
     * @todo
     */
    public function SynchronizationItemHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

        return $data;
    }

    /**
     * Synchronises objects in the gateway to a source.
     *
     * @param array $data          The data from the action
     * @param array $configuration The configuration given by the action
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array The data from the action modified by the execution of the synchronisation
     */
    public function synchronisationPushHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $source = $this->getSourceFromConfig();
        $entity = $this->getEntityFromConfig();

        if (!($entity instanceof Entity)) {
            return $this->data;
        }

        foreach ($entity->getObjectEntities() as $object) {
            $synchronisation = $this->findSyncByObject($object, $source, $entity);
            if (!$synchronisation->getLastSynced()) {
                $synchronisation = $this->syncToSource($synchronisation, false);
            } elseif ($object->getDateModified() > $synchronisation->getLastSynced()) {
                $synchronisation = $this->syncToSource($synchronisation, true);
            }
            $this->entityManager->persist($synchronisation);
            $this->entityManager->flush();
        }

        return $this->data;
    }

    /**
     * @param array $data
     * @param array $configuration
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return array
     *
     * @todo
     */
    public function SynchronizationWebhookHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;
        $sourceObject = [];
        $responseData = $data['response'];

        $gateway = $this->getSourceFromConfig();
        $entity = $this->getEntityFromConfig();

        // Dot the data array and try to find id in it
        $dot = new Dot($responseData);
        $id = $dot->get($this->configuration['apiSource']['location']['idField']);

        // If we have a complete object we can use that to sync
        if (array_key_exists('Object', $this->configuration['location'])) {
            $sourceObject = $dot->get($this->configuration['apiSource']['location']['object'], $responseData); // todo should default be $data or [] ?
        }

        // Lets grab the sync object, if we don't find an existing one, this will create a new one: via config
        $synchronization = $this->findSyncBySource($gateway, $entity, $id);

        // Lets sync (returns the Synchronization object), will do a get on the source if $sourceObject = []
        $synchronization = $this->handleSync($synchronization, $sourceObject);

        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        return $responseData;
    }

    /**
     * Gets all objects from the source according to configuration.
     *
     * @param array $data          Data from the request running
     * @param array $configuration Configuration for the action running
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return array The resulting data
     */
    public function SynchronizationCollectionHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        // todo: i think we need the Action here, because we need to set it with $synchronization->setAction($action) later...
        // todo: if we do this, some functions that have $synchronization no longer need $configuration because we can do: $synchronization->getAction()->getConfiguration()
        $gateway = $this->getSourceFromConfig();
        $entity = $this->getEntityFromConfig();

        // Get json/array results based on the type of source
        $results = $this->getObjectsFromSource($gateway);

        foreach ($results as $result) {
            // @todo this could and should be async (nice to have)

            // Turn it in a dot array to find the correct data in $result...
            $dot = new Dot($result);
            // The place where we can find the id field when looping through the list of objects, from $result root, by object (dot notation)
            $id = $dot->get($this->configuration['apiSource']['location']['idField']);

            // The place where we can find an object when we walk through the list of objects, from $result root, by object (dot notation)
            array_key_exists('object', $this->configuration['apiSource']['location']) && $result = $dot->get($this->configuration['apiSource']['location']['object'], $result);

            // Lets grab the sync object, if we don't find an existing one, this will create a new one:
            $synchronization = $this->findSyncBySource($gateway, $entity, $id);
            // todo: Another search function for sync object. If no sync object is found, look for matching properties in $result and an ObjectEntity in db. And then create sync for an ObjectEntity if we find one this way. (nice to have)
            // Other option to find a sync object, currently not used:
//            $synchronization = $this->findSyncByObject($object, $gateway, $entity);

            // Lets sync (returns the Synchronization object)
            if (array_key_exists('useDataFromCollection', $this->configuration) and !$this->configuration['useDataFromCollection']) {
                $result = [];
            }
            $synchronization = $this->handleSync($synchronization, $result);

            $this->entityManager->persist($synchronization);
            $this->entityManager->flush();
        }

        return $results;
    }

    /**
     * Searches and returns the source of the configuration in the database.
     *
     * @return Gateway|null The found source for the configuration
     */
    private function getSourceFromConfig(): ?Gateway
    {
        if (isset($this->configuration['source'])) {
            $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['id' => $this->configuration['source']]);
            if ($source instanceof Gateway) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Searches and returns the entity of the configuration in the database.
     *
     * @return Entity|null The found entity for the configuration
     */
    private function getEntityFromConfig(): ?Entity
    {
        if (isset($this->configuration['entity'])) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $this->configuration['entity']]);
            if ($entity instanceof Entity) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * Determines the configuration for using the callservice for the source given.
     *
     * @param Gateway     $gateway     The source to call
     * @param string|null $id          The id to request (optional)
     * @param array|null  $objectArray
     *
     * @throws LoaderError|SyntaxError
     *
     * @return array The configuration for the source to call
     */
    private function getCallServiceConfig(Gateway $gateway, string $id = null, ?array $objectArray = []): array
    {
        return [
            'component' => $this->gatewayService->gatewayToArray($gateway),
            'url'       => $this->getUrlForSource($gateway, $id, $objectArray),
            'query'     => $this->getCallServiceOverwrite('query') ?? $this->getQueryForCallService($id), //todo maybe array_merge instead of ??
            'headers'   => $this->getCallServiceOverwrite('headers') ?? $gateway->getHeaders(), //todo maybe array_merge instead of ??
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
     * @param Gateway     $gateway     The source to call
     * @param string|null $id          The id to request (optional)
     * @param array|null  $objectArray
     *
     * @throws LoaderError|SyntaxError
     *
     * @return string The resulting URL
     */
    private function getUrlForSource(Gateway $gateway, string $id = null, ?array $objectArray = []): string
    {
        $renderData = array_key_exists('replaceTwigLocation', $this->configuration) && $this->configuration['replaceTwigLocation'] === 'objectEntityData' ? $objectArray : $this->data;
        $location = $this->twig->createTemplate($this->configuration['location'])->render($renderData);

        if (isset($this->configuration['queryParams']['syncSourceId'])) {
            $id = null;
        }

        return $gateway->getLocation().$location.($id ? '/'.$id : '');
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
        $query = [];
        // todo: maybe move this specific option to fetchObjectsFromSource, because it is specifically used for get collection calls on the source.
        if (array_key_exists('sourceLimit', $this->configuration)) {
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
     * @param Gateway $gateway The source to get the data from
     *
     * @return array The results found on the source
     */
    private function getObjectsFromSource(Gateway $gateway): array
    {
        $callServiceConfig = $this->getCallServiceConfig($gateway);
        // Right now there are two options, either api source is paginated or it is not
        $results = $this->fetchObjectsFromSource($callServiceConfig);

        // todo: more options ...

        return $results;
    }

    /**
     * Fetches the objects stored on the source.
     *
     * @param array $callServiceConfig The configuration for the source
     * @param int   $page              The current page to be requested
     *
     * @return array
     */
    private function fetchObjectsFromSource(array $callServiceConfig, int $page = 1): array
    {
        // Get a single page
        if (is_array($callServiceConfig['query'])) {
            $query = array_merge($callServiceConfig['query'], $page !== 1 ? ['page' => $page] : []);
        } else {
            $query = $callServiceConfig['query'].'&page='.$page;
        }

        try {
            $response = $this->commonGroundService->callService(
                $callServiceConfig['component'],
                $callServiceConfig['url'],
                '',
                $query,
                $callServiceConfig['headers'],
                false,
                $callServiceConfig['method'] ?? 'GET'
            );

            if (is_array($response)) {
                throw new Exception('Callservice error while doing fetchObjectsFromSource');
            }
        } catch (Exception $exception) {
            // If no next page with this $page exists...
            return [];
            //todo: error, user feedback and log this?
//            throw new GatewayException('Callservice error while doing fetchObjectsFromSource', null, null, [
//                'data' => [
//                    'message'           => $exception->getMessage(),
//                    'code'              => $exception->getCode(),
//                    'response'          => $response ?? null,
//                    'trace'             => $exception->getTraceAsString()
//                ],
//                'path' => $callServiceConfig['url'], 'responseType' => Response::HTTP_BAD_REQUEST
//            ]);
        }

        $pageResult = json_decode($response->getBody()->getContents(), true);

        $dot = new Dot($pageResult);
        $results = $dot->get($this->configuration['apiSource']['location']['objects'], $pageResult);

        if (array_key_exists('limit', $this->configuration['apiSource']) && count($results) >= $this->configuration['apiSource']['limit']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($callServiceConfig, $page + 1));
        } elseif (!empty($results) && isset($this->configuration['apiSource']['sourcePaginated']) && $this->configuration['apiSource']['sourcePaginated']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($callServiceConfig, $page + 1));
        }

        return $results;
    }

    /**
     * Gets a single object from the source.
     *
     * @param Synchronization $synchronization The synchronisation object with the related source object id
     *
     * @return array|null The resulting object
     */
    private function getSingleFromSource(Synchronization $synchronization): ?array
    {
        $callServiceConfig = $this->getCallServiceConfig($synchronization->getGateway(), $synchronization->getSourceId());

        // Get object form source with callservice
        try {
            $response = $this->commonGroundService->callService(
                $callServiceConfig['component'],
                $synchronization->getEndpoint() ?? $callServiceConfig['url'],
                '',
                $callServiceConfig['query'],
                $callServiceConfig['headers'],
                false,
                $callServiceConfig['method'] ?? 'GET'
            );

            if (is_array($response)) {
                throw new Exception('Callservice error while doing getSingleFromSource');
            }
        } catch (Exception $exception) {
            return null;
//            //todo: error, user feedback and log this?
//            throw new GatewayException('Callservice error while doing getSingleFromSource', null, null, [
//                'data' => [
//                    'message'           => $exception->getMessage(),
//                    'code'              => $exception->getCode(),
//                    'trace'             => $exception->getTraceAsString()
//                ],
//                'path' => $callServiceConfig['url'], 'responseType' => Response::HTTP_BAD_REQUEST
//            ]);
        }
        $result = json_decode($response->getBody()->getContents(), true);
        $dot = new Dot($result);
        // The place where we can find the id field when looping through the list of objects, from $result root, by object (dot notation)
//        $id = $dot->get($this->configuration['locationIdField']); // todo, not sure if we need this here or later?

        // The place where we can find an object when we walk through the list of objects, from $result root, by object (dot notation)
        return $dot->get($this->configuration['apiSource']['location']['object'], $result);
    }

    /**
     * Finds a synchronisation object if it exists for the current object in the source, or creates one if it doesn't exist.
     *
     * @param Gateway $source   The source that is requested
     * @param Entity  $entity   The entity that is requested
     * @param string  $sourceId The id of the object in the source
     *
     * @return Synchronization|null A synchronisation object related to the object in the source
     */
    public function findSyncBySource(Gateway $source, Entity $entity, string $sourceId): ?Synchronization
    {
        $synchronization = $this->entityManager->getRepository('App:Synchronization')->findOneBy(['gateway' => $source->getId(), 'entity' => $entity->getId(), 'sourceId' => $sourceId]);

        if ($synchronization instanceof Synchronization) {
            return $synchronization;
        }

        $synchronization = new Synchronization();
        $synchronization->setGateway($source);
        $synchronization->setEntity($entity);
        $synchronization->setSourceId($sourceId);
        $this->entityManager->persist($synchronization);
        // We flush later

        return $synchronization;
    }

    /**
     * Finds a synchronisation object if it exists for the current object in the gateway, or creates one if it doesn't exist.
     *
     * @param ObjectEntity $objectEntity The current object in the gateway
     * @param Gateway      $source       The current source
     * @param Entity       $entity       The current entity
     *
     * @return Synchronization|null A synchronisation object related to the object in the gateway
     */
    private function findSyncByObject(ObjectEntity $objectEntity, Gateway $source, Entity $entity): ?Synchronization
    {
        $synchronization = $this->entityManager->getRepository('App:Synchronization')->findOneBy(['object' => $objectEntity->getId(), 'gateway' => $source, 'entity' => $entity]);
        if ($synchronization instanceof Synchronization) {
            return $synchronization;
        }

        $synchronization = new Synchronization();
        $synchronization->setObject($objectEntity);
        $synchronization->setGateway($source);
        $synchronization->setEntity($entity);
        $synchronization->setSourceId($objectEntity->getId());
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        return $synchronization;
    }

    /**
     * Adds a new ObjectEntity to a synchronisation object.
     *
     * @param Synchronization $synchronization The synchronisation object without object
     *
     * @return Synchronization The update synchronisation object with object
     */
    private function checkObjectEntity(Synchronization $synchronization): Synchronization
    {
        if (!$synchronization->getObject()) {
            $object = new ObjectEntity();
            $object->setExternalId($synchronization->getSourceId());
            $object->setEntity($synchronization->getEntity());
            $object = $this->setApplicationAndOrganization($object);
            $synchronization->setObject($object);
            $this->entityManager->persist($object);
        }

        return $synchronization;
    }

    /**
     * Sets the last changed date from the source object and creates a hash for the source object.
     *
     * @param Synchronization $synchronization The synchronisation object to update
     * @param array           $sourceObject    The object returned from the source
     *
     * @throws Exception
     *
     * @return Synchronization The updated source object
     */
    private function setLastChangedDate(Synchronization $synchronization, array $sourceObject): Synchronization
    {
        $hash = hash('sha384', serialize($sourceObject));
        $dot = new Dot($sourceObject);
        if (isset($this->configuration['apiSource']['location']['dateChangedField'])) {
            $lastChanged = $dot->get($this->configuration['apiSource']['location']['dateChangedField']);
            $synchronization->setSourcelastChanged(new DateTime($lastChanged));
        } elseif ($synchronization->getHash() != $hash) {
            $lastChanged = new DateTime();
            $synchronization->setSourcelastChanged($lastChanged);
        }
        $synchronization->setHash($hash);

        return $synchronization;
    }

    /**
     * Executes the synchronisation between source and gateway.
     *
     * @param Synchronization $synchronization The synchronisation object before synchronisation
     * @param array           $sourceObject    The object in the source
     *
     *@throws GatewayException|CacheException|InvalidArgumentException|ComponentException
     *
     * @return Synchronization The updated synchronisation object
     */
    public function handleSync(Synchronization $synchronization, array $sourceObject = []): Synchronization
    {
        $this->checkObjectEntity($synchronization);

        // If we don't have an sourced object, we need to get one
        $sourceObject = $sourceObject ?: $this->getSingleFromSource($synchronization);
        if ($sourceObject === null) {
            //todo: error, user feedback and log this? (see getSingleFromSource function)
            return $synchronization;
        }

        $synchronization = $this->setLastChangedDate($synchronization, $sourceObject);

        //Checks which is newer, the object in the gateway or in the source, and synchronise accordingly
        if (!$synchronization->getLastSynced() || ($synchronization->getLastSynced() < $synchronization->getSourceLastChanged() && $synchronization->getSourceLastChanged() > $synchronization->getObject()->getDateModified())) {
            $synchronization = $this->syncToGateway($synchronization, $sourceObject);
        } elseif ((!$synchronization->getLastSynced() || $synchronization->getLastSynced() < $synchronization->getObject()->getDateModified()) && $synchronization->getSourceLastChanged() < $synchronization->getObject()->getDateModified()) {
            $synchronization = $this->syncToSource($synchronization, true);
        } else {
            $synchronization = $this->syncThroughComparing($synchronization);
        }

        return $synchronization;
    }

    /**
     * This function populates a pre-existing objectEntity with data that has been validated.
     * This function is only meant for synchronisation.
     *
     * @param array        $data         The data that has to go into the objectEntity
     * @param ObjectEntity $objectEntity The ObjectEntity to populate
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     * @throws GatewayException
     *
     * @return ObjectEntity The populated ObjectEntity
     */
    public function populateObject(array $data, ObjectEntity $objectEntity, ?string $method = 'POST'): ObjectEntity
    {
        // todo: move this function to ObjectEntityService to prevent duplicate code...

        $this->setApplicationAndOrganization($objectEntity);

        $owner = $this->objectEntityService->checkAndUnsetOwner($data);
        if (array_key_exists('owner', $this->configuration)) {
            $owner = $this->configuration['owner'];
        }

        if ($validationErrors = $this->validatorService->validateData($data, $objectEntity->getEntity(), $method)) {
            //@TODO: Write errors to logs

//            var_dump($validationErrors);
            foreach ($validationErrors as $error) {
                if (!is_array($error) && strpos($error, 'must be present') !== false) {
                    return $objectEntity;
                }
            }
        }

        $data = $this->objectEntityService->createOrUpdateCase($data, $objectEntity, $owner, $method, 'application/ld+json');
        // todo: this dispatch should probably be moved to the createOrUpdateCase function!?
        $this->objectEntityService->dispatchEvent($method == 'POST' ? 'commongateway.object.create' : 'commongateway.object.update', ['response' => $data, 'entity' => $objectEntity->getEntity()->getId()->toString()]);

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
     *
     * @param ObjectEntity $objectEntity The ObjectEntity to update
     *
     * @return ObjectEntity The updated ObjectEntity
     */
    public function setApplicationAndOrganization(ObjectEntity $objectEntity): ObjectEntity
    {
        // todo move this to ObjectEntityService to prevent duplicate code
        $application = $this->entityManager->getRepository('App:Application')->findOneBy(['name' => 'main application']);
        if ($application instanceof Application) {
            $objectEntity->setApplication($application);
            $objectEntity->setOrganization($application->getOrganization());
        } elseif (
            ($application = $this->entityManager->getRepository('App:Application')->findAll()[0]) && $application instanceof Application
        ) {
            $objectEntity->setApplication($application);
            $objectEntity->setOrganization($application->getOrganization());
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
        // The properties to clear
        $properties = $this->configuration['apiSource']['unavailablePropertiesOut'];
        foreach ($properties as $property) {
            if (key_exists($property, $object)) {
                unset($object[$property]);
            }
        }

        return $object;
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
            $objectArray = $this->translationService->dotHydrator(array_merge($objectArray, $this->configuration['apiSource']['skeletonOut']), $objectArray, $objectArray);
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
     * Stores the result of a synchronisation in the synchronization object.
     *
     * @param Synchronization $synchronization The synchronisation object for the object that is made or updated
     * @param array           $body            The body of the call to synchronise to a source
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return Synchronization The updated synchronization object
     */
    private function storeSynchronization(Synchronization $synchronization, array $body): Synchronization
    {
        try {
            $synchronization->setObject($this->populateObject($body, $synchronization->getObject(), 'PUT'));
        } catch (Exception $exception) {
            return $synchronization;
        }

        $body = new Dot($body);
        $now = new DateTime();

        $synchronization->setLastSynced($now);
        $synchronization->setSourceLastChanged($now);
        $synchronization->setLastChecked($now);
        if ($body->has($this->configuration['apiSource']['location']['idField'])) {
            $synchronization->setSourceId($body->get($this->configuration['apiSource']['location']['idField']));
        }
        $synchronization->setHash(hash('sha384', serialize($body->jsonSerialize())));

        return $synchronization;
    }

    /**
     * Synchronises a new object in the gateway to it source, or an object updated in the gateway.
     *
     * @param Synchronization $synchronization The synchronisation object for the created or updated object
     * @param bool            $existsInSource  Determines if a new synchronisation should be made, or an existing one should be updated
     *
     * @throws CacheException|InvalidArgumentException|LoaderError|SyntaxError
     *
     * @return Synchronization The updated synchronisation object
     */
    private function syncToSource(Synchronization $synchronization, bool $existsInSource): Synchronization
    {
        $object = $synchronization->getObject();
        $objectArray = $object->toArray();

        //        $objectArray = $this->objectEntityService->checkGetObjectExceptions($data, $object, [], ['all' => true], 'application/ld+json');
        // todo: maybe move this to foreach in getAllFromSource() (nice to have)
        $callServiceConfig = $this->getCallServiceConfig($synchronization->getGateway(), null, $objectArray);
        $objectArray = $this->mapOutput($objectArray);

        try {
            $result = $this->commonGroundService->callService(
                $callServiceConfig['component'],
                $synchronization->getEndpoint() ?? $callServiceConfig['url'],
                json_encode($objectArray),
                $callServiceConfig['query'],
                $callServiceConfig['headers'],
                false,
                $callServiceConfig['method'] ?? ($existsInSource ? 'PUT' : 'POST')
            );

            if (is_array($result)) {
                throw new Exception('Callservice error while doing syncToSource');
            }
        } catch (Exception $exception) {
            return $synchronization;
            //todo: error, user feedback and log this?
//            throw new GatewayException('Callservice error while doing syncToSource', null, null, [
//                'data' => [
//                    'message'           => $exception->getMessage(),
//                    'code'              => $exception->getCode(),
//                    'trace'             => $exception->getTraceAsString()
//                ],
//                'path' => $callServiceConfig['url'], 'responseType' => Response::HTTP_BAD_REQUEST
//            ]);
        }

        $body = json_decode($result->getBody()->getContents(), true);

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
        if (array_key_exists('mappingIn', $this->configuration['apiSource']) && array_key_exists('skeletonIn', $this->configuration['apiSource'])) {
            $sourceObject = $this->translationService->dotHydrator(array_merge($sourceObject, $this->configuration['apiSource']['skeletonIn']), $sourceObject, $this->configuration['apiSource']['mappingIn']);
        } elseif (array_key_exists('mappingIn', $this->configuration['apiSource'])) {
            $sourceObject = $this->translationService->dotHydrator($sourceObject, $sourceObject, $this->configuration['apiSource']['mappingIn']);
        } elseif (array_key_exists('skeletonOut', $this->configuration['apiSource'])) {
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
     * @param Synchronization $synchronization The synchronisation object to update
     * @param array           $sourceObject    The external object to synchronise from
     *
     * @throws GatewayException|CacheException|InvalidArgumentException|ComponentException
     * @throws Exception
     *
     * @return Synchronization The updated synchronisation object containing an updated objectEntity
     */
    private function syncToGateway(Synchronization $synchronization, array $sourceObject): Synchronization
    {
        $object = $synchronization->getObject();

        $sourceObject = $this->mapInput($sourceObject);

        $sourceObjectDot = new Dot($sourceObject);

        $object = $this->populateObject($sourceObject, $object);
        $object->setUri($this->getUrlForSource($synchronization->getGateway(), $synchronization->getSourceId()));
        if (isset($this->configuration['apiSource']['location']['dateCreatedField'])) {
            $object->setDateCreated(new DateTime($sourceObjectDot->get($this->configuration['apiSource']['location']['dateCreatedField'])));
        }
        if (isset($this->configuration['apiSource']['location']['dateChangedField'])) {
            $object->setDateModified(new DateTime($sourceObjectDot->get($this->configuration['apiSource']['location']['dateChangedField'])));
        }

        return $synchronization->setObject($object);
    }

    // todo: docs
    private function syncThroughComparing(Synchronization $synchronization): Synchronization
    {
        return $synchronization;
    }
}
