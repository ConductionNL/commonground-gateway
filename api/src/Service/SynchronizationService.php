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
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

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
     */
    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, SessionInterface $session, GatewayService $gatewayService, FunctionService $functionService, LogService $logService, MessageBusInterface $messageBus, TranslationService $translationService, ObjectEntityService $objectEntityService, ValidatorService $validatorService, EavService $eavService)
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
        $this->objectEntityService->addServices($eavService->getValidationService(), $eavService);
        $this->validatorService = $validatorService;
        $this->configuration = [];
    }

    /**
     * @todo
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     */
    public function SynchronizationItemHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

        return $data;
    }

    /**
     * @todo
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     */
    public function SynchronizationWebhookHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $sourceObject = [];

        // Checks verplaatsen naar de handler
        if (in_array("source", $this->configuration) || in_array("entity", $this->configuration) || in_array("idLocation", $this->configuration)){
            // thro gateway error
        }

        $gateway = $this->getSourceFromConfig();
        $entity = $this->getEntityFromConfig();

        // Aan de hand van location id de id uit $datah halen
        $id = .....

        // If we have a complete object we can use that to sync
        if(in_array("objectLocation", $this->configuration)){
            $sourceObject = /// get from data aan de hand van object location
        }

        // Lets grab the sync object, if we don't find an existing one, this will create a new one: via config
        $sync = $this->findSyncBySource($gateway, $entity, $id);

        // Lets sync (returns the Synchronization object)
        $sync = $this->handleSync($sync, $this->configuration, $sourceObject);

        $this->entityManager->persist($sync);
        $this->entityManager->flush();

        return $data;
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

        // todo: i think we need the Action here, because we need to set it with $sync->setAction($action) later...
        // todo: if we do this, some functions that have $sync no longer need $configuration because we can do: $sync->getAction()->getConfiguration()
        $gateway = $this->getSourceFromConfig();
        $entity = $this->getEntityFromConfig();

        // Get json/array results based on the type of source
        $results = $this->getObjectsFromSource($gateway);

        foreach ($results as $result) {
            // @todo this could and should be async (nice to have)

            // Turn it in a dot array to find the correct data in $result...
            $dot = new Dot($result);
            // The place where we can find the id field when looping through the list of objects, from $result root, by object (dot notation)
            $id = $dot->get($this->configuration['apiSource']['idField']);

            // The place where we can find an object when we walk through the list of objects, from $result root, by object (dot notation)
            $result = $dot->get($this->configuration['apiSource']['locationObjects'], $result);

            // Lets grab the sync object, if we don't find an existing one, this will create a new one:
            $sync = $this->findSyncBySource($gateway, $entity, $id);
            // todo: Another search function for sync object. If no sync object is found, look for matching properties in $result and an ObjectEntity in db. And then create sync for an ObjectEntity if we find one this way. (nice to have)
            // Other option to find a sync object, currently not used:
//            $sync = $this->findSyncByObject($object, $gateway, $entity);

            // Lets sync (returns the Synchronization object)
            if (array_key_exists('useDataFromCollection', $this->configuration) and !$this->configuration['useDataFromCollection']) {
                $result = [];
            }
            $sync = $this->handleSync($sync, $result);

            $this->entityManager->persist($sync);
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
        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['id' => $this->configuration['source']]);

        if ($source instanceof Gateway) {
            return $source;
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
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $this->configuration['entity']]);

        if ($entity instanceof Entity) {
            return $entity;
        }

        return null;
    }

    /**
     * Gets the configuration for the source and fetches the results on the source.
     *
     * @param Gateway $gateway       The source to get the data from
     *
     * @return array The results found on the source
     */
    private function getObjectsFromSource(Gateway $gateway): array
    {
        $callServiceConfig = $this->getCallServiceConfig($gateway);
        // Right now there are two options, either api source is paginated or it is not
        $results = $this->fetchObjectsFromSource($callServiceConfig);

        return $results;
    }

    /**
     * Determines the configuration for the source given.
     *
     * @param Gateway $gateway       The source to call
     *
     * @return array The configuration for the source to call
     */
    private function getCallServiceConfig(Gateway $gateway): array
    {
        return [
            'component' => $this->gatewayService->gatewayToArray($gateway),
            'url'       => $this->getUrlForSource($gateway),
            'query'     => array_key_exists('sourceLimit', $this->configuration) ? ['limit' => $this->configuration['sourceLimit']] : [],
            'headers'   => $gateway->getHeaders(),
        ];
    }

    /**
     * Determines the URL to request.
     *
     * @param Gateway     $gateway       The source to call
     * @param string|null $id            The id to request (optional)
     *
     * @return string The resulting URL
     */
    private function getUrlForSource(Gateway $gateway, string $id = null): string
    {
        return $gateway->getLocation().$this->configuration['location'].($id ? '/'.$id : '');
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
        $response = $this->commonGroundService->callService(
            $callServiceConfig['component'],
            $callServiceConfig['url'],
            '',
            array_merge($callServiceConfig['query'], $page !== 1 ? ['page' => $page] : []),
            $callServiceConfig['headers'],
            false,
            'GET'
        );
        // If no next page with this $page exists... (callservice returns array on error)
        if (is_array($response)) {
            //todo: error, user feedback and log this?
            return [];
        }
        $pageResult = json_decode($response->getBody()->getContents(), true);

        $dot = new Dot($pageResult);
        $results = $dot->get($this->configuration['apiSource']['locationObjects'], $pageResult);

        if (array_key_exists('limit', $this->configuration['apiSource']) && count($results) >= $this->configuration['apiSource']['limit']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($callServiceConfig, $page + 1));
        } elseif (!empty($results) && isset($callServiceConfig['apiSource']['sourcePaginated']) && $callServiceConfig['apiSource']['sourcePaginated']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($callServiceConfig, $page + 1));
        }

        return $results;
    }

    /**
     * Gets a single object from the source.
     *
     * @param Synchronization $sync          The synchronisation object with the related source object id
     *
     * @return array|null The resulting object
     */
    private function getSingleFromSource(Synchronization $sync): ?array
    {
        $component = $this->gatewayService->gatewayToArray($sync->getGateway());
        $url = $this->getUrlForSource($sync->getGateway(), $sync->getSourceId());

        // Get object form source with callservice
        $response = $this->commonGroundService->callService($component, $url, '', [], $sync->getGateway()->getHeaders(), false, 'GET');
        if (is_array($response)) {
            //todo: error, user feedback and log this?
            return null;
        }
        $result = json_decode($response->getBody()->getContents(), true);
        $dot = new Dot($result);
        // The place where we can find the id field when looping through the list of objects, from $result root, by object (dot notation)
//        $id = $dot->get($this->configuration['sourceIdFieldLocation']); // todo, not sure if we need this here or later?

        // The place where we can find an object when we walk through the list of objects, from $result root, by object (dot notation)
        return $dot->get($this->configuration['apiSource']['locationObject'], $result);
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
    private function findSyncBySource(Gateway $source, Entity $entity, string $sourceId): ?Synchronization
    {
        $sync = $this->entityManager->getRepository('App:Synchronization')->findOneBy(['gateway' => $source->getId(), 'entity' => $entity->getId(), 'sourceId' => $sourceId]);

        if ($sync instanceof Synchronization) {
            return $sync;
        }

        $sync = new Synchronization();
        $sync->setGateway($source);
        $sync->setEntity($entity);
        $sync->setSourceId($sourceId);
        $this->entityManager->persist($sync);
        // We flush later

        return $sync;
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
        $sync = $this->entityManager->getRepository('App:Synchronization')->findBy(['object' => $objectEntity, 'gateway' => $source, 'entity' => $entity]);

        if ($sync instanceof Synchronization) {
            return $sync;
        }

        $sync = new Synchronization();
        $sync->setObject($objectEntity);
        $sync->setGateway($source);
        $sync->setEntity($entity);
        $this->entityManager->persist($sync);
        // We flush later

        return $sync;
    }

    /**
     * Adds a new ObjectEntity to an synchronisation object.
     *
     * @param Synchronization $synchronization The synchronisation object without object
     *
     * @return Synchronization The update synchronisation object with object
     */
    private function checkObjectEntity(Synchronization $synchronization): Synchronization
    {
        if (!$synchronization->getObject()) {
            $object = new ObjectEntity();
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
     * @throws \Exception
     *
     * @return Synchronization The updated source object
     */
    private function setLastChangedDate(Synchronization $synchronization, array $sourceObject): Synchronization
    {
        $hash = hash('sha384', serialize($sourceObject));
        $dot = new Dot($sourceObject);
        if (isset($this->configuration['apiSource']['dateChangedField'])) {
            $lastChanged = $dot->get($this->configuration['apiSource']['dateChangedField']);
            $synchronization->setSourcelastChanged(new \DateTime($lastChanged));
        } elseif ($synchronization->getHash() != $hash) {
            $lastChanged = new \DateTime();
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
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return Synchronization The updated synchronisation object
     */
    private function handleSync(Synchronization $synchronization, array $sourceObject = []): Synchronization
    {
        $this->checkObjectEntity($synchronization);

        // If we don't have an sourced object, we need to get one
        $sourceObject = $sourceObject ?: $this->getSingleFromSource($synchronization);
        // @todo vraag aan robert hoe gaat ?: met lege array

        $synchronization = $this->setLastChangedDate($synchronization, $sourceObject);

        //Checks which is newer, the object in the gateway or in the source, and synchronise accordingly
        if (!$synchronization->getLastSynced() || ($synchronization->getLastSynced() < $synchronization->getSourceLastChanged() && $synchronization->getSourceLastChanged() > $synchronization->getObject()->getDateModified())) {
            $object = $this->syncToGateway($synchronization, $sourceObject);
        } elseif ((!$synchronization->getLastSynced() || $synchronization->getLastSynced() < $synchronization->getObject()->getDateModified()) && $synchronization->getSourceLastChanged() < $synchronization->getObject()->getDateModified()) {
            $synchronization = $this->syncToSource($synchronization);
        } else {
            $synchronization = $this->syncThroughComparing($synchronization);
        }

        return $synchronization;
    }

    // todo: docs
    private function syncToSource(Synchronization $sync): Synchronization
    {
        return $sync;
    }

    /**
     * This function populates a pre-existing objectEntity with data that has been validated.
     *
     * @param array        $data         The data that has to go into the objectEntity
     * @param ObjectEntity $objectEntity The ObjectEntity to populate
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return ObjectEntity The populated ObjectEntity
     */
    public function populateObject(array $data, ObjectEntity $objectEntity, ?string $method = 'POST'): ObjectEntity
    {
        $owner = $this->objectEntityService->checkAndUnsetOwner($data);

        if ($validationErrors = $this->validatorService->validateData($data, $objectEntity->getEntity(), 'POST')) {
            //@TODO: Write errors to logs

            foreach ($validationErrors as $error) {
                if (strpos($error, 'must be present') !== false) {
                    return $objectEntity;
                }
            }
        }

        $data = $this->objectEntityService->createOrUpdateCase($data, $objectEntity, $owner, $method, 'application/ld+json');

        return $objectEntity;
    }

    /**
     * Translates the input according to the translation table referenced in the configuration of the action.
     *
     * @param array $externObject  The external object found in the source
     *
     * @return array The translated external object
     */
    private function translateInput(array $externObject): array
    {
        $translationsRepo = $this->entityManager->getRepository('App:Translation');
        $translations = $translationsRepo->getTranslations($this->configuration['apiSource']['translationsIn']);
        if (!empty($translations)) {
            $externObject = $this->translationService->parse($externObject, true, $translations);
        }

        return $externObject;
    }

    /**
     * Sets an application and organization for new ObjectEntities.
     *
     * @param ObjectEntity $objectEntity The ObjectEntity to update
     *
     * @return ObjectEntity The updated ObjectEntity
     */
    private function setApplicationAndOrganization(ObjectEntity $objectEntity): ObjectEntity
    {
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
     * Synchronises data from an external source to the internal database of the gateway.
     *
     * @param Synchronization $sync          The synchronisation object to update
     * @param array           $externObject  The external object to synchronise from
     *
     * @throws GatewayException|CacheException|InvalidArgumentException|ComponentException
     *
     * @return Synchronization The updated synchronisation object containing an updated objectEntity
     */
    private function syncToGateway(Synchronization $sync, array $externObject): Synchronization
    {
        $object = $sync->getObject();

        // todo: see ConvertToGatewayService->convertToGatewayObject() for example code
        // todo: turn all or some of the following todo's and there code into functions?

        // todo: availableProperties, maybe move this to foreach in getAllFromSource() (nice to have)
//        // Filter out unwanted properties before converting extern object to a gateway ObjectEntity
//        $availableBody = array_filter($body, function ($propertyName) use ($entity) {
//            if ($entity->getAvailableProperties()) {
//                return in_array($propertyName, $entity->getAvailableProperties());
//            }
//
//            return $entity->getAttributeByName($propertyName);
//        }, ARRAY_FILTER_USE_KEY);

        if (array_key_exists('mappingIn', $this->configuration['apiSource'])) {
            $externObject = $this->translationService->dotHydrator(isset($this->configuration['apiSource']['skeletonIn']) ? array_merge($this->configuration['apiSource']['skeletonIn'], $externObject) : $externObject, $externObject, $this->configuration['apiSource']['mappingIn']);
        }

        if (array_key_exists('translationsIn', $this->configuration['apiSource'])) {
            $externObject = $this->translateInput($this->configuration, $externObject);
        }

        $externObjectDot = new Dot($externObject);

        if (isset($this->configuration['apiSource']['dateChangedField'])) {
            $object->setDateModified(new DateTime($externObjectDot->get($this->configuration['apiSource']['dateChangedField'])));
        }
        $object = $this->populateObject($externObject, $object);

        return $sync->setObject($object);
    }

    // todo: docs
    private function syncThroughComparing(Synchronization $sync): Synchronization
    {
        return $sync;
    }
}
