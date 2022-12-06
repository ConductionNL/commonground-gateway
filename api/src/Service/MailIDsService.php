<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use ErrorException;
use Exception;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Serializer\SerializerInterface;

class MailIDsService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;
    private array $configuration;
    private array $data;

    /**
     * @param EntityManagerInterface $entityManager
     * @param SynchronizationService $synchronizationService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $synchronizationService,
        SerializerInterface $serializer
    ) {
        $this->entityManager = $entityManager;
        $this->synchronizationService = $synchronizationService;
        $this->serializer = $serializer;
    }

    /**
     * This function gets syncs that went wrong and mails them to configured emailadresses
     *
     * @param array $data The data from the call
     *
     * @return string The identifierPath in the action configuration
     */
    public function mailIDsHandler(array $data, array $configuration)
    {
        $this->configuration = $configuration;
        $this->data = $data;

        var_dump('MAIL IDS TRIGGERED');

        $addressesToMail = (isset($this->configuration['emailaddresses']) && !empty($this->configuration['emailaddresses'])) ? $this->configuration['emailaddresses'] : null;
        $serviceDNS = (isset($this->configuration['serviceDNS']) && !empty($this->configuration['serviceDNS'])) ? $this->configuration['serviceDNS'] : null;
        $sender = (isset($this->configuration['sender']) && !empty($this->configuration['sender'])) ? $this->configuration['sender'] : null;
        if (!$addressesToMail || !$sender || !$serviceDNS) {
            return ['response' => $data];
        }

        $syncThatWentBad = $this->entityManager->getRepository('App:Synchronization')->findByLastSyncIsNull();

        if (count($syncThatWentBad) < 1) {
            return ['response' => $data];
        }

        $objects = [];
        foreach ($syncThatWentBad as $sync) {
            $sync->getObject() !== null && $objects[] = $sync->getObject();
        }

        $csv = [];
        foreach ($objects as $object) {
            $csv[] = ['Date' => $object->getDateCreated(), 'ExternalID' => $object->getExternalId(), 'ZaakType' => ''];
        }

        $csv = $this->serializer->encode($csv, 'csv');

        // $fh = fopen('php://temp', 'rw'); # don't create a file, attempt to use memory instead

        // # write out the headers
        // fputcsv($fh, array_keys(current($csv)));

        // # write out the data
        // foreach ($csv as $row) {
        //     fputcsv($fh, $row);
        // }
        // rewind($fh);
        // $csv = stream_get_contents($fh);
        // fclose($fh);

        $serviceDNS = 'mailgun+api://bzTUk5bCleXKnBM:mailgun.com@api.eu.mailgun.net';
        $transport = Transport::fromDsn($serviceDNS);
        $mailer = new Mailer($transport);

        $now = new \DateTime('now');
        $now->sub(new DateInterval('PT1H'));

        var_dump($sender);
        var_dump($addressesToMail);

        count($addressesToMail) > 1 ? $to = implode(", ", $addressesToMail) : $to = $addressesToMail[0];

        // Create the email
        $email = (new Email())
            ->from($sender)
            ->to($to)
            ->subject('Uitgevallen berichten van ' . $now->format('Y-m-d'))
            ->text('De objecten die uitgevallen zijn, zijn te vinden in de bijlage')
            ->attach($csv, 'uitgevallen_berichten_' . $now->format('Y-m-d') . '.csv', 'text/csv');

        // todo: attachments

        // Send the email
        /** @var Symfony\Component\Mailer\SentMessage $sentEmail */
        $mailer->send($email);

        return ['response' => $data];
    }
}
