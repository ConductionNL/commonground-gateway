<?php

namespace App\ActionHandler;

use App\Service\PubliccodeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PubliccodeCheckForPublicCodeHandler implements ActionHandlerInterface
{
    private PubliccodeService $publiccodeService;

    public function __construct(ContainerInterface $container)
    {
        $publiccodeService = $container->get('publiccodeservice');
    }

    public function __run(array $data, array $configuration): array
    {
        return $this->publiccodeService->publiccodeCheckForPublicCodeHandler($data, $configuration);
    }
}
