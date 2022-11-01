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
        // TODO: Implement getConfiguration() method.
    }

    public function run(array $data, array $configuration): array
    {
        return $this->publiccodeService->enrichRepositoryWithOrganizationHandler($data, $configuration);
    }
}
