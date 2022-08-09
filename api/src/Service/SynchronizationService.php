<?php

namespace App\Service;

use Adbar\Dot;
use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, SessionInterface $session, GatewayService $gatewayService, FunctionService $functionService, LogService $logService, MessageBusInterface $messageBus, TranslationService $translationService)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->gatewayService = $gatewayService;
        $this->functionService = $functionService;
        $this->logService = $logService;
        $this->messageBus = $messageBus;
        $this->translationService = $translationService;
    }

    // todo: Een functie dan op een source + endpoint alle objecten ophaalt (dit dus  waar ook de configuratie
    // todo: rondom pagination, en locatie van de results vandaan komt).
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
            $id = $dot->get($configuration['sourceIdFieldLocation']);
            // The place where we can find an object when we walk through the list of objects, from $result root, by object (dot notation)
            $result = $dot->get($configuration['sourceObjectLocation'], $result);

            // Lets grab the sync object, if we don't find an existing one, this will create a new one:
            $sync = $this->findSyncBySource($gateway, $entity, $id);
            // todo: Another search function for sync object. If no sync object is found, look for matching properties in $result and an ObjectEntity in db. And then create sync for an ObjectEntity if we find one this way. (nice to have)
            // Other option to find a sync object, currently not used:
//            $sync = $this->findSyncByObject($object, $gateway, $entity);

            // Lets sync (returns the Synchronization object)
            $result = $this->handleSync($sync, $result, $configuration);
            $this->entityManager->persist($result);
            $this->entityManager->flush();
        }

        return $results;
    }

    // todo: docs
    private function getSourceFromAction(array $configuration): ?Gateway
    {
        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['id' => $configuration['source']]);

        if ($source instanceof Gateway) {
            return $source;
        }
        return null;
    }

    // todo: docs
    private function getEntityFromAction(array $configuration): ?Entity
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $configuration['eavObject']]);

        if ($entity instanceof Entity) {
            return $entity;
        }
        return null;
    }

    // todo: docs
    private function getObjectsFromSource(array $configuration, Gateway $gateway): array
    {
        $callServiceConfig = $this->getCallServiceConfig($configuration, $gateway);

        // Right now there are two options, either api source is paginated or it is not
        if (in_array('sourcePaginated', $configuration) && $configuration['sourcePaginated']){
            $results = $this->getObjectsFromPagedSource($configuration, $callServiceConfig);
        } else {
            $results = $this->getObjectsFromApiSource($configuration, $callServiceConfig);
        }

        return $results;
    }

    private function getCallServiceConfig(array $configuration, Gateway $gateway): array
    {
        return [
            'component' => $this->gatewayService->gatewayToArray($gateway),
            'url' => $this->getUrlForSource($gateway, $configuration),
            // todo: maybe use sourceLimitQuery instead of sourceLimit for this in $configuration?
            'query' => array_key_exists('sourceLimit', $configuration) ? ['limit' => $configuration['sourceLimit']] : [],
            'headers' => $gateway->getHeaders()
        ];
    }

    // todo: docs
    private function getUrlForSource(Gateway $gateway, array $configuration, string $id = null): string
    {
        return $gateway->getLocation().'/'.$configuration['sourceLocation'].$id ? '/'.$id : '';
    }

    // todo: docs
    private function getObjectsFromPagedSource(array $configuration, array $callServiceConfig, int $page = 1): array
    {
        // Get a single page
        $response = $this->commonGroundService->callService($callServiceConfig['component'], $callServiceConfig['url'],
            '', array_merge($callServiceConfig['query'], $page !== 1 ? ['page' => $page] : []),
            $callServiceConfig['headers'], false, 'GET');
        // If no next page with this $page exists... (callservice returns array on error)
        if (is_array($response)) {
            //todo: error, user feedback and log this?
            return [];
        }
        $pageResult = json_decode($response->getBody()->getContents(), true);

        $dot = new Dot($pageResult);
        // The place where we can find the list of objects from the root after a get collection on a source (dot notation)
        $results = $dot->get($configuration['sourceObjectsLocation'], $pageResult);

        // Let see if we need to pull another page (e.g. this page is full so there might be a next one)
        // sourceLimit = The limit per page for the collection call on the source.
        if (array_key_exists('sourceLimit', $configuration)) {
            // If limit is set
            if (count($results) >= $configuration['sourceLimit']) {
                $results = array_merge($results, $this->getObjectsFromPagedSource($configuration, $callServiceConfig, $page + 1));
            }
        } elseif (!empty($results)) {
            // If no limit is set, just go to the next page, unless we have no results.
            $results = array_merge($results, $this->getObjectsFromPagedSource($configuration, $callServiceConfig, $page + 1));
        }

        return $results;
    }

    // todo: docs
    private function getObjectsFromApiSource(array $configuration, array $callServiceConfig): array
    {
        // todo...

        return [];
    }

    // todo: Een functie die één enkel object uit de source trekt
    private function getSingleFromSource(Synchronization $sync, array $configuration): ?array
    {
        $component = $this->gatewayService->gatewayToArray($sync->getGateway());
        $url = $this->getUrlForSource($sync->getGateway(), ['sourceLocation' => $configuration['sourceLocation']], $sync->getSourceId());

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
        return $dot->get($configuration['sourceObjectLocation'], $result);
    }

    // todo: Een functie die kijkt of  er al een synchronistie object is aan de hand van de source
    // todo: (dus zoekt op source + endpoint + externeid)
    private function findSyncBySource(Gateway $source, Entity $entity, string $sourceId): ?Synchronization
    {
        $sync = $this->entityManager->getRepository('App:Synchronization')->findBy(['gateway' => $source, 'entity' => $entity, 'sourceId' => $sourceId]);

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

    // todo: Een functie die kijkt of er al een synchronisatie object is aan de hand van een objectEntity
    // todo: (dus zoekt op object + source + endooint)
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

    // todo: Een functie die aan de hand van een synchronisatie object een sync uitvoert, om dubbele bevragingen
    // todo: van externe bronnen te voorkomen zou deze ook als propertie het externe object al array moeten kunnen accepteren.
    private function handleSync(Synchronization $sync, ?array $sourceObject, array $configuration): Synchronization
    {
        // We need an object on the gateway side
        if (!$sync->getObject()){
            $object = new ObjectEntity();
            $object->setEntity($sync->getEntity());
            $sync->setObject($object);

            // todo: set organization and application for $object, see eavService->getObject() function.
        }

        // We need an object source side
        if (empty($sourceObject)){
            $sourceObject = $this->getSingleFromSource($sync, $configuration);
        }

        // Now that we have a source object we can create a hash of it
        $hash = hash('sha384', serialize($sourceObject));
        // Lets turn the source into a dot so that we can grab values
        $dot = new Dot($sourceObject);

        // Now we need to establish the last time the source was changed
        if (array_key_exists('modifiedDateLocation', $configuration)) {
            $lastChanged = $dot->get($configuration['modifiedDateLocation']);
            $sync->setSourcelastChanged($lastChanged);
        }
        // What if the source has no property that allows us to determine the last change
        elseif ($sync->getHash() != $hash){
            $lastChanged = new \DateTime();
            $sync->setSourcelastChanged($lastChanged);
        }

        // Now that we know the lastChange date we can update the hash
        $sync->setHash($hash);

        // This gives us three options
        //todo: check and test if statements are correct... might need change?
        if ($sync->getSourcelastChanged() > $sync->getObject()->getDateModified() && $sync->getSourcelastChanged() > $sync->getLastSynced() && $sync->getObject()->getDatemodified() < $sync->getsyncDatum()){
            // The source is newer
            $sync = $this->syncToSource($sync);
        }
        elseif ($sync->getSourcelastChanged() < $sync->getObject()->getDatemodified() && $sync->getObject()->getDatemodified() > $sync->getLastSynced() && $sync->getSourcelastChanged() < $sync->syncDatum()){
            // The gateway is newer
            // Save object
            $object = $this->syncToGateway($sync, $sourceObject, $configuration);
        } else {
            // we are in trouble, both the gateway object AND soure object have cahnged afther the last sync
            $sync = $this->syncThroughComparing($sync);
        }

        return $sync;
    }

    // todo: docs
    private function syncToSource(Synchronization $sync): Synchronization
    {
        return $sync;
    }

    // todo: docs
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

        // todo: mapping, mappingIn, sourceMappingIn or externMappingIn?
        if (array_key_exists('mappingIn', $configuration)) {
            $externObject = $this->translationService->dotHydrator($externObject, $externObject, $configuration['mappingIn']);
        }
        // todo: translation
        if (array_key_exists('translationsIn', $configuration)) {
            $translationsRepo = $this->entityManager->getRepository('App:Translation');
            $translations = $translationsRepo->getTranslations($configuration['translationsIn']);
            if (!empty($translations)) {
                $externObject = $this->translationService->parse($externObject, true, $translations);
            }
        }

        // todo: if dateCreated/modified in source, set it on ObjectEntity (nice to have)
        // If extern object has dateCreated & dateModified, set them for this new ObjectEntity
        key_exists('dateCreated', $externObject) && $object->setDateCreated(new DateTime($externObject['dateCreated']));
        key_exists('date_created', $externObject) && $object->setDateCreated(new DateTime($externObject['date_created']));
        key_exists('dateModified', $externObject) && $object->setDateModified(new DateTime($externObject['dateModified']));
        key_exists('date_modified', $externObject) && $object->setDateModified(new DateTime($externObject['date_modified']));

        // todo: for validation and saving an object, see example code $objectEntityService->handleObject() switch, case: POST
        // todo: validate object with $validaterService->validateData()
        // todo: save object with $objectEntityService->saveObject()
        // todo: ^... handle function
        // todo: ^... handle owner
        // todo: what to do with validationErrors?
        // todo: log? (nice to have)

        return $sync->setObject($object);
    }

    // todo: docs
    private function syncThroughComparing(Synchronization $sync): Synchronization
    {
        return $sync;
    }
}
