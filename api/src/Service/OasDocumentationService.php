<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Handler;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class OasDocumentationService
{
    private ParameterBagInterface $params;
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private ValidationService $validationService;
    private array $supportedValidators;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, CommonGroundService $commonGroundService, ValidationService $validationService)
    {
        $this->params = $params;
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->validationService = $validationService;

        // Let's define the validator that we support for documentation right now
        $this->supportedValidators = $this->supportedValidators();

        // Let's define the validator that we support for documentation right now
        $this->supportedTypes = [
            'string',
            'date',
            'datetime',
            'integer',
            'array',
            'boolean',
            'float',
            'number',
            'file',
            'object'
        ];
    }

    private function supportedValidators(): array
    {
        return [
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
    }

    /**
     * Places an schema.yaml and schema.json in the /public/eav folder for use by redoc and swagger.
     *
     * @return bool returns true if succcesfull or false on failure
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
     * @param string $applicationId
     * @return array
     */
    public function getRenderDocumentation(string $applicationId): array
    {
        $docs = [];

        // General info
        $docs['openapi'] = '3.0.3';
        $docs['info'] = $this->getDocumentationInfo();
        $docs['tags'] = [];
        /* @todo the server should include the base url */
        $docs['servers'] = [
            ['url' => "localhost:", 'description' => 'Gateway server'],
        ];
//        $host = $request->server->get('HTTP_HOST');
//        $path = $request->getPathInfo();

//        $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $applicationId]); ///findBy(['expose_in_docs'=>true]);
//        $endpoints = $this->em->getRepository('App:Endpoint')->findByApplication($application); ///findBy(['expose_in_docs'=>true]);
        $endpoints = $this->em->getRepository('App:Endpoint')->findAll(); ///findBy(['expose_in_docs'=>true]);

        foreach ($endpoints as $endpoint) {
            $docs = $this->addEndpointToDocs($endpoint, $docs);
        }

        $this->write($docs);

        return $docs;
    }

    /**
     * Returns info for the getRenderDocumentation function.
     *
     * @return array
     */
    private function getDocumentationInfo(): array
    {
        return [
            'title' => $this->params->get('documentation_title'),
            'description' => $this->params->get('documentation_description'),
            'termsOfService' => $this->params->get('documentation_terms_of_service'),
            'contact' => [
                'name' => $this->params->get('documentation_contact_name'),
                'url' => $this->params->get('documentation_contact_url'),
                'email' => $this->params->get('documentation_contact_email'),
            ],
            'license' => [
                'name' => $this->params->get('documentation_licence_name'),
                'url' => $this->params->get('documentation_licence_url'),
            ],
            'version' => $this->params->get('documentation_version'),
        ];
    }

    /**
     * Generates an OAS3 path for a specific endpoint.
     *
     * @param Endpoint $endpoint
     * @param array $docs
     *
     * @return array
     */
    public function addEndpointToDocs(Endpoint $endpoint, array &$docs): array
    {
        // Let's only add the main entities as root
        if (!$endpoint->getPath()) {
            return $docs;
        }

        // Get method and handler
        $method = strtolower($endpoint->getMethod());
        $handler = $this->getHandler($endpoint, $method);

        // If there is no handler return docs
        if (!$handler) {
            return $docs;
        }

        // Get path and loop through the array
        $paths = $endpoint->getPath();

        foreach ($paths as $path) {
            // Paths -> entity route / {id}
            if ($path == '{id}') {
                $docs['paths'][$handler->getEntity()->getRoute() . '/' . $path][$method] = $this->getEndpointMethod($method, $handler, true);
            }

            // Paths -> entity route
            if (in_array($method, ['get', 'post'])) {
                $docs['paths'][$handler->getEntity()->getRoute()][$method] = $this->getEndpointMethod($method, $handler, false);
            }
        }

        // components -> schemas
        $docs['components']['schemas'][ucfirst($handler->getEntity()->getName())] = $this->getSchema($handler->getEntity(), $handler->getMappingIn(), $handler->getMappingOut());

        // @todo remove duplicates from array
        // Tags
        $docs['tags'][] = [
            'name' => ucfirst($handler->getEntity()->getName()),
            'description' => $endpoint->getDescription(),
        ];

        return $docs;
    }

    /**
     * Gets an OAS description for a specific method
     *
     * @param string $method
     * @param Handler $handler
     * @param bool $item
     * @return array
     */
    public function getEndpointMethod(string $method, Handler $handler, bool $item): array
    {
        /* @todo name should be cleaned before being used like this */
        $methodArray = [
            "description" => $handler->getEntity()->getDescription(),
            "operationId" => $item ? $handler->getEntity()->getName() . '_' . $method . 'Id' : $handler->getEntity()->getName() . '_' . $method,
            "tags" => [ucfirst($handler->getEntity()->getName())],
            "summary" => $handler->getEntity()->getDescription(),
            "parameters" => [],
            "responses" => [],
        ];

        // Parameters
        $methodArray['parameters'] = $this->getParameters($handler);

        // Primary Response (success)
        // get the response type -> returns statusCode and description
        $response = $this->getResponseType($method);
        if ($response) {
            $methodArray['responses'][$response['statusCode']] = [
                'description' => $response['description'],
                'content' => []
            ];

//          $responseTypes = ["application/json","application/json-ld","application/json-hal","application/xml","application/yaml","text/csv"];
            $responseTypes = ["application/json", "application/json+ld", "application/json+hal"]; // @todo this is a short cut, lets focus on json first */
            foreach ($responseTypes as $responseType) {
                $schema = $this->getResponseSchema($handler, $responseType);
                $methodArray['responses'][$response['statusCode']]['content'][$responseType]['schema'] = $schema;
            }
        }

        // Let see is we need request bodies
//        $requestTypes = ["application/json","application/xml","application/yaml"];
        $requestTypes = ["application/json"]; // @todo this is a short cut, lets focus on json first */
        if (in_array($method, ['put', 'post'])) {
            foreach ($requestTypes as $requestType) {
                $schema = $this->getRequestSchema($handler, $requestType);
                $methodArray['requestBody']['content'][$requestType]['schema'] = $schema;
                $methodArray['responses'][400]['content'][$requestType]['schema'] = $schema;
            }
        }
        return $methodArray;
    }

    /**
     * Gets a handler for an endpoint method combination
     *
     * @param Endpoint $endpoint
     * @param string $method
     *
     * @return Handler|boolean
     * @todo i would expect this function to live in the handlerService
     *
     */
    public function getHandler(Endpoint $endpoint, string $method)
    {
        foreach ($endpoint->getHandlers() as $handler) {
            if (in_array('*', $handler->getMethods())) {
                return $handler;
            }

            // Check if handler should be used for this method
            if (in_array($method, $handler->getMethods())) {
                return $handler;
            }
        }
        return false;
    }

    /**
     * Gets the response type from the method
     *
     * @param string $method
     * @return array|bool
     */
    public function getResponseType(string $method)
    {
        $response = false;
        switch ($method) {
            case 'get':
                $response = [
                    'statusCode' => 200,
                    'description' => 'OK'
                ];
                break;
            case 'post':
                $response = [
                    'statusCode' => 201,
                    'description' => 'Created'
                ];
                break;
            case 'put':
                $response = [
                    'statusCode' => 202,
                    'description' => 'Accepted'
                ];
                break;
        }
        return $response;
    }

    /**
     * Gets an OAS description for a specific method
     *
     * @param Handler $handler
     * @param $responseType
     * @return array
     */
    public function getResponseSchema(Handler $handler, $responseType): array
    {
        $schema = $this->getSchema($handler->getEntity(), $handler->getMappingIn(), $handler->getMappingOut());
        return $this->serializeSchema($schema, $responseType, $handler->getEntity());
    }

    /**
     * Gets an OAS description for a specific method
     *
     * @param Handler $handler
     * @param string $requestType
     * @return array
     */
    public function getRequestSchema(Handler $handler, string $requestType): array
    {
        $schema = $this->getSchema($handler->getEntity(), $handler->getMappingIn(), null);
        return $this->serializeSchema($schema, $requestType, $handler->getEntity());
    }

    /**
     * Serializes a schema (array) to standard e.g. application/json
     *
     * @param array $schema
     * @param string $type
     * @param Entity $entity
     * @return array
     */
    public function serializeSchema(array $schema, string $type, Entity $entity): array
    {
        // Basic schema setup
        $items = ['id', 'type', 'context', 'dateCreated', 'dateCreated',
            'dateModified', 'owner', 'organization', 'application', 'uri', 'gateway/id'];

        // add schema properties to array
        // unset schema properties
        $oldArray = $schema['properties'];
        $schema['properties'] = [];
        $embedded = [];

        // switch type to add attributes  */
        switch ($type) {
            case "application/json":
                break;
            case "application/json+ld":
                $schema = $this->getProperties($schema, $items, $entity);
                foreach ($entity->getAttributes() as $attribute) {
                    if ($attribute->getObject()) {
                        $embedded[$attribute->getObject()->getName()] = [
                            'type' => 'object',
                            'title' => 'The parameter extend',
                            'example' => $this->addPropertiesMetadata($attribute->getObject()->getAttributes())
                        ];
                    }
                }
                break;
            case "application/json+hal":
                $schema['properties']['__links'] = $this->getLinks($schema, $oldArray);
                $schema['properties']['__metadata'] = $this->getMetaData($schema, $items, $entity);
                $embedded['__embedded'] = [
                    'type' => 'object',
                    'title' => 'The parameter extend',
                    'example' => $this->addEmbeddedToBody($entity, $items)
                ];

                // unset __embedded if there is no example
                $example = $embedded['__embedded']['example'];
                if (count($example) === 0) {
                    unset($embedded['__embedded']);
                }
                break;
            case "application/json+orc":
                //
                $schema = [];
                break;
            case "application/json+form.io":
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
     * Generates the attribute objects as name and type
     *
     * @param $attributes
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
     * Generates metadata items
     *
     * @param $items
     * @return array
     */
    public function addEmbeddedMetadata($items): array
    {
        $example = [];

        // add items to metadata
        foreach ($items as $item) {
            if ($item !== 'id') {
                $example['__' . $item] = 'string';
            }
        }
        return $example;
    }

    /**
     * Generates embedded properties
     *
     * @param $entity
     * @param $items
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
                        'self' => 'uuid'
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
     * Generates filter parameters for extend
     *
     * @param Entity $entity
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
     * Generates an OAS schema from an entity
     *
     * @param array $schema
     * @param array $items
     * @param Entity $entity
     * @return array
     */
    public function getProperties(array $schema, array $items, Entity $entity): array
    {
        foreach ($items as $item) {
            $schema['properties']['@' . $item] = [
                'type' => 'string',
                'title' => 'The id of ',
            ];
        }

        $schema['properties']['@extend'] = [
            'type' => 'object',
            'title' => 'The parameter extend',
            'example' => $this->getExtendProperties($entity)
        ];

        // unset @extend if there is no example
        $example = $schema['properties']['@extend']['example'];
        if (count($example) === 0) {
            unset($schema['properties']['@extend']);
        }

        return $schema;
    }

    /**
     * Generates an OAS schema from an entity
     *
     * @param array $schema
     * @param $items
     * @param $entity
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
        foreach ($items as $item) {
            if ($item !== 'id') {
                $schema['properties']['__' . $item] = [
                    'type' => 'string',
                    'title' => 'The id of ',
                ];
            }
        }

        $schema['properties']['__extend'] = [
            'type' => 'object',
            'title' => 'The parameter extend',
            'example' => $this->getExtendProperties($entity)
        ];

        // unset __extend if there is no example
        $example = $schema['properties']['__extend']['example'];
        if (count($example) === 0) {
            unset($schema['properties']['__extend']);
        }

        return $schema;
    }

    /**
     * Generates an OAS schema from an entity
     *
     * @param array $schema
     * @param array $schemaProperties
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
     * Generates an OAS schema from an entity
     *
     * @param Entity $entity
     * @param array $mappingIn
     * @param array|null $mappingOut
     * @return array
     */
    public function getSchema(Entity $entity, array $mappingIn, ?array $mappingOut): array
    {
        $schema = [
            'type' => 'object',
            'required' => [],
            'properties' => [],
        ];

        foreach ($entity->getAttributes() as $attribute) {
            // Handle required fields
            if ($attribute->getRequired() and $attribute->getRequired() !== null) {
                $schema['required'][] = ucfirst($attribute->getName());
            }

            // Add id to properties
            $schema['properties']['id'] = [
                'type' => 'string',
                'format' => 'uuid',
                'title' => 'The id of ' . $attribute->getName(),
                'description' => 'The uuid of the ' . $attribute->getName(),
            ];

            // Add the attribute
            $schema['properties'][$attribute->getName()] = [
                'type' => $attribute->getType(),
                'title' => $attribute->getName(),
                'description' => $attribute->getDescription(),
            ];

            // The attribute might be a scheme on its own
            if ($attribute->getObject() && $attribute->getCascade()) {

                // If mappingIn then change property name
                if ($mappingIn) {
                    $schema = $this->generateMappingIn($attribute, $mappingIn, $schema);

                    if ($mappingOut) {
                        $schema = $this->generateMappingOut($mappingIn, $mappingOut, $schema);
                    }
                } else {
                    // Else add schema
                    $schema['properties'][$attribute->getName()] = [
                        '$ref' => '#/components/schemas/' . ucfirst($this->toCamelCase($attribute->getObject()->getName()))
                    ];
                }

                // Schema's dont have validators so
                continue;
            } elseif ($attribute->getObject() && !$attribute->getCascade()) {
                $schema['properties'][$attribute->getName()] = [
                    'type' => 'string',
                    'format' => 'uuid',
                    'description' => $schema['properties'][$attribute->getName()]['description'] . 'The uuid of the [' . $attribute->getObject()->getName() . ']() object that you want to link, you can unlink objects by setting this field to null',
                ];
                // uuids dont have validators so
                continue;
            }

            // Add the validators
            foreach ($attribute->getValidations() as $validator => $validation) {
                if (!array_key_exists($validator, $this->supportedValidators) && $validation != null) {
                    $schema['properties'][$attribute->getName()] = [
                        $validator => $validation
                    ];
                }
            }

            // Set example data
            if ($attribute->getExample()) {
                $schema['properties'][$attribute->getName()] = [
                    'example' => $attribute->getExample()
                ];
            } else {
                $schema['properties'][$attribute->getName()] = [
                    'example' => $this->generateAttributeExample($attribute)
                ];
            }

            $schema = $this->generateMappingIn($attribute, $mappingIn, $schema);

            if ($mappingOut) {
                $schema = $this->generateMappingOut($mappingIn, $mappingOut, $schema);
            }
        }
        return $schema;
    }

    /**
     * Generates an OAS example (data) for an attribute
     *
     * @param Attribute $attribute
     * @param array $mappingIn
     * @param array $schema
     * @return array
     */
    public function generateMappingIn(Attribute $attribute, array $mappingIn, array $schema): array
    {
        // Let do mapping (changing of property names)
        foreach ($mappingIn as $key => $value) {

            // Get first and last part of the string
            $last_part = substr(strrchr($value, "."), 1);

            if ($last_part) {
                $parts = explode('.', $value);
                $firstPart = $parts[0];

                if ($attribute->getName() === $firstPart) {
                    unset($schema['properties'][$firstPart]);
                    $schema['properties'][$key]['example'] = $value;
                }
            }

            if ($attribute->getName() === $value) {
                $schema['properties'][$key] = $schema['properties'][$attribute->getName()];
                $schema['properties'][$key]['example'] = $value;
                unset($schema['properties'][$attribute->getName()]);
            }
        }
        return $schema;
    }

    /**
     * Generates an OAS example (data) for an attribute
     *
     * @param array $mappingIn
     * @param array $mappingOut
     * @param array $schema
     * @return array
     */
    public function generateMappingOut(array $mappingIn, array $mappingOut, array $schema): array
    {
        // Setup a mappingIn array
        $mappingArray = [];
        foreach ($mappingIn as $key => $value) {
            $mappingArray[] = $key;
        }

        // Let do mapping (changing of property names)
        foreach ($mappingOut as $key => $value) {
            if (in_array($value, $mappingArray)) {
                unset($schema['properties'][$value]);
                $schema['properties'][$key]['example'] = $value;
            }
        }
        return $schema;
    }


    /**
     * Generates attribute examples for format and type
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
     * Generates an attribute example for type
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
            case "string":
                if ($attribute->getEnum()) {
                    $example = $attribute->getEnum();
                } else {
                    $example = 'string';
                }
                break;
            case "date":
                $example = date("d-m-Y");
                break;
            case "datetime":
                $example = new DateTime();
                break;
            case "integer":
                $example = 1;
                break;
            case "array":
                $example = ['string', 'string'];
                break;
            case "boolean":
                $example = true;
                break;
            case "float":
                $example = 0.000;
                break;
            case "number":
                $example = 175;
                break;
            case "file":
                if ($attribute->getFileTypes()) {
                    $example = $attribute->getFileTypes();
                } else {
                    $example = 'example.pdf';
                }
                break;
            case "object":
                break;
            default:
                $example = 'string';
        }

        return $example;
    }

    /**
     * Generates an attribute example for format
     *
     * @param Attribute $attribute
     *
     * @return ?string
     */
    public function getAttributeFormat(Attribute $attribute): ?string
    {
        $example = 'string';
        // switch format to add example data to attributes  */
        switch ($attribute->getFormat()) {
            case "countryCode":
                $example = 'NL';
                break;
            case "bsn":
                $example = '9999999990';
                break;
            case "url":
                $example = 'www.example.nl';
                break;
            case "uri":
                $example = '/api/example/94e8bb2c-e66b-11ec-8fea-0242ac120002';
                break;
            case "uuid":
                $example = '94e8bb2c-e66b-11ec-8fea-0242ac120002';
                break;
            case "email":
                $example = 'example@hotmail.com';
                break;
            case "phone":
                $example = '0612345678';
                break;
            case "json":
                $example = [
                    "string" => "string",
                    "string1" => "string1"
                ];
                $example = json_encode($example);
                break;
            case "dutch_pc4":
                $example = '1217';
                break;
            default:
                $example = 'string';
        }

        return $example;
    }

    /**
     * Returns the three parameter functions as one array
     *
     * @param Handler $handler
     * @return array
     */
    public function getParameters(Handler $handler): array
    {
        return array_merge($this->getPaginationParameters(), $this->getFilterParameters($handler->getEntity()), $this->getExtendFilterParameters($handler->getEntity()));
    }

    /**
     * Get standard query parameters
     *
     * @return array
     */
    public function getPaginationParameters(): array
    {
        $parameters = [];
        $parameters[] = [
            'name' => 'start',
            'in' => 'query',
            'description' => 'The start number or offset of you list',
            'required' => false,
            'style' => 'simple',
        ];
        $parameters[] = [
            'name' => 'limit',
            'in' => 'query',
            'description' => 'the total items pe list/page that you want returned',
            'required' => false,
            'style' => 'simple',
        ];
        $parameters[] = [
            'name' => 'page',
            'in' => 'query',
            'description' => 'The page that you want returned',
            'required' => false,
            'style' => 'simple',
        ];
        return $parameters;
    }

    /**
     * Generates the filter parameters of an entity
     *
     * @param Entity $Entity
     * @return array
     */
    public function getExtendFilterParameters(Entity $Entity): array
    {
        $parameters = [];

        foreach ($Entity->getAttributes() as $attribute) {
            if ($attribute->getObject()) {
                $parameters[] = [
                    'name' => 'extend[] for ' . $attribute->getObject()->getName(),
                    'in' => 'query',
                    'description' => 'The object you want to extend',
                    'required' => false,
                    'style' => 'simple',
                    'schema' => [
                        'default' => $attribute->getObject()->getName()
                    ],
                ];
            }
        }

        return $parameters;
    }

    /**
     * Generates the filter parameters of an entity
     *
     * @param Entity $Entity
     * @param string $prefix
     * @param int $level
     * @return array
     */
    public function getFilterParameters(Entity $Entity, string $prefix = '', int $level = 1): array
    {
        $parameters = [];

        // @todo searchable is set to false
        foreach ($Entity->getAttributes() as $attribute) {
            if (in_array($attribute->getType(), ['string', 'date', 'datetime'])) {
                $parameters[] = [
                    'name' => $prefix . $attribute->getName(),
                    'in' => 'query',
                    'description' => 'Search ' . $prefix . $attribute->getName() . ' on an exact match of the string',
                    'required' => false,
                    'style' => 'simple',
                ];
            }

            if ($attribute->getObject() && $level < 5) {
                $parameters = array_merge($parameters, $this->getFilterParameters($attribute->getObject(), $attribute->getName() . '.', $level + 1));
            }
        }
        return $parameters;
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
        return lcfirst(str_replace(' ', '', ucwords(preg_replace('/^a-z0-9' . implode('', $dontStrip) . ']+/', ' ', $string))));
    }

}
