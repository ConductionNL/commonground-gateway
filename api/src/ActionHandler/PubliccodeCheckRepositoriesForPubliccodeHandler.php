<?php

namespace App\ActionHandler;

use App\Service\PubliccodeService;

class PubliccodeCheckRepositoriesForPubliccodeHandler
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

    public function __construct(PubliccodeService $publiccodeService)
    {
        $this->publiccodeService = $publiccodeService;
    }

    public function run(array $data, array $configuration): array
    {
        return $this->getPubliccodeService()->enrichPubliccodeHandler($data, $configuration);
    }
}
