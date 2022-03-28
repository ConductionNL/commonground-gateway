<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\CollectionEntity;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\Handler;
use App\Entity\Property;
use App\Repository\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * This service takes an external OpenAPI v3 specification and turns that into a gateway + eav structure
 */
class OasParserService
{
    private EntityManagerInterface $entityManager;

    private array $handlersToCreate;
    private array $oas;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        $this->handlersToCreate = [];
        $this->oas = [];
    }

    /**
     * Checks if an array is associative
     *
     * @param array $array  The array to check
     * @return bool         Whether or not the array is associative
     */
    private function isAssociative(array $array): bool
    {
        if ([] === $array) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param array $response
     * @return string|null
     */
    private function parseFirstContent(array $response): ?string
    {
        foreach ($response['content'] as $content) {
            if (isset($content['schema']['$ref'])){
                $entityNameToLinkTo = substr($content['schema']['$ref'], strrpos($content['schema']['$ref'], '/') + 1);
                isset($replaceHalInfo['entityName']) && $entityNameToLinkTo = $this->replaceHalWithJsonEntity($entityNameToLinkTo);
                return $entityNameToLinkTo;
            }
        }
        return null;
    }

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
     * Sets the schema of a flat Attribute
     * @param array $schema         The defining schema
     * @param Attribute $attribute  The attribute to set the schema for
     * @return Attribute            The resulting attribute with resulting schema
     */
    private function setSchemaForAttribute(array $schema, Attribute $attribute): Attribute
    {
        isset($schema['type']) ? $attribute->setType($schema['type']) : $attribute->setType('string');

        // If format == date-time set type: datetime
        isset($schema['format']) && $schema['format'] === 'date-time' && $attribute->setType('datetime');
        isset($schema['format']) && $schema['format'] !== 'date-time' && $attribute->setFormat($schema['format']);
        isset($schema['readyOnly']) && $attribute->setReadOnly($schema['readOnly']);
        isset($schema['maxLength']) && $attribute->setMaxLength($schema['maxLength']);
        isset($schema['minLength']) && $attribute->setMinLength($schema['minLength']);
        isset($schema['enum']) && $attribute->setEnum($schema['enum']);
        isset($schema['maximum']) && $attribute->setMaximum($schema['maximum']);
        isset($schema['minimum']) && $attribute->setMinimum($schema['minimum']);

        // @TODO do something with pattern
        // isset($property['pattern']) && $attribute->setPattern($property['pattern']);
        isset($schema['readOnly']) && $attribute->setPattern($schema['readOnly']);

        return $attribute;
    }

    /**
     * Gets the schema for an object from an internal reference
     *
     * @param string $ref   The reference to find
     * @param array $data   The oas to find the reference in
     * @return array        The schema found
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
     * @param array $response
     * @param array $method
     * @param string $methodName
     * @param Endpoint $endpoint
     */
    private function parseContent(array $response, array $method, string $methodName, Endpoint $endpoint): void
    {
        if (isset($response['content']['application/json'])) {
            $entityNameToLinkTo = isset($response['content']['application/json']['schema']['$ref']) ?
                substr($response['content']['application/json']['schema']['$ref'], strrpos($response['content']['application/json']['schema']['$ref'], '/') + 1) :
                substr($response['content']['application/json']['schema']['properties']['results']['items']['$ref'], strrpos($response['content']['application/json']['schema']['properties']['results']['items']['$ref'], '/') + 1);
        } else {
            $entityNameToLinkTo = $this->parseFirstContent($response);
        }
        if (isset($this->handlersToCreate[$entityNameToLinkTo])) {
            $this->handlersToCreate[$entityNameToLinkTo]['endpoints'][] = $endpoint;
            !isset($this->handlersToCreate[$entityNameToLinkTo]['methods']) && $this->handlersToCreate[$entityNameToLinkTo]['methods'] = [];
            !in_array($method, $this->handlersToCreate[$entityNameToLinkTo]['methods']) && $this->handlersToCreate[$entityNameToLinkTo]['methods'][] = $methodName;
        }
    }

    /**
     * @param string $property
     * @param Endpoint $endpoint
     * @param int $createdPropertiesCount
     * @return Property|null
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
     * Creates a 'flat' Attribute (being not an object) from an OAS property
     * @param string $propertyName  The name of the property
     * @param array $schema         The definition of the property
     * @param Entity $parentEntity  The entity the attribute belongs to
     * @return Attribute            The resulting attribute
     */
    private function createFlatAttribute(string $propertyName, array $schema, Entity $parentEntity): Attribute
    {
        $attribute = new Attribute();
        $attribute->setName($propertyName);

        (isset($entityInfo['required']) && in_array($propertyName, $entityInfo['required'])) && $attribute->setRequired(true);
        isset($schema['description']) && $attribute->setDescription($schema['description']);

        $attribute = $this->setSchemaForAttribute($schema, $attribute);
        $attribute->setEntity($parentEntity);

        return $attribute;
    }

    /**
     * @param string $propertyName
     * @param Entity $parentEntity
     * @param Entity $targetEntity
     * @return Attribute
     */
    private function createObjectAttribute(string $propertyName, Entity $parentEntity, Entity $targetEntity): Attribute
    {
        $newAttribute = new Attribute();
        $newAttribute->setName($propertyName);
        $newAttribute->setEntity($parentEntity);
        $newAttribute->setCascade(true);

        $newAttribute->setObject($targetEntity);

        return $newAttribute;
    }

    /**
     *
     *
     * @param string $ref
     * @param string|null $targetEntity
     * @return array
     */
    private function getSchemaFromRef(string $ref, ?string &$targetEntity = ''): array
    {
        if (strpos($ref, 'https://') !== false || strpos($ref, 'http://') !== false) {
            $targetEntity = substr($ref, strrpos($ref, '/') + 1);
            //@TODO: Guzzle dit, also allow JSON
            $data = Yaml::parse(file_get_contents($ref));
            $ref = explode('#', $ref)[1];
        } else {
            $targetEntity = substr($ref, strrpos($ref, '/') + 1);
            $data = $this->oas;
        }
        return $this->getSchemaFromReferencedLocation($ref, $data);
    }
    /**
     * @param array $method
     * @param string $methodName
     * @param Endpoint $endpoint
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
     * @param array $path
     * @param Endpoint $newEndpoint
     * @return array
     */
    private function createEndpointsProperties(array $path, Endpoint $newEndpoint): array
    {
        $endpointOperationType = 'collection';
        $createdPropertiesCount = 0;
        $createdProperties = [];
        foreach ($path as $property) {
            if ($property !== '{id}' && $property !== '{uuid}' && $property[0] === '{') {
                $endpointOperationType = 'item';
                // Remove {} from property
                $property = trim($property, '{');
                $property = trim($property, '}');
                $createdProperty = $this->createSpecialProperty($property, $newEndpoint, $createdPropertiesCount);
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
     * @param array $path
     * @param array $method
     * @return string
     */
    private function getPathRegex(array $path, array $method): string
    {
        $pathRegex = '#^(';
        foreach ($path as $key => $part) {
            if (empty($part)) {
                continue;
            }
            substr($part, 0)[0] == '{' ? $pathRegex .= '/[^/]*' : ($key <= 1 ? $pathRegex .= $part : $pathRegex .= '/'.$part);
        }
        $pathRegex .= ')$#';

        return $pathRegex;
    }

    /**
     * This function creates a Attribute from an OAS property.
     * @param array $property The definition of the property
     * @param string $propertyName The name of the property
     * @param Entity $newEntity The entity the attribute belongs to
     * @param CollectionEntity $collectionEntity The collection the entities that are parsed belong to (for recursion)
     * @return Attribute|null                       The resulting attribute
     * @throws \Exception Thrown when the attribute cannot be parsed
     */
    private function createAttribute(array $property, string $propertyName, Entity $newEntity, CollectionEntity $collectionEntity): ?Attribute
    {
        // Check reference to other object
        if (isset($property['$ref'])) {
            $property = $this->getSchemaFromRef($property['$ref'], $targetEntity);
        }
        if (!isset($targetEntity)) {
            $targetEntity = $newEntity->getName().$propertyName.'Entity';
        }

        if (!isset($property['type']) || $property['type'] == 'object') {
            $targetEntity = $this->getEntity($targetEntity, $property, $collectionEntity);
            $attribute = $this->createObjectAttribute($propertyName, $newEntity, $targetEntity);
        } else {
            $attribute = $this->createFlatAttribute($propertyName, $property, $newEntity);
        }
        $this->entityManager->persist($attribute);

        return $attribute;
    }

    /**
     * @param array $allOf
     * @param Entity $entity
     * @param CollectionEntity $collection
     * @throws \Exception
     */
    private function processAllOf(array $allOf, Entity $entity, CollectionEntity $collection)
    {
        $properties = [];
        if ($this->isAssociative($allOf)) {
            $properties = $allOf;
        } else {
            foreach ($allOf as $set) {
                if (isset($set['$ref'])) {
                    $schema = $this->getSchemaFromRef(explode('/', $set['$ref']));
                    $properties = array_merge($schema['properties'], $properties);
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
     * @param string $path
     * @param string $methodName
     * @param array $method
     * @param CollectionEntity $collection
     * @return Endpoint
     */
    private function createEndpoint(string $path, string $methodName, array $method, CollectionEntity $collection): Endpoint
    {
        $pathArray = array_values(array_filter(explode('/', $path)));
        $endpoint = new Endpoint();
        $endpoint->addCollection($collection);
        $endpoint->setName($path.' '.$methodName);
        $endpoint->setMethod($methodName);
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
     * @param string $name
     * @param array $schema
     * @param CollectionEntity $collection
     * @return Entity
     * @throws \Exception
     */
    private function persistEntityFromSchema(string $name, array $schema, CollectionEntity $collection): Entity
    {
        $newEntity = new Entity();
        $newEntity->setName($name);
        $newEntity->addCollection($collection);
        $collection->getSource() !== null && $newEntity->setGateway($collection->getSource());

        $this->entityManager->persist($newEntity);

        $this->handlersToCreate[$name]['entity'] = $newEntity;

        // Loop through allOf and create Attributes
        if (isset($schema['allOf'])) {
            $this->processAllOf($schema['allOf'], $newEntity, $collection);
        }

        // Loop through properties and create Attributes
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $propertyName => $property) {
                $attribute = $this->createAttribute($property, $propertyName, $newEntity, $collection);
            }
        }

        return $newEntity;
    }

    /**
     * @param array|Endpoint[] $endpoints
     * @param Entity $entity
     * @param array $methods
     * @return Handler
     */
    private function createHandler(array $endpoints, Entity $entity, array $methods = []): Handler
    {
        $handler = new Handler();
        $handler->setName("{$entity->getName()} handler");
        $handler->setSequence(0);
        $handler->setConditions('{}');
        isset($handler['methods']) &&$handler->setMethods($methods);
        $handler->setEntity($entity);

        foreach($endpoints as $endpoint){
            $handler->addEndpoint($endpoint);
        }
        $this->entityManager->persist($handler);

        return $handler;
    }

    /**
     * @param string $path
     * @param array $methods
     * @param CollectionEntity $collectionEntity
     * @return array
     */
    private function createEndpointsPerPath(string $path, array $methods, CollectionEntity $collectionEntity): array
    {
        $endpoints = [];
        foreach($methods as $name => $schema)
        {
            if (!isset($method['responses'])) {
                continue;
            }
            $endpoints[] = $this->createEndpoint($path, $name, $schema, $collectionEntity);
        }
        return $endpoints;
    }

    /**
     * Gets an entity for an object. First checks if the entity has already been made in the collection, otherwise the entity is recursively made
     *
     * @param string $name The name of the object
     * @param array $schema The schema of the object
     * @param CollectionEntity $collectionEntity The collection the object belongs to
     * @return Entity
     * @throws \Exception
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
     * Replaces an HAL entity by a normal JSON entity
     *
     * @param string $entityName The entity to replace
     * @return  array               The correct entity
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
     * Creates handlers from the handlersToCreate global variable
     *
     * @return array The created handlers
     */
    private function createHandlers(): array
    {
        $handlers = [];

        foreach($this->handlersToCreate as $handlerToCreate){
            if (!isset($handler['endpoints']) || !isset($handler['entity'])) {
                continue;
            }
            $handlers[] = $this->createHandler($handlerToCreate['endpoints'], $handlerToCreate['entity'], $handlerToCreate['method']);
        }

        return $handlers;
    }

    /**
     * This function reads OpenAPI Specification and persists it into Endpoints objects
     *
     * @param CollectionEntity $collection  The collection that the endpoints have to be in
     * @return array                        The endpoints for the collection
     */
    private function persistPathsAsEndpoints(CollectionEntity $collection): array
    {
        $endpoints = [];
        foreach ($this->oas['paths'] as $pathName => $path) {
            array_merge($endpoints, $this->createEndpointsPerPath($pathName, $path, $collection));
        }

        $this->entityManager->flush();

        return $endpoints;
    }

    /**
     * This function reads redoc and persists it into Entity objects.
     *
     * @param CollectionEntity $collection
     * @return array
     * @throws \Exception
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
     * @param array $oas The OpenAPI Specification of the collection
     * @param CollectionEntity $collection The collection the oas should be parsed into
     * @throws \Exception
     */
    public function parseOas(array $oas, CollectionEntity $collection)
    {
        $this->oas = $oas;
        // Persist Entities and Attributes
        $entities = $this->persistSchemasAsEntities($oas, $collection);

        // Persist Endpoints and its Properties
        $endpoints = $this->persistPathsAsEndpoints($collection);

        $this->entityManager->flush();

        // Create Handlers between the Entities and Endpoints
        $handlers = $this->createHandlers();

        // Execute sql to database
        $this->entityManager->flush();
        $this->oas = [];
        $this->handlersToCreate = [];
    }
}
