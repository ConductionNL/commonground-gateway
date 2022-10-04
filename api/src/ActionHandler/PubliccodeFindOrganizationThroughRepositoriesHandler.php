<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\PubliccodeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PubliccodeFindOrganizationThroughRepositoriesHandler implements ActionHandlerInterface
{
    private PubliccodeService $publiccodeService;

    public function __construct(ContainerInterface $container)
    {
        $publiccodeService = $container->get('publiccodeservice');
        if ($publiccodeService instanceof PubliccodeService) {
            $this->publiccodeService = $publiccodeService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
    }

    public function __run(array $data, array $configuration): array
    {
        return $this->publiccodeService->enrichRepositoryWithOrganizationHandler($data, $configuration);
    }
}
