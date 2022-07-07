<?php

namespace App\Subscriber;

use App\Entity\EmailTemplate;
use App\Entity\EmailTrigger;
use App\Entity\ObjectEntity;
use App\Event\EndpointTriggeredEvent;
use App\Repository\EmailTriggerRepository;
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

// todo: move this to an email plugin with the following packages from composer.json: symfony/mailer, symfony/mailgun-mailer & symfony/http-client
// todo ... and (re)move api/config/packages/mailer.yaml and api/.env variables symfony/mailer & symfony/mailgun-mailer
// todo ... and (re)move the Entities EmailTrigger & EmailTemplate
class EmailSubscriber implements EventSubscriberInterface
{
    private SessionInterface $session;
    private ObjectEntityService $objectEntityService;
    private Environment $twig;
    private EmailTriggerRepository $emailTriggerRepository;
    // todo: add mailgun to env variables / secrets
    private string $mailgun = "mailgun+api://code:domain@api.eu.mailgun.net";

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            EndpointTriggeredEvent::NAME => 'handleEvent',
        ];
    }

    public function __construct(SessionInterface $session, ObjectEntityService $objectEntityService, Environment $twig, EmailTriggerRepository $emailTriggerRepository)
    {
        $this->session = $session;
        $this->objectEntityService = $objectEntityService;
        $this->twig = $twig;
        $this->emailTriggerRepository = $emailTriggerRepository;
    }

    /**
     * @TODO: docs
     *
     * @param EmailTemplate $template
     * @param array $object
     *
     * @return bool
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     */
    private function sendEmail(EmailTemplate $template, array $object): bool
    {
        // Create mailer with mailgun url
        $transport = Transport::fromDsn($this->mailgun);
        $mailer = new Mailer($transport);

        // Ready the email template with configured variables
        $variables = [];
        foreach ($template->getVariables() as $key => $variable) {
            if (array_key_exists($variable, $object)) {
                $variables[$key] = $object[$variable];
            }
        }
        $html = $this->twig->render($template->getContent(), $variables);
        $text = strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html), '\n');

        // Get the sender, receiver and subject
        $sender = $template->getSender(); // todo: also make it possible to use {attributeName.attributeName} for sender
        // todo: move this to function (duplicate):
        // Lets allow the use of values from the object Created/Updated with {attributeName.attributeName} in the $template->getReceiver().
        preg_match('/{([#A-Za-z0-9.]+)}/', $template->getReceiver(), $matches); // todo: do match_all instead
        if (!empty($matches)) {
            $objectAttribute = explode('.', $matches[1]);
            $replacement = $object;
            foreach ($objectAttribute as $item) {
                $replacement = $replacement[$item];
            }
            $pattern = '/'.$matches[0].'/';
            $receiver = preg_replace($pattern, $replacement, $template->getReceiver());
        } else {
            $receiver = $template->getReceiver();
        }

        // If we have no sender, set sender to receiver
        if (!$sender) {
            $sender = $receiver;
        }

        // todo: move this to function (duplicate):
        // Lets allow the use of values from the object Created/Updated with {attributeName.attributeName} in the $template->getSubject().
        preg_match('/{([#A-Za-z0-9.]+)}/', $template->getSubject(), $matches); // todo: do match_all instead
        if (!empty($matches)) {
            $objectAttribute = explode('.', $matches[1]);
            $replacement = $object;
            foreach ($objectAttribute as $item) {
                $replacement = $replacement[$item];
            }
            $pattern = '/'.$matches[0].'/';
            $subject = preg_replace($pattern, $replacement, $template->getSubject());
        } else {
            $subject = $template->getSubject();
        }

        // Create the email
        $email = (new Email())
            ->from($sender)
            ->to($receiver)
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject($subject)
            ->html($html)
            ->text($text);

        // todo: attachments

        // Send the email
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
     * @throws CacheException|InvalidArgumentException|TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError
     */
    public function handleEvent(EndpointTriggeredEvent $event): EndpointTriggeredEvent
    {
        // Find EmailTriggers by Endpoint
        $emailTriggers = $this->emailTriggerRepository->findByEndpoint($event->getEndpoint());
        foreach ($emailTriggers as $emailTrigger) {
            // Check if request and event hooks match this EmailTrigger
            if ($emailTrigger->getRequest()) {
                // todo move this to a function
                // Example, method is already checked by the Endpoint. But we could, for example, check for queryParams with this.
                if (array_key_exists('methods', $emailTrigger->getRequest()) &&
                    !in_array($event->getRequest()->getMethod(), $emailTrigger->getRequest()['methods'])
                ) {
                    continue;
                }
            }
            if (empty(array_intersect($emailTrigger->getHooks(), $event->getHooks()))) {
                continue;
            }

            // Get the object Created/Updated when the endpoint of this trigger was called.
            $object = $this->objectEntityService->getObject($event->getEndpoint()->getHandlers()->first()->getEntity(), $this->session->get('object'));

            // Send an email per template this EmailTrigger has.
            foreach ($emailTrigger->getTemplates() as $template) {
                // todo: do this async? (see EndpointTriggeredEvent)
                $send = $this->sendEmail($template, $object);
            }
        }

        return $event;
    }
}
