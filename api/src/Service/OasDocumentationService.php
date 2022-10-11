<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\Attribute;
use App\Entity\CollectionEntity;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Handler;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\This;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

class OasDocumentationService
{
    private ParameterBagInterface $params;
    private EntityManagerInterface $em;
    private TranslationService $translationService;
    private HandlerService $handlerService;

    public array $indirectEntities;

    // Let's define the validator that we support for documentation right now
    private const SUPPORTED_TYPES = [
        'string',
        'date',
        'datetime',
        'integer',
        'array',
        'boolean',
        'float',
        'number',
        'file',
        'object',
    ];

    private const SUPPORTED_VALIDATORS = [
        'multipleOf',
        'maximum',
        'exclusiveMaximum',
        'minimum',
        'exclusiveMinimum',
        'maxLength',
        'minLength',
        'maxItems',
        'uniqueItems',
        'maxProperties',
        'minProperties',
        'required',
        'enum',
        'allOf',
        'oneOf',
        'anyOf',
        'not',
        'items',
        'additionalProperties',
        'default',
    ];

    public function __construct(
        ParameterBagInterface $params,
        EntityManagerInterface $em,
        TranslationService $translationService,
        HandlerService $handlerService
    ) {
        $this->params = $params;
        $this->em = $em;
        $this->translationService = $translationService;
        $this->handlerService = $handlerService;
        $this->indirectEntities = [];
    }

    /**
     * Turns a string to CamelCase.
     *
     * @param string $string the string to convert to CamelCase
     *
     * @return string the CamelCase representation of the string
     */
    public function toCamelCase(string $string, array $dontStrip = []): string
    {
        /*
         * This will take any dash or underscore turn it into a space, run ucwords against
         * it, so it capitalizes the first letter in all words separated by a space then it
         * turns and deletes all spaces.
         */
        return lcfirst(str_replace(' ', '', ucwords(preg_replace('/^a-z0-9'.implode('', $dontStrip).']+/', ' ', $string))));
    }

    /**
     * Returns a basic schema setup.
     *
     * @return array
     */
    public function basicSchemaSetup(): array
    {
        return [
            'id'           => 'uuid',
            'type'         => 'string',
            'context'      => 'string',
            'dateCreated'  => date('d-m-Y H:s'),
            'dateModified' => date('d-m-Y H:s'),
            'owner'        => 'string',
            'organization' => 'string',
            'application'  => 'string',
            'uri'          => 'string',
            'gateway/id'   => 'string',
        ];
    }

    /**
     * Gets the response type from the method.
     *
     * @param string $method
     *
     * @return array|bool
     */
    public function getResponseType(string $method)
    {
        $response = false;
        switch ($method) {
            case 'get':
                $response = [
                    'statusCode'  => 200,
                    'description' => 'OK',
                ];
                break;
            case 'post':
                $response = [
                    'statusCode'  => 201,
                    'description' => 'Created',
                ];
                break;
            case 'put':
                $response = [
                    'statusCode'  => 202,
                    'description' => 'Accepted',
                ];
                break;
            case 'patch':
                //
                $response = [
                    'statusCode'  => 200,
                    'description' => 'OK',
                ];
            case 'delete':
                $response = [
                    'statusCode'  => 204,
                    'description' => 'No Content',
                ];
                break;
        }

        return $response;
    }

    /**
     * Generates an attribute example for type.
     *
     * @param Attribute $attribute
     *
     * @return ?string
     */
    public function getAttributeType(Attribute $attribute)
    {
        $example = 'string';
        // switch format to add example data to attributes  */
        switch ($attribute->getType()) {
            case 'string':
                if ($attribute->getEnum()) {
                    $example = $attribute->getEnum();
                } else {
                    $example = 'string';
                }
                break;
            case 'date':
                $example = date('d-m-Y');
                break;
            case 'datetime':
                $example = date('d-m-Y H:s');
                break;
            case 'integer':
                $example = 1;
                break;
            case 'array':
                $example = ['string', 'string'];
                break;
            case 'boolean':
                $example = true;
                break;
            case 'float':
                $example = 0.000;
                break;
            case 'number':
                $example = 175;
                break;
            case 'file':
                if ($attribute->getFileTypes()) {
                    $example = $attribute->getFileTypes();
                } else {
                    $example = 'example.pdf';
                }
                break;
            case 'object':
                break;
            default:
                $example = 'string';
        }

        return $example;
    }

