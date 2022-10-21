<?php

namespace App\ActionHandler;

use App\Service\PubliccodeService;

class PubliccodeFindOrganizationThroughRepositoriesHandler
{
    /**
     * Gets the PubliccodeService trough autowiring
     *
     * @param PubliccodeService $publiccodeService
     * @return PubliccodeService
     */
    private function getPubliccodeService(PubliccodeService $publiccodeService):PubliccodeService
    {
        return $publiccodeService;
    }

    public function run(array $data, array $configuration): array
    {
        return $this->getPubliccodeService()->enrichRepositoryWithOrganizationHandler($data, $configuration);
    }
}
