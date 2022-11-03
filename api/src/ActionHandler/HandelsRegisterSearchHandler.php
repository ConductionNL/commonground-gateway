<?php

namespace App\ActionHandler;

use App\Service\HandelsRegisterSearchService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class HandelsRegisterSearchHandler implements ActionHandlerInterface
{
    private HandelsRegisterSearchService $handelsRegisterSearchService;

    public function __construct(HandelsRegisterSearchService $handelsRegisterSearchService)
    {
        $this->handelsRegisterSearchService = $handelsRegisterSearchService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'Notification Action',
            'required'   => ['ServiceDNS'],
            'properties' => [
                'serviceDNS' => [
                    'type'        => 'string',
                    'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                    'example'     => 'native://default',
                ],
            ],
        ];
    }

    /**
     * This function runs the HandelsRegisterSearch service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->handelsRegisterSearchService->handelsRegisterSearchHandler($data, $configuration);
    }
}