    /**
     * Generates an attribute example for format.
     *
     * @param Attribute $attribute
     *
     * @return ?string
     */
    public function getAttributeFormat(Attribute $attribute): ?string
    {
        // switch format to add example data to attributes  */
        switch ($attribute->getFormat()) {
            case 'countryCode':
                $example = 'NL';
                break;
            case 'bsn':
                $example = '9999999990';
                break;
            case 'url':
                $example = 'www.example.nl';
                break;
            case 'uri':
                $example = '/api/example/94e8bb2c-e66b-11ec-8fea-0242ac120002';
                break;
            case 'uuid':
                $example = '94e8bb2c-e66b-11ec-8fea-0242ac120002';
                break;
            case 'email':
                $example = 'example@hotmail.com';
                break;
            case 'phone':
                $example = '0612345678';
                break;
            case 'json':
                $example = [
                    'string'  => 'string',
                    'string1' => 'string1',
                ];
                $example = json_encode($example);
                break;
            case 'dutch_pc4':
                $example = '1217';
                break;
            default:
                $example = 'string';
        }

        return $example;
    }

    /**
     * Generates attribute examples for format and type.
     *
     * @param Attribute $attribute
     *
     * @return string
     */
    public function generateAttributeExample(Attribute $attribute)
    {
        if ($attribute->getFormat()) {
            $example = $this->getAttributeFormat($attribute);
        } else {
            $example = $this->getAttributeType($attribute);
        }

        return $example;
    }

    /**
     * This function gets the other objects.
     *
     * @param array $xTagGroups
     * @param int   $key
     *
     * @return array
     */
    public function getOtherObjects(array $xTagGroups, int &$key): array
    {
        foreach ($xTagGroups as $key => $group) {
            if ($group['name'] == 'Overige objecten') {
                return $group;
            }
        }
        $key = null;

        return ['name' => 'Overige objecten'];
    }

    /**
     * This function returns the collection group tags.
     *
     * @param CollectionEntity $collection
     * @param string           $collectionName
     *
     * @return array
     */
    public function createCollectionGroupTags(CollectionEntity $collection, string $collectionName): array
    {
        $groupTags = [];
        foreach ($collection->getEntities() as $entity) {
            $groupTags[] = ucfirst($entity->getName().$collectionName);
        }

        return [
            'name' => $collection->getName(),
            'tags' => $groupTags,
        ];
    }

    /**
     * This function returns the tags.
     *
     * @param Handler  $handler
     * @param Endpoint $endpoint
     * @param string   $prefix
     *
     * @return array
     */
    public function createTags(Handler $handler, Endpoint $endpoint, string $prefix): array
    {
        return [
            'name'          => ucfirst($handler->getEntity()->getName().$prefix),
            'x-displayName' => ucfirst($handler->getEntity()->getName()),
            'description'   => (string) $endpoint->getDescription(),
        ];
    }

    /**
     * This function adds the groups and tags to the schema.
     *
     * @param Handler  $handler
     * @param Endpoint $endpoint
     * @param array    $docs
     *
     * @return array
     */
    public function addTagsToSchema(Handler $handler, Endpoint $endpoint, array &$docs): array
    {
        $collection = $handler->getEntity()->getCollections()[0] ?? null;
        if ($collection) {
            $collectionName = preg_replace('/\s+/', '-', $collection->getName());
            $tag = $this->createTags($handler, $endpoint, $collectionName);
            $group = $this->createCollectionGroupTags($collection, $collectionName);

            if (!in_array($group, $docs['x-tagGroups'])) {
                $docs['x-tagGroups'][] = $group;
            }
        } else {
            $tag = $this->createTags($handler, $endpoint, 'Overige-objecten');
            $group[] = ucfirst($handler->getEntity()->getName().'Overige-objecten');

            if (!in_array($group, $docs['x-tagGroups'])) {
                $key = 0;
                $groups = $this->getOtherObjects($docs['x-tagGroups'], $key);
                isset($groups['tags']) && in_array(ucfirst($handler->getEntity()->getName().'Overige-objecten'), $groups['tags']) ? null : $groups['tags'][] = ucfirst($handler->getEntity()->getName().'Overige-objecten');
                $docs['x-tagGroups'][$key ?? count($docs['x-tagGroups'])] = $groups;
            }
        }

        if (!in_array($tag, $docs['tags'])) {
            $docs['tags'][] = $tag;
        }

        return $docs;
    }

