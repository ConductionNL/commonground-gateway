<?php

namespace App\Subscriber;

use App\Entity\Endpoint;
use App\Event\EndpointTriggeredEvent;
use App\Service\ObjectEntityService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

// done: Test if subscriber is reached
// done: Only trigger subscriber on POST review endpoint
// todo: (optional) add configuration for when to trigger^
// todo: Send email, will need email template, (optional) configuration for email template, maybe add to other config^
// todo: Add sendTo email to configuration / env variable
class EmailSubscriber implements EventSubscriberInterface
{
    private Client $client;
    private SessionInterface $session;
    private ObjectEntityService $objectEntityService;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            EndpointTriggeredEvent::NAME => 'handleEvent',
        ];
    }

    public function __construct(SessionInterface $session, ObjectEntityService $objectEntityService)
    {
        $this->session = $session;
        $this->objectEntityService = $objectEntityService;

        $this->client = new Client([
            'http_errors'   =>  false,
            'timeout'       =>  4000.0,
            'verify'        =>  false,
        ]);
    }

//    public function getEventType(Endpoint $endpoint): string
//    {
//        //@TODO: configurability
//        $municipality = 'nijmegen';
//        $event = 'persoon-overleden';
//        //@TODO: add conditions to decide event type
//        return "nl.$municipality.brp.$event";
//    }
//
//    public function getSource(): string
//    {
//        //@TODO: This we should find in some configuration
//        $oin = "00000001823288444000";
//        $system = "VrijBRP";
//        return "urn:nld:oin:$oin:systeem:$system";
//    }
//
//    public function getSubject(Endpoint $endpoint): string
//    {
//        //@TODO: This has to be found in the message that we listen to
//
//        return '999992806';
//    }
//
//    public function getDataRef(Endpoint $endpoint): string
//    {
//        //@TODO: make configurable
//        $basePath = "https://acc-vrijbrp-nijmegen.commonground.nu/haal-centraal-brp-bevragen/api/v1.3/ingeschrevenpersonen";
//        return "$basePath/{$this->getSubject($endpoint)}";
//    }
//
//    public function getEvent(Endpoint $endpoint): string
//    {
//        $dateTime = new \DateTime();
//        $result = [
//            'specversion'   =>  '1.0',
//            'type'          =>  $this->getEventType($endpoint),
//            'source'        =>  $this->getSource(),
//            'subject'       =>  $this->getSubject($endpoint),
//            'id'            =>  Uuid::uuid4(),
//            'time'          =>  $dateTime->format('Y-m-d\TH:i:s\Z'),
//            'dataref'       =>  $this->getDataRef($endpoint),
//        ];
//
//        return json_encode($result);
//    }

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

    /**
     * @TODO: docs
     *
     * @param array $mail
     *
     * @return bool
     *
     * @throws TransportExceptionInterface
     */
    private function sendEmail(array $mail): bool
    {
        //todo: add symfony/mailer and symfony/mailgun-mailer to composer.json
        $transport = Transport::fromDsn($mail['service']['authorization']);
        $mailer = new Mailer($transport);

        $html = $mail['content'];
        $text = strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html), '\n');
        $sender = "wilco@conduction.nl";
        $reciever = "wilco@conduction.nl";

        $email = (new Email())
            ->from($sender)
            ->to($reciever)
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject($mail['subject'] ?? $mail['content']['name'])
            ->html($html)
            ->text($text);

        // todo: attachments

        /** @var Symfony\Component\Mailer\SentMessage $sentEmail */
        $mailer->send($email);

        return true;
    }

    /**
     * @TODO: docs
     *
     * @param EndpointTriggeredEvent $event
     *
     * @return EndpointTriggeredEvent
     *
     * @throws CacheException|InvalidArgumentException
     */
    public function handleEvent(EndpointTriggeredEvent $event): EndpointTriggeredEvent
    {
        if (
            $event->getRequest()->getMethod() !== 'POST' ||
            !in_array('POST_HANDLER', $event->getHooks()) ||
            (count($event->getEndpoint()->getHandlers()) > 0 &&
            $event->getEndpoint()->getHandlers()->first()->getEntity()->getName() !== 'Review')
            // todo: use Config to determine when this subscriber should trigger and use something better than this^?
        ) {
            return $event;
        }

        $object = $this->objectEntityService->getObject($event->getEndpoint()->getHandlers()->first()->getEntity(), $this->session->get('object'));
        var_dump($object);
//        $result = $this->sendEvent($eventContent);


        return $event;
    }
}
