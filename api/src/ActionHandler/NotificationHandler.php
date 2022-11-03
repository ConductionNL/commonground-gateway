<?php

namespace App\ActionHandler;

use App\Service\NotificationService;

class NotificationHandler implements ActionHandlerInterface
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action schould comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'NotificationHandler',
            'description'=> 'Handles the notification actions.',
            'required'   => ['sourceId', 'endpoint', 'specversion', 'type', 'source', 'datacontenttype', 'dataref'],
            'properties' => [
                'sourceId' => [
                    'type'        => 'string',
                    'description' => 'The id of the source where to send the notification to',
                    'example'     => 'f3dce042-cd6e-4977-844d-05be8dce7cea',
                    'required'    => true,
                ],
                'endpoint' => [
                    'type'        => 'string',
                    'description' => 'The endpoint on the source where the notification is send to',
                    'example'     => '/webhook',
                    'required'    => true,
                ],
                'query' => [
                    'type'        => 'array',
                    'description' => 'Any additional query parameters that need to be included (for example for validation',
                    'example'     => ['token'=>'123'],
                    'nullable'    => true,
                ],
                'headers' => [
                    'type'        => 'array',
                    'description' => 'Any additional header parameters that need to be included (for example for validation',
                    'example'     => ['token'=>'123'],
                    'nullable'    => true,
                ],
                'includeObject' => [
                    'type'        => 'boolean',
                    'description' => 'Whether to include  the object in the notification',
                    'default'     => false,
                    'examle'      => false,
                    'nullable'    => true,
                ],
                'specversion' => [
                    'type'        => 'string',
                    'description' => 'The spec version of the implementation you are sending from',
                    'default'     => '1.0',
                    'example'     => '1.0',
                    'required'    => true,
                ],
                'type' => [
                    'type'        => 'string',
                    'description' => 'The NL GOV type of the message',
                    'example'     => 'nl.overheid.zaken.zaakstatus-gewijzigd',
                    'required'    => true,
                ],
                'source' => [
                    'type'        => 'string',
                    'description' => 'The NL GOV id of the sender',
                    'example'     => 'urn:nld:oin:00000001823288444000:systeem:BRP-component',
                    'required'    => true,
                ],
                'datacontenttype' => [
                    'type'        => 'string',
                    'enum'        => 'application/json',
                    'description' => 'The content type of the data',
                    'example'     => 'application/json',
                    'required'    => true,
                ],
                'dataref' => [
                    'type'        => 'string',
                    'description' => 'The url to the object (excluding id)',
                    'example'     => 'https://gemeenteX/api/persoon/',
                    'required'    => true,
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
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->notificationService->NotificationHandler($data, $configuration);
    }
}