    /**
     * Generates filter parameters for extend.
     *
     * @param Entity $entity
     *
     * @return array
     */
    public function getExtendProperties(Entity $entity): array
    {
        $example = [];

        foreach ($entity->getAttributes() as $attribute) {
            if ($attribute->getObject()) {
                $example[$attribute->getName()] = true;
            }
        }

        return $example;
    }

    /**
     * This function returns the links array.
     *
     * @param array $schema
     * @param array $schemaProperties
     *
     * @return array
     */
    public function getLinks(array $schema, array $schemaProperties): array
    {
        // add key id to self
        foreach ($schemaProperties as $key => $value) {
            if ($key === 'id') {
                // change id to self
                $schema['properties']['self'] = $schemaProperties[$key];
            }
            unset($schemaProperties[$key]);
        }

        return $schema;
    }

    /**
     * This function adds the properties to metadata.
     *
     * @param array $schema
     * @param $items
     * @param $entity
     *
     * @return array
     */
    public function getMetaData(array $schema, $items, $entity): array
    {
        // @todo add example data for metadata

        // delete key __links
        foreach ($schema['properties'] as $key => $value) {
            if ($key === '__links') {
                unset($schema['properties'][$key]);
            }
        }

        // add items to metadata
        foreach ($items as $key => $value) {
            if ($key !== 'id') {
                $schema['properties']['__'.$key] = [
                    'type'    => 'string',
                    'title'   => 'The id of ',
                    'example' => $value,
                ];
            }
        }

        $schema['properties']['__extend'] = [
            'type'    => 'object',
            'title'   => 'The parameter extend',
            'example' => $this->getExtendProperties($entity),
        ];

        // unset __extend if there is no example
        $example = $schema['properties']['__extend']['example'];
        if (count($example) === 0) {
            unset($schema['properties']['__extend']);
        }

        return $schema;
    }

    /**
     * This function returns a json-hal schema.
     *
     * @param Entity $entity
     * @param array  $schema
     * @param array  $oldArray
     * @param array  $items
     *
     * @return array
     */
    public function getJsonHalSchema(Entity $entity, array $schema, array $oldArray, array $items): array
    {
        $schema['properties']['__links'] = $this->getLinks($schema, $oldArray);
        $schema['properties']['__metadata'] = $this->getMetaData($schema, $items, $entity);

        return $schema;
    }

    /**
     * This function changes the object to an uuid.
     *
     * @param array $oldArray
     *
     * @return array
     */
    public function changeObjects(array $oldArray): array
    {
        foreach ($oldArray as $key => $value) {
            if (key_exists('$ref', $value)) {
                unset($oldArray[$key]['$ref']);
                $oldArray[$key] = [
                    'type'    => 'string',
                    'format'  => 'uuid',
                    'title'   => 'The uuid of the '.$key,
                    'example' => 'uuid', //@todo here the uuid of the object
                ];
            }
        }

        return $oldArray;
    }

    /**
     * This function adds the properties to metadata.
     *
     * @param $attributes
     *
     * @return array
     */
    public function addPropertiesMetadata($attributes): array
    {
        $example = [];

        foreach ($attributes as $attribute) {
            // Add the attribute with type
            $example[$attribute->getName()] = $attribute->getType();
        }

        return $example;
    }

    /**
     * Generates metadata items.
     *
     * @param $items
     *
     * @return array
     */
    public function addEmbeddedMetadata($items): array
    {
        $example = [];

        // add items to metadata
        foreach ($items as $key => $value) {
            if ($key !== 'id') {
                $example['__'.$key] = 'string';
            }
        }

        return $example;
    }

    /**
     * Generates an OAS example (data) for an attribute.
     *
     * @param Attribute $attribute
     * @param array     $schema
     * @param $last_part
     * @param $value
     *
     * @return array
     */
    public function unsetObject(Attribute $attribute, array $schema, $last_part, $value): array
    {
        if ($last_part) {
            $parts = explode('.', $value);
            $firstPart = $parts[0];

            if ($attribute->getName() === $firstPart) {
                unset($schema['properties'][$firstPart]);
            }
        }

        return $schema;
    }

