<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\HuwelijksplannerService;
use Psr\Container\ContainerInterface;

class HuwelijksplannerHandler implements ActionHandlerInterface
{
    private huwelijksplannerService $huwelijksplannerService;

    public function __construct(ContainerInterface $container)
    {
        $huwelijksplannerService = $container->get('zdszaakservice');
        if ($huwelijksplannerService instanceof HuwelijksplannerService) {
            $this->huwelijksplannerService = $huwelijksplannerService;
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
            "required" => ["huwelijksEntityId"],
            'properties' => [
                'huwelijksEntityId' => [
                    'type' => 'string',
                    'description' => 'The id of the huwelijks entity'
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
        return $this->huwelijksplannerService->HuwelijksplannerHandler($data, $configuration);
    }
}
