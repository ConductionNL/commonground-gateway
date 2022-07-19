<?php

namespace App\Subscriber;

use App\Entity\Action;
use App\Entity\Endpoint;
use App\Event\EndpointTriggeredEvent;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class EndpointSubscriber implements EventSubscriberInterface
{

    private Client $client;
    private EntityManagerInterface $entityManager;
    private Request $request;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            EndpointTriggeredEvent::NAME => 'handleEvent',
        ];
    }

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->client = new Client([
            'http_errors'   =>  false,
            'timeout'       =>  4000.0,
            'verify'        =>  false,
        ]);
    }

    public function getAction(Endpoint $endpoint): ?Action
    {
        $actions = $this->entityManager->getRepository("App:Action")->findBy(['endpointId' => $endpoint->getId()]);
        foreach($actions as $action)
        {
            if(
                $action instanceof Action &&
                $action->getType() == 'Life-Event-Notification'
            ){
                return $action;
            }
        }
        return null;
    }

    public function getEventType(Endpoint $endpoint): string
    {
        $action = $this->getAction($endpoint);
        $municipality = $action->getConfiguration()['metadata']['municipality'];

        $content = $this->request->getContent();

        $event = 'persoon-overleden';
        //@TODO: add conditions to decide event type
        return "nl.$municipality.brp.$event";
    }

    public function getSource(Endpoint $endpoint): string
    {
        $action = $this->getAction($endpoint);

        $oin = $action->getConfiguration()['metadata']['oin'];
        $system = $action->getConfiguration()['metadata']['system'];
        return "urn:nld:oin:$oin:systeem:$system";
    }

    public function getSubject(Endpoint $endpoint): string
    {
        //@TODO: This has to be found in the message that we listen to

        return '999992806';
    }

    public function getDataRef(Endpoint $endpoint): string
    {
        $action = $this->getAction($endpoint);
        $basePath = $action->getConfiguration()['metadata']['haalCentraal-base-path'];
        return "$basePath/{$this->getSubject($endpoint)}";
    }

    public function getEvent(Endpoint $endpoint): string
    {
        $dateTime = new \DateTime();
        $result = [
            'specversion'   =>  '1.0',
            'type'          =>  $this->getEventType($endpoint),
            'source'        =>  $this->getSource($endpoint),
            'subject'       =>  $this->getSubject($endpoint),
            'id'            =>  Uuid::uuid4(),
            'time'          =>  $dateTime->format('Y-m-d\TH:i:s\Z'),
            'dataref'       =>  $this->getDataRef($endpoint),
        ];

        return json_encode($result);
    }

    public function sendEvent(string $event): Response
    {
        // @TODO use the commonground service here with a gateway resource
        $endpoint   =   "";
        $apikey     =   "";

        return $this->client->post($endpoint, [
            'body'      => $event,
            'headers'   =>
                [
                    'X-Api-Key' => $apikey,
                ],
        ]);
    }

    public function handleEvent(EndpointTriggeredEvent $event): EndpointTriggeredEvent
    {
//        if(
//            $event->getRequest()->getMethod() == 'GET'
//            // @TODO: add extra conditions
//        ) {
//            return $event;
//        }
//        $this->request = $event->getRequest();
//        $eventContent = $this->getEvent($event->getEndpoint());
//        $result = $this->sendEvent($eventContent);


        return $event;
    }
}