    /**
     * Generates an OAS example (data) for an attribute.
     *
     * @param Attribute $attribute
     * @param array     $mapping
     * @param array     $schema
     *
     * @return array
     */
    public function unsetProperties(Attribute $attribute, array $mapping, array $schema): array
    {
        // Let do mapping (changing of property names)
        foreach ($mapping as $key => $value) {

            // Get first and last part of the string
            $last_part = substr(strrchr($value, '.'), 1);

            $schema = $this->unsetObject($attribute, $schema, $last_part, $value);

            if ($attribute->getName() === $value) {
                unset($schema['properties'][$attribute->getName()]);
            }
        }

        return $schema;
    }

    /**
     * Generates embedded properties.
     *
     * @param $entity
     * @param $items
     *
     * @return array
     */
    public function addEmbeddedToBody($entity, $items): array
    {
        $examples = [];
        foreach ($entity->getAttributes() as $attribute) {
            if ($attribute->getObject()) {
                $properties = $this->addPropertiesMetadata($attribute->getObject()->getAttributes());
                $metadata = $this->addEmbeddedMetadata($items);
                $att = [
                    '__links' => [
                        'self' => 'uuid',
                    ],
                    '__metadata' => $metadata,
                ];
                $example = array_merge($att, $properties);

                $examples[] = [
                    $attribute->getObject()->getName() => $example,
                ];
            }
        }

        return $examples;
    }

    /**
     * This function returns a json-hal embedded array.
     *
     * @param Entity $entity
     * @param array  $items
     *
     * @return array
     */
    public function getJsonHalEmbeddedSchema(Entity $entity, array $items): array
    {
        $embedded['__embedded'] = [
            'type'    => 'object',
            'title'   => 'The parameter extend',
            'example' => $this->addEmbeddedToBody($entity, $items),
        ];

        // unset __embedded if there is no example
        $example = $embedded['__embedded']['example'];
        if (count($example) === 0) {
            unset($embedded['__embedded']);
        }

        return $embedded;
    }

    /**
     * This function returns a json-ld schema.
     *
     * @param array  $schema
     * @param array  $items
     * @param Entity $entity
     *
     * @return array
     */
    public function getJsonLdSchema(array $schema, array $items, Entity $entity): array
    {
        foreach ($items as $key => $value) {
            $schema['properties']['@'.$key] = [
                'type'    => 'string',
                'title'   => 'The id of ',
                'example' => $value,
            ];
        }

        $schema['properties']['@extend'] = [
            'type'    => 'object',
            'title'   => 'The parameter extend',
            'example' => $this->getExtendProperties($entity),
        ];

        // unset @extend if there is no example
        $example = $schema['properties']['@extend']['example'];
        if (count($example) === 0) {
            unset($schema['properties']['@extend']);
        }

        return $schema;
    }

    /**
     * Serializes a schema (array) to standard e.g. application/json.
     *
     * @param array  $schema
     * @param string $type
     * @param Entity $entity
     *
     * @return array
     */
    public function serializeSchema(array $schema, string $type, Entity $entity): array
    {
        $items = $this->basicSchemaSetup();
        $oldArray = $schema['properties'];
        $schema['properties'] = [];
        $embedded = [];

        switch ($type) {
            case 'application/json':
                break;
            case 'application/json+ld':
                $schema = $this->getJsonLdSchema($schema, $items, $entity);
                // @todo add embedded array
                break;
            case 'application/json+hal':
                $schema = $this->getJsonHalSchema($entity, $schema, $oldArray, $items);
                $embedded = $this->getJsonHalEmbeddedSchema($entity, $items);
                $oldArray = $this->changeObjects($oldArray);
                break;
            case 'application/json+orc':
                //
                $schema = [];
                break;
            case 'application/json+form.io':
                $schema = [];
                break;
            default:
                /* @todo throw error */
        }

        // add the schema properties to the array
        foreach ($oldArray as $key => $value) {
            $schema['properties'][$key] = $value;
        }
        $schema['properties'] = array_merge($schema['properties'], $embedded);

        return $schema;
    }

    /**
     * Generates an OAS schema from an entity.
     *
     * @param Attribute $attribute
     * @param array     $schema
     *
     * @return array
     */
    public function checkAttributeMultipleSchema(Attribute $attribute, array &$schema): array
    {
        if ($attribute->getMultiple()) {
            $schema['properties'][$attribute->getName()] = [
                'type'  => 'array',
                'items' => [
                    '$ref' => '#/components/schemas/'.ucfirst($this->toCamelCase($attribute->getObject()->getName())),
                ],
            ];
        } else {
            $schema['properties'][$attribute->getName()] = [
                '$ref' => '#/components/schemas/'.ucfirst($this->toCamelCase($attribute->getObject()->getName())),
            ];
        }

        return $schema;
    }

