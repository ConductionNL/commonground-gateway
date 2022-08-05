<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Message\SyncPageMessage;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class SynchronisationService
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
    public function getAllFromSource(Gateway $gateway, Entity $entity, string $location, array $configuration): array
    {
        $component = $this->gatewayService->gatewayToArray($gateway);
        $url = $this->getUrlForSource($gateway, $location);

        // todo: get amount of pages
        $amountOfPages = $this->getAmountOfPages($component, $url);

        // todo: asyn messages? for each page

        return [];
    }

    // todo: Een functie die één enkel object uit de source trekt
    public function getSinleFromSource(Sync $sync): array
    {
        $component = $this->gatewayService->gatewayToArray($gateway);
        $url = $this->getUrlForSource($gateway, $location);

        // todo: get amount of pages
        $amountOfPages = $this->getAmountOfPages($component, $url);

        // todo: asyn messages? for each page

        return [];
    }

    private function getUrlForSource(Gateway $gateway, string $location): string
    {
        // todo: generate url with correct query params etc.

        return '';
    }

    private function getAmountOfPages(array $component, string $url): int
    {
        // todo: use callService with url
        $response = $this->commonGroundService->callService($component, $url, '', [], [], false, 'GET');

        return 1;
    }

    // todo: Een functie die aan de hand van een synchronisatie object een sync uitvoert, om dubbele bevragingen
    // todo: van externe bronnen te voorkomen zou deze ook als propertie het externe object al array moeten kunnen accepteren.
    // RL: ik zou verwachten dat handle syn een sync ontvange en terug geeft
    public function handleSync(Sync $sync, array $sourceObject): Sync
    {
        // We need an object on the gateway side
        if(!$sync->getObject()){
            $object = new ObjectEntity();
            $object->setEntity($sync->getEnity);
        }

        // We need an object source side
        if(!$sourceObject || empty($sourceObject)){
            $sourceObject = $this->getSingleFromSource($sync);
        }

        // Now that we have a source object we can create a hash of it
        $hash = hash('sha384', $sourceObject);

        // Now we need to establish the last time the source was changed
        if(in_array('modifiedDateLocation',$sync->getAction()->getConfig())){
            // todo: get the laste chage date from object array
            $lastchagne = '';
            $sourceObject->setSourcelastChanged($lastchagne);
        }
        // What if the source has no propertity that alows us to determine the last change
        elseif($sync->getHash() != $hash){
            $lastchagne = new \DateTime();
            $sourceObject->setSourcelastChanged($lastchagne);
        }

        // Now that we know the lastchange date we can update the hash
        $sourceObject->setHash($hash);

        // This gives us three options
        if($sync->getSourcelastChanged() > $sync->getObject->getDateModiefied() && $sync->getSourcelastChanged() > $sync->getLastSynced() && $sync->getObject()->getDateModiefied() < $sync->getsyncDatum()){
            // The source is newer
            $sync = $this->syncToSource($sync);
        }
        elseif($sync->getSourcelastChanged() < $sync->getObject()->getDateModiefied() && $sync->getObject()->getDateModiefied() > $sync->getLastSynced() && $sync->getSourcelastChanged() < $sync->syncDatum()){
            // The gateway is newer
            $sync = $this->syncToGate($sync);

        }
        else{
            // we are in trouble, both the gateway object AND soure object have cahnged afther the last sync
            $sync = $this->syncTroughComparing($sync);
        }

        return $sync;
        // RLI: Onderstaande kan volgens mij weg


        // todo: if $sourceObject array is given continue, else get it from the source.
        // todo: check if hash of $sourceObject matches the already existing hash
        // todo: if so, update syncDatum and return
        // todo: else: sync (check if ObjectEntity exists, if not create one, else update it)

        //$entity = new Entity(); // todo $sync->getEntity() ?
        //return $this->saveAsGatewayObject($entity, $sourceObject);
    }

    //RLI: Ik zou dre seperate functies verwachten source->gateway,gateway->source,bidirectional
    private function saveAsGatewayObject(Entity $entity, array $externObject): ObjectEntity
    {
        // todo: mapping and translation
        // todo: validate object
        // todo: save object
        // todo: log?

        return new ObjectEntity();
    }
}
