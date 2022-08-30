<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\LarpingService;
use Psr\Container\ContainerInterface;

class LarpingHandler implements ActionHandlerInterface
{
    private LarpingService $larpingService;

    public function __construct(ContainerInterface $container)
    {
        $larpingService = $container->get('zdszaakservice');
        if ($larpingService instanceof LarpingService) {
            $this->larpingService = $larpingService;
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
            "required" => ["characterEntityId","effectEntityId"],
            'properties' => [
                'characterEntityId' => [
                    'type' => 'string',
                    'description' => 'The id of the character entity'
                ],
                'effectEntityId' => [
                    'type' => 'string',
                    'description' => 'The id of the effect entity'
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
        return $this->LarpingService->LarpingHandler($data, $configuration);
    }
}