    /**
     * @param Entity     $entity
     * @param array      $mapping
     * @param array|null $docs
     *
     * @return array
     *
     * @todo Generates the mapping for the schema
     */
    public function getMappingSchema(Attribute $attribute, array $mapping, array $schema): array
    {
        // @todo fix mapping
//        if ($mapping) {
//            $newSchema = [];
//            $newSchema = $this->translationService->dotHydrator($newSchema, $schema['properties'], $mapping);
//            foreach ($newSchema as $key => $value) {
//                $newSchema[$key] = [
//                    'example' => 'string'
//                ];
//            }
//
//            #@todo here mapping
//            # object is still there -> request body
//            # object is not showing -> response body
//            $schema = $this->unsetProperties($attribute, $mapping, $schema);
//            $schema['properties'] = $newSchema;
//        } else {
//            // Else add schema
//            $schema['properties'][$attribute->getName()] = [
//                '$ref' => '#/components/schemas/' . ucfirst($this->toCamelCase($attribute->getObject()->getName()))
//            ];
//        }

        return $schema;
    }

    /**
     * Generates an OAS schema from an entity.
     *
     * @param Entity      $entity
     * @param array       $mapping
     * @param array|null  $docs
     * @param string|null $type
     *
     * @return array
     */
    public function getSchema(Entity $entity, array $mapping, ?array $docs, ?string $type): array
    {
        $schema = [
            'type'       => 'object',
            'required'   => [],
            'properties' => [],
        ];
        while (in_array($entity, $this->indirectEntities)) {
            unset($this->indirectEntities[array_search($entity, $this->indirectEntities)]);
        }

        foreach ($entity->getAttributes() as $attribute) {
            // Handle required fields
            if ($attribute->getRequired() and $attribute->getRequired() !== null) {
                $schema['required'][] = $attribute->getName();
            }

            // Add id to properties
            $schema['properties']['id'] = [
                'type'        => 'string',
                'format'      => 'uuid',
                'title'       => 'The id of '.$attribute->getName(),
                'description' => 'The uuid of the '.$attribute->getName(),
            ];

            // Add the attribute
            $schema['properties'][$attribute->getName()] = [
                'type'        => $attribute->getType(),
                'title'       => $attribute->getName(),
                'description' => $attribute->getDescription(),
            ];

//             The attribute might be a scheme on its own
            if ($attribute->getObject() && $attribute->getCascade()) {
                // @todo fix mapping
//                $schema = $this->getMappingSchema($attribute, $mapping, $schema);

                $schema = $this->checkAttributeMultipleSchema($attribute, $schema);

                if (!isset($docs['components']['schemas'][ucfirst($attribute->getObject()->getName())])) {
                    $this->indirectEntities[$attribute->getObject()->getName()] = $attribute->getObject();
                }

                continue;
            } elseif ($attribute->getObject() && !$attribute->getCascade() && $type == 'response') {
                $schema = $this->checkAttributeMultipleSchema($attribute, $schema);
                continue;
            } elseif ($attribute->getObject() && !$attribute->getCascade() && $type == 'request') {
                $schema['properties'][$attribute->getName()] = [
                    'type'        => 'string',
                    'format'      => 'uuid',
                    'description' => $schema['properties'][$attribute->getName()]['description'].'The uuid of the ['.$attribute->getObject()->getName().']() object that you want to link, you can unlink objects by setting this field to null',
                ];

                continue;
            }

            // Add the validators
            foreach ($attribute->getValidations() as $validator => $validation) {
                if (!array_key_exists($validator, OasDocumentationService::SUPPORTED_VALIDATORS) && $validation != null) {
                    $schema['properties'][$attribute->getName()][$validator] = $validation;
                }
            }

            // Set example data
            if ($attribute->getExample()) {
                $schema['properties'][$attribute->getName()]['example'] = $attribute->getExample();
            } else {
                $schema['properties'][$attribute->getName()]['example'] = $this->generateAttributeExample($attribute);
            }

//            # @todo fix mapping
//            $schema = $this->getMappingSchema($attribute, $mapping, $schema);
        }

        return $schema;
    }

