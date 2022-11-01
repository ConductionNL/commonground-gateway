<?php

namespace App\ActionHandler;

use App\Service\LarpingService;

class LarpingHandler implements ActionHandlerInterface
{
    private LarpingService $larpingService;

    public function __construct(LarpingService $larpingService)
    {
        $this->larpingService = $larpingService;
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
            'required'   => ['characterEntityId', 'effectEntityId'],
            'properties' => [
                'characterEntityId' => [
                    'type'        => 'string',
                    'description' => 'The id of the character entity',
                ],
                'effectEntityId' => [
                    'type'        => 'string',
                    'description' => 'The id of the effect entity',
                ],
            ],
        ];
    }

    /**
     * This function runs the zaak type plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     * @throws \App\Exception\GatewayException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->larpingService->LarpingHandler($data, $configuration);
    }
}
