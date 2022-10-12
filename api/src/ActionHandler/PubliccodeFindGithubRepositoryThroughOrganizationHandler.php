<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\PubliccodeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PubliccodeFindGithubRepositoryThroughOrganizationHandler
{
    private PubliccodeService $publiccodeService;

    public function __construct(PubliccodeService $publiccodeService)
    {
        $this->publiccodeService = $publiccodeService;
    }

    public function run(array $data, array $configuration): array
    {
        return $this->publiccodeService->enrichOrganizationWithCatalogi($data, $configuration);
    }
}