    /**
     * This function returns the serialized response schema.
     *
     * @param Handler $handler
     * @param $responseType
     *
     * @return array
     */
    public function getResponseSchema(Handler $handler, $responseType): array
    {
        $schema = $this->getSchema($handler->getEntity(), $handler->getMappingOut(), null, 'response');

        return $this->serializeSchema($schema, $responseType, $handler->getEntity());
    }

    /**
     * This function returns the serialized request schema.
     *
     * @param Handler $handler
     * @param string  $requestType
     *
     * @return array
     */
    public function getRequestSchema(Handler $handler, string $requestType): array
    {
        $schema = $this->getSchema($handler->getEntity(), $handler->getMappingIn(), null, 'request');

        return $this->serializeSchema($schema, $requestType, $handler->getEntity());
    }

    /**
     * This function returns the response.
     *
     * @param Handler $handler
     * @param array   $methodArray
     * @param string  $method
     *
     * @return array
     */
    public function getResponse(Handler $handler, array $methodArray, string $method): array
    {
        if ($response = $this->getResponseType($method)) {
            $methodArray['responses'][$response['statusCode']] = [
                'description' => $response['description'],
                'content'     => [],
            ];

//          $responseTypes = ["application/json","application/json-ld","application/json-hal","application/xml","application/yaml","text/csv"];
            $responseTypes = ['application/json', 'application/json+ld', 'application/json+hal']; // @todo this is a short cut, lets focus on json first */
            foreach ($responseTypes as $responseType) {
                $method !== 'delete' ? $schema = $this->getResponseSchema($handler, $responseType) : $schema = null;
                $methodArray['responses'][$response['statusCode']]['content'][$responseType]['schema'] = $schema;
            }
        }

        return $methodArray;
    }

    /**
     * This function returns the request.
     *
     * @param Handler $handler
     * @param array   $methodArray
     * @param string  $method
     *
     * @return array
     */
    public function getRequest(Handler $handler, array $methodArray, string $method): array
    {
        //        $requestTypes = ["application/json","application/xml","application/yaml"];
        $requestTypes = ['application/json']; // @todo this is a short cut, lets focus on json first */
        if (in_array($method, ['patch', 'put', 'post'])) {
            foreach ($requestTypes as $requestType) {
                $schema = $this->getRequestSchema($handler, $requestType);
                $methodArray['requestBody']['content'][$requestType]['schema'] = $schema;
                $methodArray['responses'][400]['content'][$requestType]['schema'] = $schema;
            }
        }

        return $methodArray;
    }

    /**
     * Generates the filter parameters of an entity.
     *
     * @param Entity $Entity
     *
     * @return array
     */
    public function getExtendFilterParameters(Entity $Entity): array
    {
        $parameters = [];

        foreach ($Entity->getAttributes() as $attribute) {
            if ($attribute->getObject()) {
                $parameters[] = [
                    'name'        => 'extend[] for '.$attribute->getObject()->getName(),
                    'in'          => 'query',
                    'description' => 'The object you want to extend',
                    'required'    => $attribute->getRequired(),
                    'style'       => 'simple',
                    'schema'      => [
                        'default' => $attribute->getObject()->getName(),
                        'type'    => 'string',
                    ],
                ];
            }
        }

        return $parameters;
    }

    /**
     * Generates the filter parameters of an entity.
     *
     * @param Entity $Entity
     * @param string $prefix
     * @param int    $level
     *
     * @return array
     */
    public function getFilterParameters(Entity $Entity, string $prefix = '', int $level = 1): array
    {
        $parameters = [];
        foreach ($Entity->getAttributes() as $attribute) {
            if (in_array($attribute->getType(), ['string', 'date', 'datetime']) and $attribute->getSearchable()) {
                $parameters[] = [
                    'name'        => $prefix.$attribute->getName(),
                    'in'          => 'query',
                    'description' => 'Search '.$prefix.$attribute->getName().' on an exact match of the string',
                    'required'    => $attribute->getRequired(),
                    'style'       => 'simple',
                    'schema'      => [
                        'type'     => $attribute->getType() ?? 'string',
                        'required' => $attribute->getRequired(),
                    ],
                ];
            }

            if ($attribute->getObject() && $level < 3) {
                $parameters = array_merge($parameters, $this->getFilterParameters($attribute->getObject(), $prefix.$attribute->getName().'.', $level + 1));
            }
        }

        return $parameters;
    }

