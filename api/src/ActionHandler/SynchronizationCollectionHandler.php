<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\SynchronizationService;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

class SynchronizationCollectionHandler implements ActionHandlerInterface
{
    private SynchronizationService $synchronizationService;

    /**
     * @param SynchronizationService $synchronizationService
     */
    public function __construct(SynchronizationService $synchronizationService)
    {
        $this->synchronizationService = $synchronizationService;
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
            'title'      => 'SynchronizationCollectionHandler',
            'description' => '',
            'required'   => ['source', 'entity', 'idField'],
            'properties' => [
                'location' => [
                    'type'        => 'string',
                    'description' => 'The subpath to sync from',
                    'example'     => '/subpath',
                    'required'    => true,
                ],
                'source' => [
                    'type'        => 'uuid',
                    'description' => 'The source to sync from',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'entity' => [
                    'type'        => 'uuid',
                    'description' => 'The entity to sync to',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'apiSource' => [
                    'type'        => 'object',
                    'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                    'properties'  => [
                        'location' => [
                            'type'        => 'object',
                            'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                            'properties'  => [
                                'idField' => [
                                    'type'        => 'uuid',
                                    'description' => 'The place where we can find the id field',
                                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                                    'required'    => true,
                                ],
                                'object' => [
                                    'type'        => 'object',
                                    'description' => 'The place where we can find an object',
                                    'example'     => 'native://default',
                                    'nullable'    => true,
                                ],
                                'contentType' => [
                                    'type'        => 'string',
                                    'description' => 'The content type',
                                    'example'     => 'application/json',
                                    'nullable'    => true,
                                ],
                                'xmlRootNodeName' => [
                                    'type'        => 'string',
                                    'description' => 'The XML root name',
                                    'example'     => 'SOAP:env',
                                    'nullable'    => true,
                                ],
                                'dateCreatedField' => [
                                    'type'        => 'string',
                                    'description' => 'The date created field',
                                    'nullable'    => true,
                                ],
                                'dateChangedField' => [
                                    'type'        => 'string',
                                    'description' => 'The date changed field',
                                    'nullable'    => true,
                                ],
                            ],
                        ],
                        'extend' => [ // ??
                            'type'        => 'string',
                            'description' => '',
                            'example'     => '',
                            'nullable'    => true,
                        ],
                        'sourceLimitKey' => [
                            'type'        => 'string',
                            'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                            'example'     => 'native://default',
                            'nullable'    => true,
                        ],
                        'limit' => [
                            'type'        => 'string',
                            'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                            'example'     => 'native://default',
                            'nullable'    => true,
                        ],
                        'sourcePaginated' => [
                            'type'        => 'boolean',
                            'description' => 'If the source is paginated',
                            'example'     => true,
                            'nullable'    => true,
                        ],
                        'skeletonIn' => [
                            'type'        => 'object',
                            'description' => 'Skeleton in',
                            'nullable'    => true,
                        ],
                        'skeletonOut' => [
                            'type'        => 'object',
                            'description' => 'Skeleton out',
                            'nullable'    => true,
                        ],
                        'mappingIn' => [
                            'type'        => 'object',
                            'description' => 'Mapping in',
                            'nullable'    => true,
                        ],
                        'mappingOut' => [
                            'type'        => 'object',
                            'description' => 'Mapping out',
                            'nullable'    => true,
                        ],
                        'translationsIn' => [
                            'type'        => 'object',
                            'description' => 'Translation in',
                            'nullable'    => true,
                        ],
                        'translationsOut' => [
                            'type'        => 'object',
                            'description' => 'Translation out',
                            'nullable'    => true,
                        ],
                        'allowedPropertiesIn' => [
                            'type'        => 'array',
                            'description' => 'The allowed propertied in',
                            'example'     => ['name'],
                            'nullable'    => true,
                        ],
                        'allowedPropertiesOut' => [
                            'type'        => 'array',
                            'description' => 'The allowed propertied out',
                            'example'     => ['name'],
                            'nullable'    => true,
                        ],
                        'notAllowedPropertiesIn' => [
                            'type'        => 'array',
                            'description' => 'The not allowed propertied in',
                            'example'     => ['name'],
                            'nullable'    => true,
                        ],
                        'notAllowedPropertiesOut' => [
                            'type'        => 'array',
                            'description' => 'The not allowed propertied out',
                            'example'     => ['name'],
                            'nullable'    => true,
                        ],
                        'unavailablePropertiesOut' => [
                            'type'        => 'array',
                            'description' => 'The unavailable propertied out',
                            'example'     => ['name'],
                            'nullable'    => true,
                        ],
                        'prefixFieldsOut' => [
                            'type'        => 'array',
                            'description' => 'The prefix fields out',
                            'nullable'    => true,
                        ],
                        'clearNull' => [
                            'type'        => 'boolean',
                            'description' => 'Removes all null fields',
                            'example'     => true,
                            'nullable'    => true,
                        ],
                    ],
                ],
                'callService' => [
                    'type'        => 'array',
                    'description' => 'If we want to overwrite how we do a callService call to a source',
                    'nullable'    => true,
                ],
                'replaceTwigLocation' => [
                    'type'        => 'string',
                    'description' => 'Replace the location with twig',
                    'example'     => 'objectEntityData',
                    'nullable'    => true,
                ],
                'useDataFromCollection' => [
                    'type'        => 'boolean',
                    'description' => 'If to use date from collection',
                    'example'     => false,
                    'nullable'    => true,
                ],
                'queryParams' => [
                    'type'        => 'object',
                    'description' => 'queryParams',
                    'properties'  => [
                        'syncSourceId' => [
                            'type'        => 'uuid',
                            'description' => 'syncSourceId',
                        ],
                    ],
                ],
                'owner' => [
                    'type'        => 'string',
                    'description' => 'The owner',
                    'nullable'    => true,
                ],
                'actionConditions' => [
                    'type'        => 'array',
                    'description' => 'The conditions of the action',
                    'nullable'    => true,
                ],
            ],
        ];
    }

    /**
     * Run the actual business logic in the appropriate server.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException|LoaderError|SyntaxError|GuzzleException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        $this->synchronizationService->SynchronizationCollectionHandler($data, $configuration);

        return $data;
    }
}
