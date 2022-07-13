<?php

namespace App\Subscriber;

use App\Entity\EmailTemplate;
use App\Entity\EmailTrigger;
use App\Event\EndpointTriggeredEvent;
use App\Repository\EmailTriggerRepository;
use App\Service\ObjectEntityService;
use Exception;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
// todo ... and (re)move api/config/packages/mailer.yaml
// todo ... and (re)move the Entities EmailTrigger & EmailTemplate
// todo ... and (re)move the mailgun .env variable (see mailgun in the following files: .env, docker-compose.yaml, helm/values.yaml, helm/templates/deployment.yaml & helm/templates/secrets.yaml)
class EmailSubscriber implements EventSubscriberInterface
{
    private SessionInterface $session;
    private ObjectEntityService $objectEntityService;
    private Environment $twig;
    private EmailTriggerRepository $emailTriggerRepository;
    private string $mailgun;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            EndpointTriggeredEvent::NAME => 'handleEvent',
        ];
    }

    /**
     * @param SessionInterface       $session
     * @param ObjectEntityService    $objectEntityService
     * @param Environment            $twig
     * @param EmailTriggerRepository $emailTriggerRepository
     * @param ParameterBagInterface  $parameterBag
     *
     * @throws Exception
     */
    public function __construct(SessionInterface $session, ObjectEntityService $objectEntityService, Environment $twig, EmailTriggerRepository $emailTriggerRepository, ParameterBagInterface $parameterBag)
    {
        $this->session = $session;
        $this->objectEntityService = $objectEntityService;
        $this->twig = $twig;
        $this->emailTriggerRepository = $emailTriggerRepository;
        $this->mailgun = $parameterBag->get('mailgun');
        if (empty($this->mailgun) ||
            $this->mailgun === 'mailgun+api://code:domain@api.eu.mailgun.net'
        ) {
            throw new Exception("The MAILGUN env variable is not set (or still on it's default value).");
        }
    }

    /**
     * Sends and email using an EmailTemplate with configuration for it. It is possible to use $object data in the email if configured right.
     *
     * @param EmailTemplate $template
     * @param array         $object
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     * @return bool
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

        // Get the subject, receiver and sender
        // Lets allow the use of values from the object Created/Updated with {attributeName.attributeName} in the these^ strings.
        $subject = $this->replaceWithObjectValues($template->getSubject(), $object);
        $receiver = $this->replaceWithObjectValues($template->getReceiver(), $object);
        $sender = $this->replaceWithObjectValues($template->getSender(), $object);

        // If we have no sender, set sender to receiver
        if (!$sender) {
            $sender = $receiver;
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
     * Replaces {attributeName.attributeName} in given $source string with values from the object Created/Updated.
     *
     * @param string $source
     * @param array  $object
     *
     * @return string
     */
    private function replaceWithObjectValues(string $source, array $object): string
    {
        $destination = $source;

        // Find {attributeName.attributeName} in $source string
        preg_match_all('/{([#A-Za-z0-9.]+)}/', $source, $matches);
        if (!empty($matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                // If we find matches, get the value for {attributeName.attributeName} from $object.
                $objectAttribute = explode('.', $matches[1][$i]);
                $replacement = $object;
                foreach ($objectAttribute as $item) {
                    $replacement = $replacement[$item];
                }

                // Replace {attributeName.attributeName} in $source with the corresponding value from $object.
                $pattern = '/'.$matches[0][$i].'/';
                $destination = preg_replace($pattern, $replacement, $destination);
            }
        }

        return $destination;
    }

    /**
     * Handles an EndpointTriggeredEvent, checks all EmailTriggers, if event->endpoint, ->request and ->hooks match send email(s) per EmailTemplate.
     *
     * @param EndpointTriggeredEvent $event
     *
     * @throws CacheException|InvalidArgumentException|TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError
     *
     * @return EndpointTriggeredEvent
     */
    public function handleEvent(EndpointTriggeredEvent $event): EndpointTriggeredEvent
    {
        // Find EmailTriggers by Endpoint
        $emailTriggers = $this->emailTriggerRepository->findByEndpoint($event->getEndpoint());
        foreach ($emailTriggers as $emailTrigger) {
            // Check if request and event hooks match this EmailTrigger
            if (($emailTrigger->getRequest() && !$this->checkTriggerRequest($event, $emailTrigger)) ||
                empty(array_intersect($emailTrigger->getHooks(), $event->getHooks()))) {
                continue;
            }

            // Get the object Created/Updated when the endpoint of this trigger was called.
            $object = $this->objectEntityService->getObject($event->getEndpoint()->getHandlers()->first()->getEntity(), $this->session->get('object'));

            // Send an email per template this EmailTrigger has.
            foreach ($emailTrigger->getTemplates() as $template) {
                // todo: do this async? (see Event/EndpointTriggeredEvent.php)
                $send = $this->sendEmail($template, $object);
            }
        }

        return $event;
    }

    /**
     * Compares EndpointTriggeredEvent Request with EmailTrigger request, returns false if there is a mismatch and the trigger should not go off (and send one or more emails).
     *
     * @param EndpointTriggeredEvent $event
     * @param EmailTrigger           $emailTrigger
     *
     * @return bool
     */
    private function checkTriggerRequest(EndpointTriggeredEvent $event, EmailTrigger $emailTrigger): bool
    {
        $triggerRequest = $emailTrigger->getRequest();
        $eventRequest = $event->getRequest();

        // Example, we can use this to check for specific queryParams.
        if (array_key_exists('query', $triggerRequest)) {
            $queries = $eventRequest->query->all();
            foreach ($triggerRequest['query'] as $key => $value) {
                if (!array_key_exists($key, $queries) || $queries[$key] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }
}