    /**
     * Get standard query parameters.
     *
     * @return array
     */
    public function getPaginationParameters(): array
    {
        $parameters = [];
        $parameters[] = [
            'name'        => 'start',
            'in'          => 'query',
            'description' => 'The start number or offset of you list',
            'required'    => false,
            'style'       => 'simple',
            'schema'      => [
                'type' => 'string',
            ],
        ];
        $parameters[] = [
            'name'        => 'limit',
            'in'          => 'query',
            'description' => 'the total items pe list/page that you want returned',
            'required'    => false,
            'style'       => 'simple',
            'schema'      => [
                'type' => 'string',
            ],
        ];
        $parameters[] = [
            'name'        => 'page',
            'in'          => 'query',
            'description' => 'The page that you want returned',
            'required'    => false,
            'style'       => 'simple',
            'schema'      => [
                'type' => 'string',
            ],
        ];

        return $parameters;
    }

    /**
     * Returns the three parameter functions as one array.
     *
     * @param Handler $handler
     *
     * @return array
     */
    public function getParameters(Handler $handler): array
    {
        return array_merge($this->getPaginationParameters(), $this->getFilterParameters($handler->getEntity()), $this->getExtendFilterParameters($handler->getEntity()));
    }

    /**
     * This function returns a summary.
     *
     * @param string  $method
     * @param Handler $handler
     * @param string  $path
     *
     * @return array
     */
    public function getMethodSummary(string $method, Handler $handler, string $path): array
    {
        $pathArray = explode('/', $path);
        switch ($method) {
            case 'get':
                $methodName = [
                    'summary'   => 'Get a list of '.strtolower($path),
                    'summaryId' => 'Get a single '.strtolower($handler->getEntity()->getName()),
                ];
                break;
            case 'post':
                $methodName = [
                    'summary'   => 'Create a '.strtolower($handler->getEntity()->getName()),
                    'summaryId' => 'Create a '.strtolower($handler->getEntity()->getName()),
                ];
                break;
            case 'put':
                $methodName = [
                    'summary'   => null,
                    'summaryId' => 'Replace a '.strtolower($handler->getEntity()->getName()),
                ];
                break;
            case 'patch':
                $methodName = [
                    'summary'   => 'Update a '.strtolower($handler->getEntity()->getName()),
                    'summaryId' => 'Update a '.strtolower($handler->getEntity()->getName()),
                ];
                break;
            case 'delete':
                $methodName = [
                    'summary'   => null,
                    'summaryId' => 'Delete a '.strtolower($pathArray[0]).' '.strtolower($handler->getEntity()->getName()),
                ];
                break;
        }

        return $methodName ?? [
            'summary'   => 'Unrecognized method for '.strtolower($handler->getEntity()->getName()),
            'summaryId' => 'Unrecognized method for '.strtolower($handler->getEntity()->getName()),
        ];
    }

    /**
     * This function returns the method array.
     *
     * @param string  $method
     * @param Handler $handler
     * @param string  $path
     *
     * @return array
     */
    public function getMethodArray(string $method, Handler $handler, string $path): array
    {
        $methodSummary = $this->getMethodSummary($method, $handler, $path);
        $collection = $handler->getEntity()->getCollections()[0] ?? null;

        $collectionName = null;
        if ($collection) {
            $collectionName = preg_replace('/\s+/', '-', $collection->getName());
        }

        return [
            'description' => $handler->getEntity()->getDescription(),
            'operationId' => str_contains($path, '{') ?
                strtolower($path).'_'.$handler->getEntity()->getName().'_'.$method.'Id' :
                strtolower($path).'_'.$handler->getEntity()->getName().'_'.$method,
            'tags'       => [$collectionName ? ucfirst($handler->getEntity()->getName().$collectionName) : ucfirst($handler->getEntity()->getName().'Overige-objecten')],
            'summary'    => str_contains($path, '{') ? $methodSummary['summaryId'] : $methodSummary['summary'],
            'parameters' => [],
            'responses'  => [],
        ];
    }

    /**
     * This function returns the endpoint method.
     *
     * @param string  $method
     * @param Handler $handler
     * @param string  $path
     *
     * @return array
     */
    public function getEndpointMethod(string $method, Handler $handler, string $path): array
    {
        $methodArray = $this->getMethodArray($method, $handler, $path);
        $method !== 'delete' && $methodArray['parameters'] = $this->getParameters($handler);
        $methodArray = $this->getResponse($handler, $methodArray, $method);

        return $this->getRequest($handler, $methodArray, $method);
    }

