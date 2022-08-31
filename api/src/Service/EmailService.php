<?php

namespace App\Service;

use App\Entity\EmailTemplate;
use Doctrine\ORM\EntityManagerInterface;
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
class EmailService
{
    private EntityManagerInterface $entityManager;
    private Environment $twig;
    private array $data;
    private array $configuration;

    public function __construct(
        EntityManagerInterface $entityManager,
        Environment $twig
    ) {
        $this->entityManager = $entityManager;
        $this->twig = $twig;
//        $this->mailgun = $parameterBag->get('mailgun');
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
     * @param EmailTemplate $template
     * @param array         $object
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
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
        // @todo right now the code here points to a hardcoded twig template, that should be doft coded the below code does that
        // $html = $this->twig->createTemplate($this->configuration['template'])->render($variables);
        // @todo however this code is more akin to how it works now and refers to a hardcoded twig template in the stack
        $html = $this->twig->render($this->configuration['template'], $variables);
        $text = strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html), '\n');

        // Lets allow the use of values from the object Created/Updated with {attributeName.attributeName} in the these^ strings.
        $subject = $this->replaceWithObjectValues($this->configuration['subject'], $this->data);
        $receiver = $this->replaceWithObjectValues($this->configuration['receiver'], $this->data);
        $sender = $this->replaceWithObjectValues($this->configuration['sender'], $this->data);

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
}
