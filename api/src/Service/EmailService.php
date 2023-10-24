<?php

namespace App\Service;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

// todo: move this to an email plugin with the following packages from composer.json: symfony/mailer, symfony/mailgun-mailer, symfony/sendinblue-mailer & symfony/http-client

/**
 * @Author Wilco Louwerse <wilco@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class EmailService
{
    private Environment $twig;
    private array $data;
    private array $configuration;

    public function __construct(
        Environment $twig
    ) {
        $this->twig = $twig;
    }

    /**
     * Handles the sending of an email based on an event.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     * @return array
     */
    public function EmailHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $this->sendEmail();

        return $data;
    }

    /**
     * Sends and email using an EmailTemplate with configuration for it. It is possible to use $object data in the email if configured right.
     *
     * @throws LoaderError
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     *
     * @return bool
     */
    private function sendEmail(): bool
    {
        // Create mailer with mailgun url
        $transport = Transport::fromDsn($this->configuration['serviceDNS']);
        $mailer = new Mailer($transport);

        // Ready the email template with configured variables
        $variables = [];

        foreach ($this->configuration['variables'] as $key => $variable) {
            if (array_key_exists($variable, $this->data['response'])) {
                $variables[$key] = $this->data['response'][$variable];
            }
        }

        // Render the template
        $html = $this->twig->createTemplate(base64_decode($this->configuration['template']))->render($variables);
        $text = strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html), '\n');

        // Lets allow the use of values from the object Created/Updated with {attributeName.attributeName} in the these^ strings.
        $subject = $this->twig->createTemplate($this->configuration['subject'])->render($variables);
        $receiver = $this->twig->createTemplate($this->configuration['receiver'])->render($variables);
        $sender = $this->twig->createTemplate($this->configuration['sender'])->render($variables);

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

        // Then we can handle some optional configuration
        if (array_key_exists('cc', $this->configuration)) {
            $email->cc($this->configuration['cc']);
        }

        if (array_key_exists('bcc', $this->configuration)) {
            $email->bcc($this->configuration['bcc']);
        }

        if (array_key_exists('replyTo', $this->configuration)) {
            $email->replyTo($this->configuration['replyTo']);
        }

        if (array_key_exists('priority', $this->configuration)) {
            $email->priority($this->configuration['priority']);
        }

        // todo: attachments

        // Send the email
        /** @var Symfony\Component\Mailer\SentMessage $sentEmail */
        $mailer->send($email);

        return true;
    }
}