    /**
     * Generates an OAS3 path for a specific endpoint.
     *
     * @param Endpoint $endpoint
     * @param array    $docs
     *
     * @return array
     */
    public function addEndpointToDocs(Endpoint $endpoint, array &$docs): array
    {
        $method = strtolower($endpoint->getMethod());
        $handler = $this->handlerService->getHandler($endpoint, $method);

        if (!$handler) {
            return $docs;
        }

        if (!$handler->getEntity()) {
            return $docs;
        }

        if (!$endpoint->getPath()) {
            return $docs;
        }
        $paths = $endpoint->getPath();
        $path = implode('/', $paths);

        // Add the paths
        $docs['paths']['/api/'.$path][$method] = $this->getEndpointMethod($method, $handler, $path);
        $docs['components']['schemas'][ucfirst($handler->getEntity()->getName())] = $this->getSchema($handler->getEntity(), $handler->getMappingOut(), $docs, 'response');
        $this->addTagsToSchema($handler, $endpoint, $docs);

        return $docs;
    }

    /**
     * Returns info for the getRenderDocumentation function.
     *
     * @param Application|null $application
     *
     * @return array
     */
    private function getDocumentationInfo(?Application $application): array
    {
        return [
            'title'          => $application ? $application->getName() : $this->params->get('documentation_title'),
            'description'    => $application ? $application->getDescription() : $this->params->get('documentation_description'),
            'termsOfService' => $this->params->get('documentation_terms_of_service'),
            'contact'        => [
                'name'  => $this->params->get('documentation_contact_name'),
                'url'   => $this->params->get('documentation_contact_url'),
                'email' => $this->params->get('documentation_contact_email'),
            ],
            'license' => [
                'name' => $this->params->get('documentation_licence_name'),
                'url'  => $this->params->get('documentation_licence_url'),
            ],
            'version' => $this->params->get('documentation_version'),
        ];
    }

    /**
     * Places a schema.yaml and schema.json in the /public/eav folder for use by redoc and swagger.
     *
     * @return bool returns true if successfully or false on failure
     */
    public function write(array $docs): bool
    {
        // Setup the file system
        $filesystem = new Filesystem();

        // Check if there is a eav folder in the /public folder
        if (!$filesystem->exists('public/eav')) {
            $filesystem->mkdir('public/eav');
        }

        $filesystem->dumpFile('public/eav/schema.json', json_encode($docs, JSON_UNESCAPED_SLASHES));
        $filesystem->dumpFile('public/eav/schema.yaml', Yaml::dump($docs));

        return true;
    }

    /**
     * Generates an OAS3 documentation for the exposed eav entities in the form of an array.
     *
     * @param ?string|null $applicationId
     *
     * @return array
     */
    public function getRenderDocumentation(?string $applicationId): array
    {
        $docs = [];

        // General info
        $docs['openapi'] = '3.0.3';
        $docs['tags'] = [];
        $docs['x-tagGroups'] = [];
        $docs['servers'] = [
            ['url' => $this->params->get('app_url'), 'description' => 'Gateway server'],
        ];

        $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $applicationId]); ///findBy(['expose_in_docs'=>true]);
        if ($application !== null) {
            $endpoints = $this->em->getRepository('App:Endpoint')->findByApplication($application); ///findBy(['expose_in_docs'=>true]);
        } else {
            $endpoints = $this->em->getRepository('App:Endpoint')->findAll(); ///findBy(['expose_in_docs'=>true]);
        }

        $docs['info'] = $this->getDocumentationInfo($application);

        foreach ($endpoints as $endpoint) {
            $docs = $this->addEndpointToDocs($endpoint, $docs);
        }

        while (count($this->indirectEntities) > 0) {
            $entity = array_pop($this->indirectEntities);
            $docs['components']['schemas'][ucfirst($entity->getName())] = $this->getSchema($entity, [], $docs, 'response');
        }

        return $docs;
    }

    /**
     * Generates an OAS3 documentation for the exposed eav entities in the form of an array.
     *
     * @param string|null $applicationId
     *
     * @return bool
     */
    public function writeRedoc(?string $applicationId): bool
    {
        $docs = $this->getRenderDocumentation($applicationId);

        return $this->write($docs);
    }
}
