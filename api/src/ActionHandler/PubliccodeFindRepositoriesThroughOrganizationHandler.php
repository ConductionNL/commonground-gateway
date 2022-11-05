<?php

namespace App\ActionHandler;

use App\Service\PubliccodeService;

class PubliccodeFindRepositoriesThroughOrganizationHandler implements ActionHandlerInterface
{
    private PubliccodeService $publiccodeService;

    /**
     * Wrapper function to prevent service loading on container autowiring
     *
     * @param PubliccodeService $publiccodeService
     * @return PubliccodeService
     */
    private function getPubliccodeService(PubliccodeService $publiccodeService):PubliccodeService {
        if(isset($this->publiccodeService)) {$this->publiccodeService = $publiccodeService;}
        return  $this->publiccodeService;
    }

    public function getConfiguration()
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'PubliccodeFindRepositoriesThroughOrganizationHandler',
            'description'=> 'This handler finds repositories through organizations',
            'required'   => ['repositoryEntityId'],
            'properties' => [
                'repositoryEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the repository entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
            ],
        ];
    }

    public function run(array $data, array $configuration): array
    {
        return $this->getPubliccodeService()->enrichOrganizationWithRepositoriesHandler($data, $configuration);
    }
}
