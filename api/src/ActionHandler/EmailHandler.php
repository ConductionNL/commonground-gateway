<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\EmailService;
use Psr\Container\ContainerInterface;

class EmailHandler implements ActionHandlerInterface
{
    private EmailService $emailService;

    public function __construct(ContainerInterface $container)
    {
        $emailService = $container->get('mailService');
        if ($emailService instanceof EmailService) {
            $this->emailService = $emailService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action schould comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id' => "https://example.com/person.schema.json",
            '$schema' => "https://json-schema.org/draft/2020-12/schema",
            'title' => "Notification Action",
            "required" => ['ServiceDNS','template','sender','reciever','subject'],
            'properties' => [
                'serviceDNS' => [
                    'type' => 'string',
                    'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                    'example' => 'native://default'
                ],
                'template' => [
                    'type' => 'string',
                    'description' => 'The actual email template',
                    'example' => 'emails/kiss/new-review-e-mail.html.twig'
                ],
                'variables' => [
                    'type' => 'array',
                    'description' => 'The variables supported by this template (might contain default vallues)',
                ],
                'sender' => [
                    'type' => 'string',
                    'description' => 'The sender of the email',
                    'example' => 'info@conduction.nl'
                ],
                'reciever' => [
                    'type' => 'string',
                    'description' => 'The reciever of the email',
                    'example' => 'j.do@conduction.nl'
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'The subject of the email',
                    'example' => 'Your weekly update'
                ],
                'cc' => [
                    'type' => 'string',
                    'description' => 'Carbon copy, email boxes that should recieve a copy of  this mail',
                    'example' => 'archive@conduction.nl'
                ],
                'bcc' => [
                    'type' => 'string',
                    'description' => 'Blind carbon copy, people that should recieve a copy without other recipient knowing',
                    'example' => 'b.brother@conduction.nl'
                ],
                'replyTo' => [
                    'type' => 'string',
                    'description' => 'The adres the reciever should reply to, only provide this if it differs from the sender address',
                    'example' => 'no-reply@conduction.nl'
                ],
                'priority' => [
                    'type' => 'string',
                    'description' => 'An optional priority for the email'
                ]
            ],
        ];
    }

    /**
     * This function runs the email service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws \App\Exception\GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
        return $this->emailService->emailHandler($data, $configuration);
    }
}
