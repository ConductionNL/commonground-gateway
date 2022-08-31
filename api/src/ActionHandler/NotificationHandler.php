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
        $notificationService = $container->get('notificationservice');
        if ($notificationService instanceof NotificationService) {
            $this->notificationService = $notificationService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action schould comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'Notification Action',
            'required'   => ['sourceId'],
            'properties' => [
                'sourceId' => [
                    'type'        => 'string',
                    'description' => 'The id of the source where to send the notification to',
                    'example'     => 'f3dce042-cd6e-4977-844d-05be8dce7cea',
                ],
                'endpoint' => [
                    'type'        => 'string',
                    'description' => 'The endpoint on the source where the  notification is send to',
                    'example'     => '/webhook',
                ],
                'query' => [
                    'type'        => 'array',
                    'description' => 'Any aditional query parameters that need to be included (for example for validation',
                    'example'     => ['token'=>'123'],
                ],
                'headers' => [
                    'type'        => 'array',
                    'description' => 'Any aditional header parameters that need to be included (for example for validation',
                    'example'     => ['token'=>'123'],
                ],
                'includeObject' => [
                    'type'        => 'boolean',
                    'description' => 'Whether to include  the object in the notification',
                    'default'     => false,
                    'examle'      => false,
                ],
                'specversion' => [
                    'type'        => 'string',
                    'description' => 'The spec version of the implementation you are sending from',
                    'default'     => '1.0',
                    'example'     => '1.0',
                ],
                'type' => [
                    'type'        => 'string',
                    'description' => 'The NL GOV type of the message',
                    'example'     => 'nl.overheid.zaken.zaakstatus-gewijzigd',
                ],
                'source' => [
                    'type'        => 'string',
                    'description' => 'The NL GOV id of the sender',
                    'example'     => 'urn:nld:oin:00000001823288444000:systeem:BRP-component',
                ],
                'datacontenttype' => [
                    'type'        => 'string',
                    'enum'        => 'application/json',
                    'description' => 'The id of the source where to send the notification to',
                    'example'     => 'application/json',
                ],
                'dataref' => [
                    'type'        => 'string',
                    'description' => 'The url to the object (excluding id)',
                    'example'     => 'https://gemeenteX/api/persoon/',
                ],
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
