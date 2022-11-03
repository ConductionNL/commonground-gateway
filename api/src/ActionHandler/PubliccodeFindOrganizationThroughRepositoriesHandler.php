<?php

namespace App\ActionHandler;

use App\Service\PubliccodeService;

class PubliccodeFindOrganizationThroughRepositoriesHandler implements ActionHandlerInterface
{
    private PubliccodeService $publiccodeService;

    public function __construct(PubliccodeService $publiccodeService)
    {
        $this->publiccodeService = $publiccodeService;
    }

    public function getConfiguration()
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'PubliccodeFindOrganizationThroughRepositoriesHandler',
            'description'=> 'This handler finds organizations through repositories',
            'required'   => ['repositoryEntityId', 'organisationEntityId'],
            'properties' => [
                'repositoryEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the repository entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'organisationEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the organisation entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
            ],
        ];
    }

    public function run(array $data, array $configuration): array
    {
        return $this->publiccodeService->enrichRepositoryWithOrganizationHandler($data, $configuration);
    }
}
