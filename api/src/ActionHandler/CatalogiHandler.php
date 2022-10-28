<?php

namespace App\ActionHandler;

use App\Service\CatalogiService;

class CatalogiHandler
{
    private CatalogiService $catalogiService;

    public function __construct(CatalogiService $catalogiService)
    {
        $this->catalogiService = $catalogiService;
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
            'title'      => 'Catalogi Action',
            'required'   => [],
            'properties' => [
                'todo' => [
                    'type'        => 'string',
                    'description' => 'todo',
                    'example'     => 'todo',
                ],
            ],
        ];
    }

    /**
     * This function runs the email service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->catalogiService->catalogiHandler($data, $configuration);
    }
}
