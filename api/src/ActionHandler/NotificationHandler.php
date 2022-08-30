<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\NotificationService;
use Psr\Container\ContainerInterface;

class NotificationHandler implements ActionHandlerInterface
{
    private NotificationService $notificationService;

    public function __construct(ContainerInterface $container)
    {
        $notificationService = $container->get('zdszaakservice');
        if ($notificationService instanceof NotificationService) {
            $this->notificationService = $notificationService;
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
            "required" => ["sourceId"],
            'properties' => [
                'sourceId' => [
                    'type' => 'string',
                    'description' => 'The id of the source where to send the notification to'
                ],
                'includeObject' => [
                    'type' => 'boolean',
                    'description' => 'Whether to include  the object in the notification',
                    'default' =>  false
                ]
            ],
        ];
    }

    /**
     * This function runs the notificationService plugin.
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
        return $this->notificationService->NotificationHandler($data, $configuration);
    }
}
