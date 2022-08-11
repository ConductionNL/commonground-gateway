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
    private ObjectEntityService $objectEntityService;
    private ValidatorService $validatorService;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, SessionInterface $session, GatewayService $gatewayService, FunctionService $functionService, LogService $logService, MessageBusInterface $messageBus, TranslationService $translationService, ObjectEntityService $objectEntityService, ValidatorService $validatorService)
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
        $this->validatorService = $validatorService;
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
            $id = $dot->get($configuration['apiSource']['idField']);
            // The place where we can find an object when we walk through the list of objects, from $result root, by object (dot notation)
            $result = $dot->get($configuration['apiSource']['locationObjects'], $result);

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
//        var_dump($callServiceConfig);

        // Right now there are two options, either api source is paginated or it is not
        $results = $this->fetchObjectsFromSource($configuration, $callServiceConfig);

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
        return $gateway->getLocation().'/'.$configuration['sourceLocation'].($id ? '/'.$id : '');
    }

    // todo: docs
    private function fetchObjectsFromSource(array $configuration, array $callServiceConfig, int $page = 1): array
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
        $results = $dot->get($configuration['apiSource']['locationObjects'], $pageResult);

        if (array_key_exists('limit', $configuration['apiSource']) && count($results) >= $configuration['apiSource']['limit']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($configuration, $callServiceConfig, $page + 1));
        } elseif (!empty($results) && isset($callServiceConfig['apiSource']['sourcePaginated']) && $callServiceConfig['apiSource']['sourcePaginated']) {
            $results = array_merge($results, $this->fetchObjectsFromSource($configuration, $callServiceConfig, $page + 1));
        }

        return $results;
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
            $this->entityManager->persist($object);
            // todo: set organization and application for $object, see eavService->getObject() function.
        }

        // We need an object source side
        if (empty($sourceObject)){
            $sourceObject = $this->getSingleFromSource($sync, $configuration);
        }
        $hash = hash('sha384', serialize($sourceObject));
        $dot = new Dot($sourceObject);

        if (isset($configuration['apiSource']['dateChangedField'])) {
            $lastChanged = $dot->get($configuration['apiSource']['dateChangedField']);
            $sync->setSourcelastChanged(new \DateTime($lastChanged));
        }

        elseif ($sync->getHash() != $hash) {
            $lastChanged = new \DateTime();
            $sync->setSourcelastChanged($lastChanged);
        }

        $sync->setHash($hash);

        if (!$sync->getLastSynced() || ($sync->getLastSynced() < $sync->getSourceLastChanged() && $sync->getSourceLastChanged() > $sync->getObject()->getDateModified())){
            $object = $this->syncToGateway($sync, $sourceObject, $configuration);
        }
        elseif ((!$sync->getLastSynced() || $sync->getLastSynced() < $sync->getObject()->getDateModified()) && $sync->getSourceLastChanged() < $sync->getObject()->getDateModified()){
            $sync = $this->syncToSource($sync);
        } else {
            $sync = $this->syncThroughComparing($sync);
        }

        die;

        return $sync;
    }

    // todo: docs
    private function syncToSource(Synchronization $sync): Synchronization
    {

        return $sync;
    }

    private function populateObject(array $data, ObjectEntity $objectEntity): ObjectEntity
    {
        $owner = $this->objectEntityService->checkAndUnsetOwner($data);


        // validate
        if ($validationErrors = $this->validatorService->validateData($data, $objectEntity->getEntity(), 'POST')) {
            var_dump($validationErrors);
            die;
        }

        $data = $this->objectEntityService->createOrUpdateCase($data, $objectEntity, $owner, 'POST', 'application/ld+json');

        var_dump($this->objectEntityService->renderPostBody($objectEntity));
        return $objectEntity;
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

        if (array_key_exists('mappingIn', $configuration['apiSource'])) {
            var_dump($configuration['apiSource']['mappingIn']);
            $externObject = $this->translationService->dotHydrator($externObject, $externObject, $configuration['apiSource']['mappingIn']);
        }

        if (array_key_exists('translationsIn', $configuration['apiSource'])) {
            $translationsRepo = $this->entityManager->getRepository('App:Translation');
            $translations = $translationsRepo->getTranslations($configuration['apiSource']['translationsIn']);
            if (!empty($translations)) {
                $externObject = $this->translationService->parse($externObject, true, $translations);
            }
        }

        $externObjectDot = new Dot($externObject);

        if(isset($configuration['apiSource']['dateChangedField'])){
            $object->setDateModified(new DateTime($externObjectDot->get($configuration['apiSource']['dateChangedField'])));
        }
        $this->populateObject($externObject, $object);
        die;
        return $sync->setObject($object);
    }

    // todo: docs
    private function syncThroughComparing(Synchronization $sync): Synchronization
    {
        return $sync;
    }
}
