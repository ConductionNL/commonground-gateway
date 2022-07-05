<?php

namespace App\Subscriber;

use App\Event\EndpointTriggeredEvent;
use App\Service\ObjectEntityService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

// done: Test if subscriber is reached
// done: Only trigger subscriber on POST review endpoint
// todo: (optional) add configuration for when to trigger^
// todo: (optional) configuration for email template, maybe add to other config^
// done: Send email, will need email template
// todo: Add sendTo email to configuration / env variable
class EmailSubscriber implements EventSubscriberInterface
{
    private SessionInterface $session;
    private ObjectEntityService $objectEntityService;
    private Environment $twig;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            EndpointTriggeredEvent::NAME => 'handleEvent',
        ];
    }

    public function __construct(SessionInterface $session, ObjectEntityService $objectEntityService, Environment $twig)
    {
        $this->session = $session;
        $this->objectEntityService = $objectEntityService;
        $this->twig = $twig;
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
     * Returns a temp email template
     *
     * @param array $parameters
     *
     * @return string
     *
     * @throws LoaderError|RuntimeError|SyntaxError
     */
    private function getEmailTemplate(array $parameters): string
    {
        return $this->twig->render('new-review-e-mail.html.twig', $parameters);
    }

    /**
     * @TODO: docs
     *
     * @param EndpointTriggeredEvent $event
     *
     * @return EndpointTriggeredEvent
     *
     * @throws CacheException|InvalidArgumentException|TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError
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
        $send = $this->sendEmail([
            "service" => [
                "authorization" => "mailgun+api://somecode:somedomain@api.eu.mailgun.net"
            ],
            "content" => $this->getEmailTemplate([
                "subject"       => $object['name'],
                "author"        => $object['author'],
                "topic"         => $object['topic'],
                "rating"        => $object['rating'],
                "description"   => $object['description']
            ]),
            "subject" => $object['name']
        ]);

        return $event;
    }
}
