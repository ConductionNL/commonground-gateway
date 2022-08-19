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
    public function getAllFromSource(array $data, array $configuration): array
    {
        // todo: i think we need the Action here, because we need to set it with $sync->setAction($action) later...
        // todo: if we do this, some functions that have $sync no longer need $configuration because we can do: $sync->getAction()->getConfiguration()
        $gateway = $this->getSourceFromAction($configuration);
        $entity = $this->getEntityFromAction($configuration);

        // Get json/array results based on the type of source
        $results = $this->getObjectsFromSource($configuration, $gateway);

        foreach ($results as $result) {
            // @todo this could and should be async (nice to have)

            // Turn it in a dot array to find the correct data in $result...
            $dot = new Dot($result);
            // The place where we can find the id field when looping through the list of objects, from $result root, by object (dot notation)
            $id = $dot->get($configuration['apiSource']['idField']);

            // The place where we can find an object when we walk through the list of objects, from $result root, by object (dot notation)
            $result = $dot->get($configuration['apiSource']['locationObjects'], $result);

            // Lets grab the sync object, if we don't find an existing one, this will create a new one:
            $sync = $this->findSyncBySource($gateway, $entity, $id);
            // todo: Another search function for sync object. If no sync object is found, look for matching properties in $result and an ObjectEntity in db. And then create sync for an ObjectEntity if we find one this way. (nice to have)
            // Other option to find a sync object, currently not used:
//            $sync = $this->findSyncByObject($object, $gateway, $entity);

            // Lets sync (returns the Synchronization object)
            $result = $this->handleSync($sync, $configuration, $result);
            $this->entityManager->persist($result);
            $this->entityManager->flush();
        }

        return $results;
    }

    /**
     * Searches and returns the source of the configuration in the database.
     *
     * @param array $configuration The configuration of the action running
     *
     * @return Gateway|null The found source for the configuration
     */
    private function getSourceFromAction(array $configuration): ?Gateway
    {
        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['id' => $configuration['source']]);

        if ($source instanceof Gateway) {
            return $source;
        }

        return null;
    }

    /**
     * Searches and returns the entity of the configuration in the database.
     *
     * @param array $configuration The configuration of the action running
     *
     * @return Entity|null The found entity for the configuration
     */
    private function getEntityFromAction(array $configuration): ?Entity
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $configuration['eavObject']]);

        if ($entity instanceof Entity) {
            return $entity;
        }

        return null;
    }

    /**
     * Gets the configuration for the source and fetches the results on the source.
     *
     * @param array   $configuration The configuration for the action running
     * @param Gateway $gateway       The source to get the data from
     *
     * @return array The results found on the source
     */
    private function getObjectsFromSource(array $configuration, Gateway $gateway): array
    {
        $callServiceConfig = $this->getCallServiceConfig($configuration, $gateway);
        // Right now there are two options, either api source is paginated or it is not
        $results = $this->fetchObjectsFromSource($configuration, $callServiceConfig);

        return $results;
    }

    /**
     * Determines the configuration for the source given.
     *
     * @param array   $configuration Configuration of the action running
     * @param Gateway $gateway       The source to call
     *
     * @return array The configuration for the source to call
     */
    private function getCallServiceConfig(array $configuration, Gateway $gateway): array
    {
        return [
            'component' => $this->gatewayService->gatewayToArray($gateway),
            'url'       => $this->getUrlForSource($gateway, $configuration),
            'query'     => array_key_exists('sourceLimit', $configuration) ? ['limit' => $configuration['sourceLimit']] : [],
            'headers'   => $gateway->getHeaders(),
        ];
    }

    /**
     * Determines the URL to request.
     *
     * @param Gateway     $gateway       The source to call
     * @param array       $configuration The configuration of the action running
     * @param string|null $id            The id to request (optional)
     *
     * @return string The resulting URL
     */
    private function getUrlForSource(Gateway $gateway, array $configuration, string $id = null): string
    {
        return $gateway->getLocation().$configuration['location'].($id ? '/'.$id : '');
    }

    /**
     * Fetches the objects stored on the source.
     *
     * @param array $configuration     The configuration of the action running
     * @param array $callServiceConfig The configuration for the source
     * @param int   $page              The current page to be requested
     *
     * @return array
     */
    private function fetchObjectsFromSource(array $configuration, array $callServiceConfig, int $page = 1): array
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
        $results = $dot->get($configuration['apiSource']['locationObjects'], $pageResult);

        if (array_key_exists('limit', $configuration['apiSource']) && count($results) >= $configuration['apiSource']['limit']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($configuration, $callServiceConfig, $page + 1));
        } elseif (!empty($results) && isset($callServiceConfig['apiSource']['sourcePaginated']) && $callServiceConfig['apiSource']['sourcePaginated']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($configuration, $callServiceConfig, $page + 1));
        }

        return $results;
    }

    /**
     * Gets a single object from the source.
     *
     * @param Synchronization $sync          The synchronisation object with the related source object id
     * @param array           $configuration The configuration of the action running
     *
     * @return array|null The resulting object
     */
    private function getSingleFromSource(Synchronization $sync, array $configuration): ?array
    {
        $component = $this->gatewayService->gatewayToArray($sync->getGateway());
        $url = $this->getUrlForSource($sync->getGateway(), ['location' => $configuration['location']], $sync->getSourceId());

        // Get object form source with callservice
        $response = $this->commonGroundService->callService($component, $url, '', [], $sync->getGateway()->getHeaders(), false, 'GET');
        if (is_array($response)) {
            //todo: error, user feedback and log this?
            return null;
        }
        $result = json_decode($response->getBody()->getContents(), true);
        $dot = new Dot($result);
        // The place where we can find the id field when looping through the list of objects, from $result root, by object (dot notation)
//        $id = $dot->get($configuration['sourceIdFieldLocation']); // todo, not sure if we need this here or later?

        // The place where we can find an object when we walk through the list of objects, from $result root, by object (dot notation)
        return $dot->get($configuration['apiSource']['locationObject'], $result);
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
     * @param array           $configuration   The configuration from the action
     * @param array           $sourceObject    The object returned from the source
     *
     * @throws \Exception
     *
     * @return Synchronization The updated source object
     */
    private function setLastChangedDate(Synchronization $synchronization, array $configuration, array $sourceObject): Synchronization
    {
        $hash = hash('sha384', serialize($sourceObject));
        $dot = new Dot($sourceObject);
        if (isset($configuration['apiSource']['dateChangedField'])) {
            $lastChanged = $dot->get($configuration['apiSource']['dateChangedField']);
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
     * @param array           $configuration   The configuration from the action
     * @param array           $sourceObject    The object in the source
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return Synchronization The updated synchronisation object
     */
    private function handleSync(Synchronization $synchronization, array $configuration, array $sourceObject = []): Synchronization
    {
        $this->checkObjectEntity($synchronization);
        $sourceObject = $sourceObject ?: $this->getSingleFromSource($synchronization, $configuration);
        $synchronization = $this->setLastChangedDate($synchronization, $configuration, $sourceObject);

        //Checks which is newer, the object in the gateway or in the source, and synchronise accordingly
        if (!$synchronization->getLastSynced() || ($synchronization->getLastSynced() < $synchronization->getSourceLastChanged() && $synchronization->getSourceLastChanged() > $synchronization->getObject()->getDateModified())) {
            $object = $this->syncToGateway($synchronization, $sourceObject, $configuration);
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
     * @param array $configuration The configuration of the action, refers to the translationTable to use
     * @param array $externObject  The external object found in the source
     *
     * @return array The translated external object
     */
    private function translateInput($configuration, $externObject): array
    {
        $translationsRepo = $this->entityManager->getRepository('App:Translation');
        $translations = $translationsRepo->getTranslations($configuration['apiSource']['translationsIn']);
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
     * @param array           $configuration The configuration of the action to handle
     *
     * @throws GatewayException|CacheException|InvalidArgumentException|ComponentException
     *
     * @return Synchronization The updated synchronisation object containing an updated objectEntity
     */
    private function syncToGateway(Synchronization $sync, array $externObject, array $configuration): Synchronization
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

        if (array_key_exists('mappingIn', $configuration['apiSource'])) {
            $externObject = $this->translationService->dotHydrator(isset($configuration['apiSource']['skeletonIn']) ? array_merge($configuration['apiSource']['skeletonIn'], $externObject) : $externObject, $externObject, $configuration['apiSource']['mappingIn']);
        }

        if (array_key_exists('translationsIn', $configuration['apiSource'])) {
            $externObject = $this->translateInput($configuration, $externObject);
        }

        $externObjectDot = new Dot($externObject);

        if (isset($configuration['apiSource']['dateChangedField'])) {
            $object->setDateModified(new DateTime($externObjectDot->get($configuration['apiSource']['dateChangedField'])));
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
