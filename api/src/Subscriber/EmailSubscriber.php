<?php

namespace App\Subscriber;

use App\Entity\Endpoint;
use App\Event\EndpointTriggeredEvent;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// todo: Test if subscriber is reached
// todo: Only trigger subscriber on POST review endpoint
// todo: (optional) add configuration for when to trigger^
// todo: Send email, will need email template, (optional) configuration for email template, maybe add to other config^
// todo: Add sendTo email to configuration / env variable
class EmailSubscriber implements EventSubscriberInterface
{
    private Client $client;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            EndpointTriggeredEvent::NAME => 'handleEvent',
        ];
    }

    public function __construct()
    {
        $this->client = new Client([
            'http_errors'   =>  false,
            'timeout'       =>  4000.0,
            'verify'        =>  false,
        ]);
    }

    public function getEventType(Endpoint $endpoint): string
    {
        //@TODO: configurability
        $municipality = 'nijmegen';
        $event = 'persoon-overleden';
        //@TODO: add conditions to decide event type
        return "nl.$municipality.brp.$event";
    }

    public function getSource(): string
    {
        //@TODO: This we should find in some configuration
        $oin = "00000001823288444000";
        $system = "VrijBRP";
        return "urn:nld:oin:$oin:systeem:$system";
    }

    public function getSubject(Endpoint $endpoint): string
    {
        //@TODO: This has to be found in the message that we listen to

        return '999992806';
    }

    public function getDataRef(Endpoint $endpoint): string
    {
        //@TODO: make configurable
        $basePath = "https://acc-vrijbrp-nijmegen.commonground.nu/haal-centraal-brp-bevragen/api/v1.3/ingeschrevenpersonen";
        return "$basePath/{$this->getSubject($endpoint)}";
    }

    public function getEvent(Endpoint $endpoint): string
    {
        $dateTime = new \DateTime();
        $result = [
            'specversion'   =>  '1.0',
            'type'          =>  $this->getEventType($endpoint),
            'source'        =>  $this->getSource(),
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
        if(
            $event->getRequest()->getMethod() == 'GET'
            // @TODO: add extra conditions
        ) {
            return $event;
        }

        $eventContent = $this->getEvent($event->getEndpoint());
        $result = $this->sendEvent($eventContent);


        return $event;
    }
}
