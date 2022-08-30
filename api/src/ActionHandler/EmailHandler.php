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
            "required" => ['mailServiceDNS','emailTemplate','emailSender','emailSubject'],
            'properties' => [
                'mailServiceDNS' => [
                    'type' => 'string',
                    'description' => 'The id of the source where to send the emails to'
                ],
                'emailTemplate' => [
                    'type' => 'string',
                    'description' => 'The actual email template'
                ],
                'emailSender' => [
                    'type' => 'string',
                    'description' => 'The sender of the email'
                ],
                'emailSubject' => [
                    'type' => 'string',
                    'description' => 'The subject of the email'
                ]
            ],
        ];
    }

    /**
     * This function runs the zaak type plugin.
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
