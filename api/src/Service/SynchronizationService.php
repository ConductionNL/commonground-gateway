<?php

namespace App\Service;

use Adbar\Dot;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
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
    // RLI: beetje veel wat we hier mee geven,  volgensmij heb je alleen de action nodig
    public function getAllFromSource(array $data, array $configuration): array
    {
        $gateway = $this->getSourceFromAction($configuration);
        $entity = $this->getEntityFromAction($configuration);

        // Get results based on the type of source
        $results = $this->getObjectsFromSource($configuration, $gateway);

        foreach ($results as $result) {
            $dot = new Dot($result);
            $id = $dot->get($configuration['sourceIdFieldLocation']);
            $result = $dot->get($configuration['sourceObjectLocation'], $result);

            // @todo this could and should be async
            // Lets grab the sync object
            $sync = $this->findSyncBySource($gateway, $entity, $id);
//            $sync = $this->findSyncByObject($object, $gateway, $entity);
            // todo, what if we don't find a $sync object? should we create one inside the findSyncBySource() function?
            // Lets sync
            $result = $this->handleSync($sync, $result);
            $this->entityManager->persist($result);
            // todo flush here? or after foreach?
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

        // todo switch?
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
            'query' => [],
            'headers' => $gateway->getHeaders()
        ];
    }

    // todo: docs
    private function getUrlForSource(Gateway $gateway, array $configuration, string $id = null): string
    {
        return $gateway->getLocation().'/'.$configuration['sourceLocation'].$id ? '/'.$id : '';
    }

    // Door pages heen lopen zonder total result
    // todo: docs
    private function getObjectsFromPagedSource(array $configuration, array $callServiceConfig, int $page = 1): array
    {
        // RLI  what if a source doesn't have a limit
        // WL   check for pagination?
        $limit = $configuration['sourceLimit'];

        // Get a single page
        $response = $this->commonGroundService->callService($callServiceConfig['component'], $callServiceConfig['url'],
            '', array_merge($callServiceConfig['query'], ['limit' => $limit], $page !== 1 ? ['page' => $page] : []),
            $callServiceConfig['headers'], false, 'GET');
        // If no next page with this $page exists... (callservice returns array on error)
        if (is_array($response)) {
            //todo: error, user feedback and log this?
            return [];
        }
        $pageResult = json_decode($response->getBody()->getContents(), true);

        $dot = new Dot($pageResult);
        $results = $dot->get($configuration['sourceObjectsLocation'], []);
        // Let see if we need to pull another page (e.g. this page is full so there might be a next one)
        if (count($results) >= $limit) {
            $page ++;
            $results = array_merge($results, $this->getObjectsFromPagedSource($configuration, $callServiceConfig, $page));
        }

        return $results;
    }

    // todo: docs
    private function getObjectsFromApiSource(array $configuration, array $callServiceConfig): array
    {
        return [];
    }

    // todo: Een functie die één enkel object uit de source trekt
    private function getSingleFromSource(Synchronization $sync): ?array
    {
        $component = $this->gatewayService->gatewayToArray($sync->getGateway());
        $url = $this->getUrlForSource($sync->getGateway(), ['sourceLocation' => $sync->getAction()->getConfiguration()['sourceLocation']], $sync->getSourceId());

        // Get object form source with callservice
        $response = $this->commonGroundService->callService($component, $url, '', [], $sync->getGateway()->getHeaders(), false, 'GET');
        if (is_array($response)) {
            //todo: error, user feedback and log this?
            return null;
        }
        $result = json_decode($response->getBody()->getContents(), true);
        $dot = new Dot($result);
//        $id = $dot->get($sync->getAction()->getConfiguration()['sourceIdFieldLocation']); // todo, not sure if we need this here or later?

        return $dot->get($sync->getAction()->getConfiguration()['sourceObjectLocation'], $result);
    }

    // todo: Een functie die kijkt of  er al een synchronistie object is aan de hand van de source
    // todo: (dus zoekt op source + endpoint + externeid)
    private function findSyncBySource(Gateway $source, Entity $entity, string $sourceId): ?Synchronization
    {
        $sync = $this->entityManager->getRepository('App:Synchronization')->findBy(['gateway' => $source, 'entity' => $entity, 'sourceId' => $sourceId]);

        if ($sync instanceof Synchronization) {
            return $sync;
        }
        return null;
    }

    // todo: Een functie die kijkt of er al een synchronisatie object is aan de hand van een objectEntity
    // todo: (dus zoekt op object + source + endooint)
    private function findSyncByObject(ObjectEntity $objectEntity, Gateway $source, Entity $entity): ?Synchronization
    {
        $sync = $this->entityManager->getRepository('App:Synchronization')->findBy(['object' => $objectEntity, 'gateway' => $source, 'entity' => $entity]);

        if ($sync instanceof Synchronization) {
            return $sync;
        }
        return null;
    }

    // todo: Een functie die aan de hand van een synchronisatie object een sync uitvoert, om dubbele bevragingen
    // todo: van externe bronnen te voorkomen zou deze ook als propertie het externe object al array moeten kunnen accepteren.
    // RL: ik zou verwachten dat handle syn een sync ontvange en terug geeft
    private function handleSync(Synchronization $sync, ?array $sourceObject): Synchronization
    {
        // We need an object on the gateway side
        if (!$sync->getObject()){
            $object = new ObjectEntity();
            $object->setEntity($sync->getEntity());
        }

        // We need an object source side
        if (empty($sourceObject)){
            $sourceObject = $this->getSingleFromSource($sync);
        }

        // Now that we have a source object we can create a hash of it
        $hash = hash('sha384', $sourceObject); // todo, this needs to be string somehow? implode?
        // Lets turn the source into a dot so that we can grap values
        $dot = new Dot($sourceObject);

        // Now we need to establish the last time the source was changed
        if (in_array('modifiedDateLocation', $sync->getAction()->getConfiguration())){
            // todo: get the laste chage date from object array
            $lastchagne = '';
            $sync->setSourcelastChanged($lastchagne);
        }
        // What if the source has no propertity that alows us to determine the last change
        elseif ($sync->getHash() != $hash){
            $lastchagne = new \DateTime();
            $sync->setSourcelastChanged($lastchagne);
        }

        // Now that we know the lastchange date we can update the hash
        $sync->setHash($hash);

        // This gives us three options
        if ($sync->getSourcelastChanged() > $sync->getObject->getDateModified() && $sync->getSourcelastChanged() > $sync->getLastSynced() && $sync->getObject()->getDatemodified() < $sync->getsyncDatum()){
            // The source is newer
            $sync = $this->syncToSource($sync);
        }
        elseif ($sync->getSourcelastChanged() < $sync->getObject()->getDatemodified() && $sync->getObject()->getDatemodified() > $sync->getLastSynced() && $sync->getSourcelastChanged() < $sync->syncDatum()){
            // The gateway is newer
//            $sync = $this->syncToGateway($sync);

            // Save object
            //$entity = new Entity(); // todo $sync->getEntity() ?
            $object = $this->syncToGateway($entity, $sourceObject);
        }
        else {
            // we are in trouble, both the gateway object AND soure object have cahnged afther the last sync
            $sync = $this->syncTroughComparing($sync);
        }

        return $sync;
    }

    // todo: docs
    private function syncToSource(Synchronization $sync): Synchronization
    {
        return $sync;
    }

    // todo: docs
    private function syncToGateway(Entity $entity, array $externObject): ObjectEntity
    {
        // todo: mapping and translation
        // todo: validate object
        // todo: save object
        // todo: log?

        return new ObjectEntity();
    }

    // todo: docs
    private function syncThroughComparing(Synchronization $sync): Synchronization
    {
        return $sync;
    }
}
