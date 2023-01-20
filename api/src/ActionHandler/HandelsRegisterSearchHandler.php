<?php

namespace App\ActionHandler;

use App\Service\HandelsRegisterSearchService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

// todo: delete, moved to kissBundle
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
            'title'      => 'HandelsRegisterSearchHandler',
            'description'=> 'Handles the search action for kvk handelsRegister.',
            'required'   => [],
            'properties' => [
                'entities' => [
                    'type'        => 'string',
                    'description' => 'The entities',
                    'properties'  => [
                        'vestiging' => [
                            'type'        => 'uuid',
                            'description' => 'The uuid of the vestiging entity',
                            'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                            'nullable'    => true,
                        ],
                    ],
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
