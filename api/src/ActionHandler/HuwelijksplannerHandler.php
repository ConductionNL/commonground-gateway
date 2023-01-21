<?php

namespace App\ActionHandler;

use App\Service\HuwelijksplannerService;

class HuwelijksplannerHandler implements ActionHandlerInterface
{
    private huwelijksplannerService $huwelijksplannerService;

    public function __construct(HuwelijksplannerService $huwelijksplannerService)
    {
        $this->huwelijksplannerService = $huwelijksplannerService;
    }

    function getConditions() {
        return ['==' => [1, 1]];
    }

    function getListens() {
        return [
            'none'
        ];
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
            'title'      => 'HuwelijksplannerHandler',
            'description'=> 'Handles Huwelijkslnner actions.',
            'required'   => ['huwelijksEntityId'],
            'properties' => [],
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
    public function run(array $data, array $configuration): array
    {
        return $this->huwelijksplannerService->HuwelijksplannerHandler($data, $configuration);
    }
}
