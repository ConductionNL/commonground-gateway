<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\CollectionEntity;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\Handler;
use App\Entity\Property;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * This service takes an external OpenAPI v3 specification and turns that into a gateway + eav structure.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class OasParserService
{
    private EntityManagerInterface $entityManager;
    private Client $client;

    private array $handlersToCreate;
    private array $oas;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->client = new Client([]);

        $this->handlersToCreate = [];
        $this->oas = [];

        $this->schemaRefs = [];
    }

    /**
     * Checks if an array is associative.
     *
     * @param array $array The array to check
     *
     * @return bool Whether or not the array is associative
     */
    private function isAssociative(array $array): bool
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Checks the type of data the response contains, and parses it accordingly into an array.
     *
     * @param Response $response The response of the request made to find the data
     * @param string   $url      The url the request was made to
     *
     * @return array The resulting OpenAPI Specification as array
     */
    private function getDataFromResponse(Response $response, string $url): array
    {
        if ($response->hasHeader('Content-Type')) {
            switch ($response->getHeader('Content-Type')) {
                case 'application/json':
                    return json_decode($response->getBody()->getContents(), true);
                case 'application/x-yaml':
                    return Yaml::parse($response->getBody()->getContents());
            }
        }
        if (strpos($url, '.json') !== false) {
            return json_decode($response->getBody()->getContents(), true);
        } else {
            return Yaml::parse($response->getBody()->getContents());
        }
    }

    /**
     * Retrieves an external OpenAPI Specification.
     *
     * @param string $url The URL of the external OAS
     *
     * @return array The parsed OAS
     */
    private function getExternalOAS(string $url): array
    {
        $response = $this->client->get($url);

        return $this->getDataFromResponse($response, $url);
    }

    /**
     * Parse the content description of a response for which no application/json version exists.
     *
     * @param array $response The response defined in the OAS
     *
     * @return string|null The entity the content belongs to
     */
    private function parseFirstContent(array $response): ?string
    {
        foreach ($response['content'] as $key => $content) {
            if (isset($content['schema']['$ref']) && $key !== 'application/problem+json') {
                $entityNameToLinkTo = substr($content['schema']['$ref'], strrpos($content['schema']['$ref'], '/') + 1);
                isset($this->replaceHalWithJsonEntity($entityNameToLinkTo)['entityName']) ? $entityNameToLinkTo = $this->replaceHalWithJsonEntity($entityNameToLinkTo)['entityName'] : null;

                return $entityNameToLinkTo;
            }
        }

        return null;
    }

    /**
     * Creates an endpoint property.
     *
     * @param array    $oasParameter           The parameter in the OAS that has to be made into a property
     * @param Endpoint $endpoint               The endpoint the property belongs to
     * @param int      $createdPropertiesCount The number of properties created until this moment
     *
     * @return Property The resulting property
     */
    private function createProperty(array $oasParameter, Endpoint $endpoint, int $createdPropertiesCount): Property
    {
        $propertyObject = new Property();
        $propertyObject->setName($oasParameter['name']);
        isset($oasParameter['description']) && $propertyObject->setDescription($oasParameter['description']);
        isset($oasParameter['required']) && $propertyObject->setRequired($oasParameter['required']);
        $propertyObject->setInType($oasParameter['in']);
        $propertyObject->setSchemaArray($oasParameter['schema']);

        $pathPropertiesCount = 0;
        foreach ($endpoint->getProperties() as $property) {
            $property->getInType() === 'path' && $pathPropertiesCount++;
        }
        $propertyObject->setPathOrder($pathPropertiesCount + $createdPropertiesCount);

        return $propertyObject;
    }

    /**
     * Checks if type is a array of and returns the proper type as string.
     *
     * @param $type The data type
     *
     * @return string The data type
     */
    private function checkSchemaTypeAsArray($type): string
    {
        $dataTypes = ['string' => 'string', 'integer' => 'integer', 'number' => 'integer', 'text' => 'string'];
        foreach ($dataTypes as $key => $dataType) {
            if (in_array($key, $type)) {
                $type = $dataType;
                break;
            }
        }

        return $type;
    }

    /**
     * Sets the type of a Attribute.
     *
     * @param array     $schema    The defining schema
     * @param Attribute $attribute The attribute to set the schema for
     *
     * @return Attribute The resulting attribute with resulting schema
     */
    private function setAttributeType(array $schema, Attribute $attribute): Attribute
    {
        if (isset($schema['type'])) {
            if (is_array($schema['type'])) {
                $schema['type'] = $this->checkSchemaTypeAsArray($schema['type']);
            }
            $attribute->setType($schema['type']);
        } else {
            $attribute->setType('string');
        }

        return $attribute;
    }

    /**
     * Sets the schema of a flat Attribute.
     *
     * @param array     $schema    The defining schema
     * @param Attribute $attribute The attribute to set the schema for
     *
     * @return Attribute The resulting attribute with resulting schema
     */
    private function setSchemaForAttribute(array $schema, Attribute $attribute): Attribute
    {
        $attribute = $this->setAttributeType($schema, $attribute);

        // If format == date-time set type: datetime
        isset($schema['format']) && $schema['format'] === 'date-time' && $attribute->setType('datetime');
        isset($schema['format']) && $schema['format'] === 'date' && $attribute->setType('date');
        isset($schema['format']) && ($schema['format'] !== 'date-time' && $schema['format'] !== 'date') && $attribute->setFormat($schema['format']);
        isset($schema['readyOnly']) && $attribute->setReadOnly($schema['readOnly']);
        isset($schema['maxLength']) && $attribute->setMaxLength($schema['maxLength']);
        isset($schema['minLength']) && $attribute->setMinLength($schema['minLength']);
        isset($schema['enum']) && $attribute->setEnum($schema['enum']);
        // Check if maximum/minimum is numeric string and below max int size
        isset($schema['maximum']) && $schema['maximum'] = (is_numeric($schema['maximum']) && is_string($schema['maximum'])) ? intval($schema['maximum']) : $schema['maximum'];
        isset($schema['maximum']) && $schema['maximum'] < PHP_INT_MAX && $attribute->setMaximum($schema['maximum']);
        isset($schema['minimum']) && $schema['minimum'] = (is_numeric($schema['minimum']) && is_string($schema['minimum'])) ? intval($schema['minimum']) : $schema['minimum'];
        isset($schema['minimum']) && $schema['minimum'] < PHP_INT_MAX && $attribute->setMinimum($schema['minimum']);

        // @TODO do something with pattern
        // isset($property['pattern']) && $attribute->setPattern($property['pattern']);
        isset($schema['readOnly']) && $attribute->setPattern($schema['readOnly']);

        return $attribute;
    }

    /**
     * Gets the schema for an object from an internal reference.
     *
     * @param string $ref  The reference to find
     * @param array  $data The oas to find the reference in
     *
     * @return array The schema found
     */
    private function getSchemaFromReferencedLocation(string $ref, array $data): array
    {
        $ref = explode('/', $ref);
        foreach ($ref as $location) {
            if ($location && $location !== '#') {
                $data = $data[$location];
            }
        }

        return $data;
    }

    /**
     * Creates a special type of property for an endpoint.
     *
     * @param array  $endpoints
     * @param string $pathRegex
     *
     * @return bool The created property
     */
    private function findEntityByPath(array $endpoints, string $pathRegex): bool
    {
        foreach ($endpoints as $endpoint) {
            if ($endpoint->getPathRegex() == $pathRegex) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a special type of property for an endpoint.
     *
     * @param string $pathRegex
     *
     * @return ?string The created property
     */
    private function findEndpointByHandler(string $pathRegex): ?string
    {
        foreach ($this->handlersToCreate as $entityName => $handler) {
            if (isset($handler['endpoints'])) {
                $endpoint = $this->findEntityByPath($handler['endpoints'], $pathRegex);

                if ($endpoint) {
                    return $entityName;
                }
            }
        }

        return null;
    }

    /**
     * Parses content for responses in the OpenAPI Specification.
     *
     * @param array    $response   The response the content relates to
     * @param array    $method     The HTTP method of the response
     * @param string   $methodName The name of the HTTP method of the response
     * @param Endpoint $endpoint   The endpoint the response relates to
     */
    private function parseContent(array $response, array $method, string $methodName, Endpoint $endpoint): void
    {
        if (isset($response['content']['application/json'])) {
            $entityNameToLinkTo = isset($response['content']['application/json']['schema']['$ref']) ?
                substr($response['content']['application/json']['schema']['$ref'], strrpos($response['content']['application/json']['schema']['$ref'], '/') + 1) : (
                    isset($response['content']['application/json']['schema']['properties']) ?
                    substr($response['content']['application/json']['schema']['properties']['results']['items']['$ref'], strrpos($response['content']['application/json']['schema']['properties']['results']['items']['$ref'], '/') + 1) :
                    substr($response['content']['application/json']['schema']['items']['$ref'], strrpos($response['content']['application/json']['schema']['items']['$ref'], '/') + 1)
                );
        } else {
            $entityNameToLinkTo = $this->parseFirstContent($response);
        }

        if ($entityNameToLinkTo == null) {
            $entityNameToLinkTo = $this->findEndpointByHandler($endpoint->getPathRegex());
        }

        if (isset($this->handlersToCreate[$entityNameToLinkTo])) {
            $this->handlersToCreate[$entityNameToLinkTo]['endpoints'][] = $endpoint;
            !isset($this->handlersToCreate[$entityNameToLinkTo]['methods']) && $this->handlersToCreate[$entityNameToLinkTo]['methods'] = [];
            !in_array($method, $this->handlersToCreate[$entityNameToLinkTo]['methods']) && $this->handlersToCreate[$entityNameToLinkTo]['methods'][] = $methodName;
        }
    }

    /**
     * Creates a special type of property for an endpoint.
     *
     * @param string   $property               The name of the property
     * @param Endpoint $endpoint               The endpoint the property belongs to
     * @param int      $createdPropertiesCount The properties created until this point
     *
     * @return Property|null The created property
     */
    private function createSpecialProperty(string $property, Endpoint $endpoint, int &$createdPropertiesCount): ?Property
    {
        // Check if property exist as parameter in the OAS
        if (isset($this->oas['components']['parameters'][$property])) {
            $oasParameter = $this->oas['components']['parameters'][$property];

            $propertyRepo = $this->entityManager->getRepository('App:Property');
            $propertyObject = $propertyRepo->findOneBy(['name' => $oasParameter['name']]) ? $propertyRepo->findOneBy(['name' => $oasParameter['name']]) : $this->createProperty($oasParameter, $endpoint, $createdPropertiesCount);

            // Set Endpoint and persist Property
            $propertyObject->setEndpoint($endpoint);
            $this->entityManager->persist($propertyObject);
            $createdPropertiesCount++;

            return $propertyObject;
        }

        return null;
    }

    /**
     * Creates a 'flat' Attribute (being not an object) from an OAS property.
     *
     * @param string $propertyName The name of the property
     * @param array  $schema       The definition of the property
     * @param Entity $parentEntity The entity the attribute belongs to
     * @param bool   $multiple     Whether the attribute should allow for multiple values (i.e. is an array of values)
     *
     * @return Attribute The resulting attribute
     */
    private function createFlatAttribute(string $propertyName, array $schema, Entity $parentEntity, bool $multiple = false): Attribute
    {
        $attribute = new Attribute();
        $attribute->setName($propertyName);

        (isset($schema['required']) && $schema['required'] === true) && $attribute->setRequired(true);
        isset($schema['description']) && $attribute->setDescription($schema['description']);
        isset($schema['readOnly']) && $attribute->setReadOnly($schema['readOnly']);

        // Set schema.org ref
        isset($schema['x-context']) && strpos($schema['x-context'], 'schema') !== false && $attribute->setSchema($schema['x-context']);

        if (
            isset($schema['format']) && $schema['format'] == 'uri' && isset($schema['type']) &&
            $schema['type'] == 'string' && isset($schema['readOnly']) && $schema['readOnly'] == true &&
            $propertyName == 'url'
        ) {
            $attribute->setFunction('self');
        }

        $attribute = $this->setSchemaForAttribute($schema, $attribute);
        $attribute->setMultiple($multiple);
        $attribute->setEntity($parentEntity);
        $attribute->setSearchable(true);

        return $attribute;
    }

    /**
     * Creates an 'array' Attribute (either an array of objects, a free-form array of an array of defined types) from an OAS property.
     *
     * @param string           $propertyName The name of the property
     * @param array            $schema       The schema of the property
     * @param Entity           $parentEntity The entity the attribute relates to
     * @param CollectionEntity $collection   The collection any created entity should relate to
     *
     * @throws Exception Thrown when getEntity throws an exception
     *
     * @return Attribute The resulting Attribute
     */
    private function createArrayAttribute(string $propertyName, array $schema, Entity $parentEntity, CollectionEntity $collection): Attribute
    {
        if (isset($schema['items']) && isset($schema['items']['$ref'])) {
            $itemSchema = $this->getSchemaFromRef($schema['items']['$ref'], $targetEntity);
        } elseif (isset($schema['items'])) {
            $itemSchema = $schema['items'];
        } else {
            return $this->createFlatAttribute($propertyName, $schema, $parentEntity);
        }

        isset($itemSchema) && isset($schema['x-context']) && $itemSchema['x-context'] = $schema['x-context'];

        if (isset($itemSchema['type']) && ($itemSchema['type'] == 'object' || $itemSchema['type'] == 'array') && isset($targetEntity)) {
            return $this->createObjectAttribute($propertyName, $parentEntity, $this->getEntity($targetEntity, $itemSchema, $collection), true);
        } elseif (isset($itemSchema['type']) && $itemSchema['type'] == 'object' && isset($itemSchema['properties'])) {
            $simpleEntity = $this->persistEntityFromSchema($parentEntity->getName().$propertyName.'Entity', $itemSchema, $collection);

            return $this->createObjectAttribute($propertyName, $parentEntity, $simpleEntity, true);
        } else {
            return $this->createFlatAttribute($propertyName, $itemSchema, $parentEntity, true);
        }
    }

    /**
     * Creates an attribute referencing another entity.
     *
     * @param string  $propertyName The name of the attribute
     * @param Entity  $parentEntity The entity the attribute is in
     * @param Entity  $targetEntity The entity the attribute references
     * @param bool    $multiple     Whether the attribute should allow for multiple values (i.e. is an array of values)
     * @param ?string $schema       Link to schema.org object
     *
     * @return Attribute The resulting attribute
     */
    private function createObjectAttribute(string $propertyName, Entity $parentEntity, Entity $targetEntity, bool $multiple = false): Attribute
    {
        $newAttribute = new Attribute();
        $newAttribute->setName($propertyName);
        $newAttribute->setEntity($parentEntity);
        $newAttribute->setCascade(true);
        $newAttribute->setMultiple($multiple);
        $newAttribute->setSearchable(true);
        $newAttribute->setExtend(true);

        $newAttribute->setObject($targetEntity);

        return $newAttribute;
    }

    /**
     * Gets a schema from a reference in the OAS specification, be it internal or external.
     *
     * @param string      $ref          The reference in the original OAS specification
     * @param string|null $targetEntity The name of the referenced entity
     *
     * @return array The resulting schema
     */
    private function getSchemaFromRef(string $ref, ?string &$targetEntity = ''): array
    {
        $targetEntity = substr($ref, strrpos($ref, '/') + 1);
        if (strpos($ref, 'https://') !== false || strpos($ref, 'http://') !== false) {
            $data = $this->getExternalOAS($ref);
            $ref = explode('#', $ref)[1];
        } else {
            $data = $this->oas;
        }

        return $this->getSchemaFromReferencedLocation($ref, $data);
    }

    /**
     * Parses responses from the OAS.
     *
     * @param array    $method     The schema that contains the responses
     * @param string   $methodName The name of the method
     * @param Endpoint $endpoint   The endpoint the response relates to
     */
    private function parseResponses(array $method, string $methodName, Endpoint $endpoint): void
    {
        foreach ($method['responses'] as $response) {
            if (!isset($response['content'])) {
                continue;
            }
            $this->parseContent($response, $method, $methodName, $endpoint);
            break;
        }
    }

    /**
     * This function reads OpenAPI Specification and persists it into Properties of an Endpoint.
     *
     * @param array    $path     The exploded path of the endpoint
     * @param Endpoint $endpoint The endpoint the path relates to
     *
     * @return array The resulting endpoint properties
     */
    private function createEndpointsProperties(array $path, Endpoint $endpoint): array
    {
        $endpointOperationType = 'collection';
        $createdPropertiesCount = 0;
        $createdProperties = [];
        foreach ($path as $property) {
            if ($property !== '{id}' && $property !== '{uuid}' && $property[0] === '{') {
                $endpointOperationType = 'item';
                // Remove {} from property
                $property = trim($property, '{}');
                $createdProperty = $this->createSpecialProperty($property, $endpoint, $createdPropertiesCount);
                $createdProperty ? $createdProperties[] = $createdProperty : null;
            } elseif ($property === '{id}' || $property === '{uuid}') {
                $endpointOperationType = 'item';
            } else {
                $endpointOperationType = 'collection';
            }
        }

        return ['operationType' => $endpointOperationType, 'createdProperties' => $createdProperties];
    }

    /**
     * Decide the regex of a path property.
     *
     * @param array $path   The exploded path of the endpoint
     * @param array $method The method of the endpoint
     *
     * @return string The resulting regex
     */
    private function getPathRegex(array $path, array $method): string
    {
        $pathRegex = '^';
        foreach ($path as $key => $part) {
            if (empty($part)) {
                continue;
            }
            substr($part, 0)[0] == '{' ? $pathRegex .= '/[a-z0-9-]+' : ($key < 1 ? $pathRegex .= $part : $pathRegex .= '/'.$part);
        }
        $pathRegex .= '$';

        return $pathRegex;
    }

    /**
     * This function creates an Attribute from an OAS property.
     *
     * @param array            $originalProperty
     * @param string           $propertyName     The name of the property
     * @param Entity           $entity           The entity the attribute belongs to
     * @param CollectionEntity $collectionEntity The collection the entities that are parsed belong to (for recursion)
     *
     * @throws Exception Thrown when the attribute cannot be parsed
     *
     * @return Attribute|null The resulting attribute
     */
    private function createAttribute(array $originalProperty, string $propertyName, Entity $entity, CollectionEntity $collectionEntity): ?Attribute
    {
        // Ignore this attribute if its id or empty, because the gateway generates this itself
        if ($propertyName == 'id' || empty($propertyName)) {
            return null;
        }

        if (isset($originalProperty['$ref'])) {
            $originalProperty = $this->getSchemaFromRef($originalProperty['$ref'], $targetEntity);
        } elseif (isset($originalProperty['items']['$ref']) && isset($originalProperty['type']) && $originalProperty['type'] == 'array') {
            $newProperty = $this->getSchemaFromRef($originalProperty['items']['$ref'], $targetEntity);
        } else {
            $targetEntity = $entity->getName().$propertyName.'Entity';
        }

        if ((!isset($originalProperty['type']) || $originalProperty['type'] == 'object') || (isset($originalProperty['type']) &&
            $originalProperty['type'] == 'array' && isset($originalProperty['items']['$ref']))) {
            $targetEntity = $this->getEntity($targetEntity, $newProperty ?? $originalProperty, $collectionEntity);
            $multiple = (isset($originalProperty['type']) && $originalProperty['type'] == 'array');
            $attribute = $this->createObjectAttribute($propertyName, $entity, $targetEntity, $multiple);
        } elseif ($originalProperty['type'] == 'array') {
            $attribute = $this->createArrayAttribute($propertyName, $originalProperty, $entity, $collectionEntity);
        } else {
            $attribute = $this->createFlatAttribute($propertyName, $originalProperty, $entity);
        }
        $this->entityManager->persist($attribute);

        if ($attribute->getSchema()) {
            $this->schemaRefs[] = [
                'id'     => $attribute->getId(),
                'type'   => 'attribute',
                'schema' => $attribute->getSchema(),
            ];
        }

        return $attribute;
    }

    /**
     * Processes allOf specifications.
     *
     * @param array            $allOf      The specification of the allOf object
     * @param Entity           $entity     The entity the allOf references
     * @param CollectionEntity $collection The collection the entities belong to
     *
     * @throws Exception Throws when the schema can not be decided
     */
    private function processAllOf(array $allOf, Entity $entity, CollectionEntity $collection)
    {
        $properties = [];
        if ($this->isAssociative($allOf)) {
            $properties = $allOf;
        } else {
            foreach ($allOf as $set) {
                if (isset($set['$ref'])) {
                    $schema = $this->getSchemaFromRef($set['$ref']);

                    if (isset($schema['properties'])) {
                        $properties = array_merge($schema['properties'], $properties);
                    } else {
                        $properties[] = $schema;
                    }
                } else {
                    $properties = array_merge($set['properties'], $properties);
                }
            }
        }
        foreach ($properties as $propertyName => $property) {
            $this->createAttribute($property, $propertyName, $entity, $collection);
        }
    }

    /**
     * Creates an endpoint object from the OAS specification.
     *
     * @param string           $path       The path of the endpoint
     * @param string           $methodName The HTTP method of the endpoint
     * @param array            $method     The HTTP method schema of the endpoint
     * @param CollectionEntity $collection The collection the endpoint relates to
     *
     * @return Endpoint The resulting endpoint
     */
    private function createEndpoint(string $path, string $methodName, array $method, CollectionEntity $collection): Endpoint
    {
        $pathArray = array_values(array_filter(explode('/', $path)));
        $endpoint = new Endpoint();
        $endpoint->addCollection($collection);
        $endpoint->setName($path.' '.$methodName);
        $endpoint->setMethod($methodName);
        $collection->getPrefix() && array_unshift($pathArray, $collection->getPrefix());
        $endpoint->setPath($pathArray);

        isset($method['description']) && $endpoint->setDescription($method['description']);
        isset($method['tags']) && $endpoint->setTags($method['tags']);
        $endpoint->setPathRegex($this->getPathRegex($pathArray, $method));
        $endpoint->setOperationType($this->createEndpointsProperties($pathArray, $endpoint)['operationType']);
        $this->entityManager->persist($endpoint);

        $this->parseResponses($method, $methodName, $endpoint);

        return $endpoint;
    }

    /**
     * Creates an entity from a schema.
     *
     * @param string           $name       The name of the entity
     * @param array            $schema     The schema of the entity
     * @param CollectionEntity $collection The collection the entity belongs to
     *
     * @throws Exception Thrown if an allOf or an attribute cannot be parsed
     *
     * @return Entity The resulting entity
     */
    private function persistEntityFromSchema(string $name, array $schema, CollectionEntity $collection): Entity
    {
        $newEntity = new Entity();
        $newEntity->setName($name);
        $newEntity->addCollection($collection);
        $collection->getSource() !== null && $newEntity->setSource($collection->getSource());
        isset($schema['x-context']) && strpos($schema['x-context'], 'schema') !== false && $newEntity->setSchema($schema['x-context']);

        $this->entityManager->persist($newEntity);
        $this->handlersToCreate[$name]['entity'] = $newEntity;

        if ($newEntity->getSchema()) {
            $this->schemaRefs[] = [
                'id'     => $newEntity->getId(),
                'type'   => 'entity',
                'schema' => $newEntity->getSchema(),
            ];
        }

        // Loop through allOf and create Attributes
        if (isset($schema['allOf'])) {
            $this->processAllOf($schema['allOf'], $newEntity, $collection);
        }

        // Loop through properties and create Attributes
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $propertyName => $property) {
                if (isset($schema['required']) && is_array($schema['required']) && in_array($propertyName, $schema['required'])) {
                    $property['required'] = true;
                }
                $attribute = $this->createAttribute($property, $propertyName, $newEntity, $collection);
            }
        }

        return $newEntity;
    }

    /**
     * Creates a handler for an endpoint.
     *
     * @param array|Endpoint[] $endpoints The endpoint the handler relates to
     * @param Entity           $entity    The entity the handler relates to
     * @param array            $methods   The methods the handler should allow
     *
     * @return Handler The resulting handler
     */
    private function createHandler(array $endpoints, Entity $entity, array $methods = []): Handler
    {
        $handler = new Handler();
        $handler->setName("{$entity->getName()} handler");
        $handler->setSequence(0);
        $handler->setConditions('{}');
        $methods && $handler->setMethods($methods);
        $handler->setEntity($entity);

        foreach ($endpoints as $endpoint) {
            $handler->addEndpoint($endpoint);
        }
        $this->entityManager->persist($handler);

        return $handler;
    }

    /**
     * Creates endpoints for a path.
     *
     * @param string           $path             The path the endpoints belong to
     * @param array            $methods          The methods the path should accept
     * @param CollectionEntity $collectionEntity The collection the endpoints should belong to
     *
     * @return Endpoint[] The resulting endpoints
     */
    private function createEndpointsPerPath(string $path, array $methods, CollectionEntity $collectionEntity): array
    {
        $endpoints = [];
        foreach ($methods as $name => $schema) {
            if (!isset($schema['responses'])) {
                continue;
            }
            $endpoints[] = $this->createEndpoint($path, $name, $schema, $collectionEntity);
        }

        return $endpoints;
    }

    /**
     * Gets an entity for an object. First checks if the entity has already been made in the collection, otherwise the entity is recursively made.
     *
     * @param string           $name             The name of the object
     * @param array            $schema           The schema of the object
     * @param CollectionEntity $collectionEntity The collection the object belongs to
     *
     * @throws Exception Thrown if the entity cannot be made
     *
     * @return Entity The resulting entity
     */
    private function getEntity(string $name, array $schema, CollectionEntity $collectionEntity): Entity
    {
        foreach ($collectionEntity->getEntities() as $entity) {
            if ($entity->getName() == $name) {
                return $entity;
            }
        }

        return $this->persistEntityFromSchema($name, $schema, $collectionEntity);
    }

    /**
     * Replaces an HAL entity by a normal JSON entity.
     *
     * @param string $entityName The entity to replace
     *
     * @return array The correct object schema
     */
    private function replaceHalWithJsonEntity(string $entityName): array
    {
        // If string contains Hal search for the schema without Hal
        if (str_contains($entityName, 'Hal')) {
            $entityNameWithoutHal = substr($entityName, 0, strpos($entityName, 'Hal'));

            // If schema without Hal is found make that the current iteration
            if (isset($this->oas['components']['schemas'][$entityNameWithoutHal])) {
                $entityInfo = $this->oas['components']['schemas'][$entityNameWithoutHal];
                $entityName = $entityNameWithoutHal;

                return ['entityName' => $entityName, 'entityInfo' => $entityInfo];
            }
        }

        return [];
    }

    /**
     * Creates handlers from the handlersToCreate global variable.
     *
     * @return Handler[] The created handlers
     */
    private function createHandlers(): array
    {
        $handlers = [];

        foreach ($this->handlersToCreate as $handlerToCreate) {
            if (!isset($handlerToCreate['endpoints']) || !isset($handlerToCreate['entity'])) {
                continue;
            }
            $handlers[] = $this->createHandler($handlerToCreate['endpoints'], $handlerToCreate['entity'], $handlerToCreate['methods'] ?: []);
        }

        return $handlers;
    }

    /**
     * This function reads OpenAPI Specification and persists it into Endpoints objects.
     *
     * @param CollectionEntity $collection The collection that the endpoints have to be in
     *
     * @return Endpoint[] The endpoints for the collection
     */
    private function persistPathsAsEndpoints(CollectionEntity $collection): array
    {
        $endpoints = [];
        foreach ($this->oas['paths'] as $pathName => $path) {
            $endpoints = array_merge($endpoints, $this->createEndpointsPerPath($pathName, $path, $collection));
        }
        $this->entityManager->flush();

        return $endpoints;
    }

    /**
     * This function reads redoc and persists it into Entity objects.
     *
     * @param CollectionEntity $collection The collection the entities should belong to
     *
     * @throws Exception Thrown if entities cannot be created
     *
     * @return Entity[] The resulting entities
     */
    private function persistSchemasAsEntities(CollectionEntity $collection): array
    {
        // These attributes can only be set when entities are flushed, otherwise they cant find eachother, so these will be persisted at the end of the code
        foreach ($this->oas['components']['schemas'] as $entityName => $entityInfo) {
            // If this schema is not a valid Entity to persist continue foreach
            if ((!isset($entityInfo['type']) && !isset($entityInfo['allOf'])) || (isset($entityInfo['type']) && $entityInfo['type'] !== 'object')) {
                continue;
            }
            // Check for json schema instead of Hal
            $replaceHalInfo = $this->replaceHalWithJsonEntity($entityName);
            isset($replaceHalInfo['entityName']) && $entityName = $replaceHalInfo['entityName'];
            isset($replaceHalInfo['entityInfo']) && $entityInfo = $replaceHalInfo['entityInfo'];

            // If schema is already iterated continue foreach

            $entities[] = $this->getEntity($entityName, $entityInfo, $collection);
        }
        $this->entityManager->flush();

        return $entities;
    }

    /**
     * This function reads OpenAPI Specification files and parses it into doctrine objects.
     *
     * @param CollectionEntity $collection The collection the oas should be parsed into
     *
     * @throws Exception Thrown if an object cannot be made
     */
    public function parseOas(CollectionEntity $collection, array &$schemaRefs = null): CollectionEntity
    {
        $this->oas = $this->getExternalOAS($collection->getLocationOAS());
        $entities = $this->persistSchemasAsEntities($collection);
        $endpoints = $this->persistPathsAsEndpoints($collection);
        $this->entityManager->flush();

        // Create Handlers between the Entities and Endpoints
        $handlers = $this->createHandlers();
        $this->entityManager->flush();
        // Set synced at
        $collection = $this->entityManager->getRepository(CollectionEntity::class)->find($collection->getId());
        $collection->setSyncedAt(new \DateTime('now'));
        $this->entityManager->persist($collection);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->oas = [];
        $this->handlersToCreate = [];

        $schemaRefs = $this->schemaRefs;

        return $collection;
    }
}
