<?php

namespace App\ActionHandler;

use App\Service\RequestService;

class SearchRequestHandler implements ActionHandlerInterface
{
    private RequestService $requestService;

    public function __construct(RequestService $requestService)
    {
        $this->requestService = $requestService;
    }

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'SearchRequestHandler',
            'required'   => [],
            'properties' => [
                'searchEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the entity you want to search for',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'nullable'    => true,
                ],
            ],
        ];
    }

    /**
     * This function runs the search request service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->requestService->searchRequestHandler($data, $configuration);
    }
}
