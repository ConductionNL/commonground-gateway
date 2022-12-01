<?php

namespace App\ActionHandler;

use  CommonGateway\OpenCatalogiBundle\Service\PubliccodeService;

class EnrichPubliccodeHandler implements ActionHandlerInterface
{
    private PubliccodeService $publiccodeService;

    public function __construct(PubliccodeService $publiccodeService)
    {
        $this->publiccodeService = $publiccodeService;
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
            'title'      => 'EnrichPubliccodeHandler',
            'required'   => ['repositoryEntityId', 'componentEntityId', 'descriptionEntityId'],
            'properties' => [
                'repositoryEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the repository entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'componentEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the component entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'descriptionEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the description entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
            ],
        ];
    }

    public function run(array $data, array $configuration): array
    {
        return $this->publiccodeService->enrichPubliccodeHandler($data, $configuration);
    }
}
