<?php

namespace App\Service;

use App\Entity\EmailTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
    private ObjectEntityService $objectEntityService;
    private EntityManagerInterface $entityManager;
    private Environment $twig;
    private array $configuration;
    private string $mailgun;

    public function __construct(
        EntityManagerInterface $entityManager,
        Environment $twig,
        ParameterBagInterface $parameterBag,
        ObjectEntityService $objectEntityService
    ) {
        $this->entityManager = $entityManager;
        $this->twig = $twig;
        $this->mailgun = $parameterBag->get('mailgun');
    }

    /**
     * @todo
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     */
    public function EmailHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

        // todo: het idee is/was dat we een of meerdere triggers kunnen hebben die afgaan op een of meerdere endpoints
        // todo: hiervoor kunnen we commongateway.object.create gebruiken, in combinatie met conditions voor een specifieke Entity id van de Review entity
        // todo: in config van Action aangeven welke email templates allemaal af moeten gaan, dus een lijstje met id's, in dit geval voor kiss maar eentje nodig (zie kiss-apis.yaml emailTemplates)
        // todo: In plaats van een lijstje met id's van EmailTemplate objecten kunnen we ook gewoon de configuratie die in deze objecten word opgeslagen in de configuratie array van de action stoppen

        // Send an email per template this EmailTrigger has.
        foreach ($this->configuration['emailTemplates'] as $templateId) {
            $template = $this->entityManager->getRepository('App:EmailTemplate')->find($templateId);
            $send = $this->sendEmail($template, $data['response']);
        }

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
}
