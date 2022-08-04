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
    public function getFromSource(Gateway $gateway): array
    {
        $component = $this->gatewayService->gatewayToArray($gateway);
        $url = $this->getUrlForSource($gateway);

        // todo: use callService with url
        $response = $this->commonGroundService->callService($component, $url, '', [], [], false, 'GET');

        // todo: asyn messages? for each page

        return [];
    }

    private function getUrlForSource(Gateway $gateway): string
    {
        // todo: generate url with correct query params etc.

        return '';
    }

    // todo: Een functie die kijkt of  er al een synchronistie object is aan de hand van de source
    // todo: (dus zoekt op source + endpoint + externeid) onderdeel van de EntityRepository
    public function findSyncBySource(Gateway $gateway)//: ?Sync $sync
    {

        return null;
    }

    // todo: Een functie die kijkt of er al een synchronisatie object is aan de hand van een objectEntity
    // todo: (dus zoekt op object + source + endooint) onderdeel van de EntityRepository
    public function findSyncByObject(ObjectEntity $objectEntity, Gateway $gateway)//: ?Sync $sync
    {

        return null;
    }

    // todo: Een functie die aan de hand van een synchronisatie object een sync uitvoert, om dubbele bevragingen
    // todo: van externe bronnen te voorkomen zou deze ook als propertie het externe object al array moeten kunnen accepteren.
    public function handleSync(Sync $sync, array $externObject): ObjectEntity
    {

        $entity = new Entity(); // todo $sync->getEntity() ?
        return $this->saveAsGatewayObject($entity, $externObject);
    }


    private function saveAsGatewayObject(Entity $entity, array $externObject): ObjectEntity
    {
        // todo: mapping and translation
        // todo: validate object
        // todo: save object
        // todo: log?

        return new ObjectEntity();
    }
}
