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
use Symfony\Component\Yaml\Yaml;

/*
 * This servers takes an external oas document and turns that into an gateway + eav structure
 */
class OasParserService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->entityRepo = $this->entityManager->getRepository('App:Entity');
        $this->handlerRepo = $this->entityManager->getRepository('App:Handler');
        $this->endpointRepo = $this->entityManager->getRepository('App:Endpoint');
    }

    /**
     * This function reads redoc and parses it into doctrine objects.
     */
    public function parseRedoc(array $redoc, CollectionEntity $collection)
    {
        // Persist Entities and Attributes
        $handlersToSetLaterAsObject = $this->persistSchemasAsEntities($redoc, $collection);

        // Persist Endpoints and its Properties
        $handlersToSetLaterAsObject = $this->persistPathsAsEndpoints($redoc, $collection, $handlersToSetLaterAsObject);

        $this->entityManager->flush();

        // Create Handlers between the Entities and Endpoints
        foreach ($handlersToSetLaterAsObject as $entityName => $handler) {

            // If we dont have endpoints or entity continue foreach
            if (!isset($handler['endpoints']) || !isset($handler['entity'])) {
                continue;
            }

            $newHandler = new Handler();
            $newHandler->setName($entityName.' handler');
            $newHandler->setSequence(0);
            $newHandler->setConditions('{}');
            isset($handler['methods']) && $newHandler->setMethods(array_unique($handler['methods']));
            $newHandler->setEntity($handler['entity']);
            foreach ($handler['endpoints'] as $endpoint) {
                $newHandler->addEndpoint($endpoint);
            }

            $this->entityManager->persist($newHandler);
        }

        // Execute sql to database
        $this->entityManager->flush();
    }

    private function isAssociative(array $array): bool
    {
        if (array() === $array) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function processAllOf(array $allOf, Entity $entity, CollectionEntity $collection, array &$handlersToSetLaterAsObject, array $oas)
    {
        $properties = [];
        if($this->isAssociative($allOf)){
            $properties = $allOf;
        } else {
            foreach($allOf as $set){
                if(isset($set['$ref'])){
                    $schema = $this->getDataFromRef($oas, explode('/', $set['$ref']));
                    $properties = array_merge($schema['properties'], $properties);
                } else {
                    $properties = array_merge($set['properties'], $properties);
                }
            }
        }
        foreach ($properties as $propertyName => $property) {
            $this->createAttribute($property, $propertyName, $entity, $handlersToSetLaterAsObject, $oas, $collection);
        }
    }

    private function persistEntityFromSchema(string $name, array $schema, CollectionEntity $collection, array $oas, array &$handlersToSetLaterAsObject): Entity
    {
        $newEntity = new Entity();
        $newEntity->setName($name);
        $newEntity->addCollection($collection);
        $collection->getSource() !== null && $newEntity->setGateway($collection->getSource());

        $this->entityManager->persist($newEntity);

        $handlersToSetLaterAsObject[$name]['entity'] = $newEntity;

        // Loop through allOf and create Attributes
        if (isset($schema['allOf'])) {
            $this->processAllOf($schema['allOf'], $newEntity, $collection, $handlersToSetLaterAsObject, $oas);
        }

        // Loop through properties and create Attributes
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $propertyName => $property) {
                $this->createAttribute($property, $propertyName, $newEntity, $handlersToSetLaterAsObject, $oas, $collection);
            }
        }

        return $newEntity;
    }

    /**
     * This function reads redoc and persists it into Entity objects.
     */
    private function persistSchemasAsEntities(array $redoc, CollectionEntity $collection): array
    {
        // These attributes can only be set when entities are flushed, otherwise they cant find eachother, so these will be persisted at the end of the code
        $handlersToSetLaterAsObject = [];

//        var_dump(array_keys($redoc['components']['schemas']));
        foreach ($redoc['components']['schemas'] as $entityName => $entityInfo) {
            // if ($entityName !== 'IngeschrevenPersoonHal' && $entityName !== 'IngeschrevenPersoonHal' && $entityName !== 'IngeschrevenPersoonHalBasis'  && $entityName !== 'IngeschrevenPersoon') continue;

            // If this schema is not a valid Entity to persist continue foreach
            if ((!isset($entityInfo['type']) && !isset($entityInfo['allOf'])) || (isset($entityInfo['type']) && $entityInfo['type'] !== 'object')) {
                continue;
            }

            // Check for json schema instead of Hal
            $replaceHalInfo = $this->replaceHalWithJsonEntity($entityName, $redoc);
            isset($replaceHalInfo['entityName']) && $entityName = $replaceHalInfo['entityName'];
            isset($replaceHalInfo['entityInfo']) && $entityInfo = $replaceHalInfo['entityInfo'];

            // If schema is already iterated continue foreach

            $entities[] = $this->getEntity($entityName, $entityInfo, $collection, $redoc, $handlersToSetLaterAsObject);
        }

        $this->entityManager->flush();
        return $handlersToSetLaterAsObject;
    }

    private function getDataFromRef(array $oas, array $ref): array
    {
        $data = $oas;
        try{
            foreach($ref as $location){
                if($location && $location !== '#')
                    $data = $data[$location];
            }
        } catch (\Exception $exception){
            var_dump(array_keys($oas['components']['schemas']));
            throw $exception;
        }

        return $data;
    }

    private function getEntity(string $name, array $schema, CollectionEntity $collectionEntity, array $oas, array &$handlersToSetLaterAsObject): Entity
    {
        foreach($collectionEntity->getEntities() as $entity){
            if($entity->getName() == $name) {
                return $entity;
            }
        }

        return $this->persistEntityFromSchema($name, $schema, $collectionEntity, $oas, $handlersToSetLaterAsObject);
    }

    private function createObjectAttribute(string $propertyName, Entity $parentEntity, Entity $targetEntity): Attribute
    {
        $newAttribute = new Attribute();
        $newAttribute->setName($propertyName);
        $newAttribute->setEntity($parentEntity);
        $newAttribute->setCascade(true);

        $newAttribute->setObject($targetEntity);

        return $newAttribute;
    }

    private function createFlatAttribute(string $propertyName, array $schema, Entity $parentEntity): Attribute
    {
        $attribute = new Attribute();
        $attribute->setName($propertyName);

        (isset($entityInfo['required']) && in_array($propertyName, $entityInfo['required'])) && $attribute->setRequired(true);
        isset($schema['description']) && $attribute->setDescription($schema['description']);
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

        $attribute->setEntity($parentEntity);

        return $attribute;
    }

    /**
     * This function creates a Attribute from a OAS property.
     */
    private function createAttribute(array $property, string $propertyName, Entity $newEntity, array &$handlersToSetLaterAsObject, array &$oas, CollectionEntity $collectionEntity): ?Attribute
    {
        // Check reference to other object
        if (isset($property['$ref'])) {
            if(strpos($property['$ref'], 'https://') !== false){
                $targetEntity = substr($property['$ref'], strrpos($property['$ref'], '/') + 1);
                $dataOas = Yaml::parse(file_get_contents($property['$ref']));
                $ref = explode('#', $property['$ref'])[1];

            } else {
                $targetEntity = substr($property['$ref'], strrpos($property['$ref'], '/') + 1);
                $ref = $property['$ref'];
                $dataOas = $oas;
            }
            $ref = explode('/', $ref);
            $property = $this->getDataFromRef($dataOas, $ref);
        }
        if(!isset($targetEntity)) {
            $targetEntity = $newEntity->getName().$propertyName.'Entity';
        }

        if(!isset($property['type']) || $property['type'] == 'object'){
            $targetEntity = $this->getEntity($targetEntity, $property, $collectionEntity, $oas, $handlersToSetLaterAsObject);
            $attribute = $this->createObjectAttribute($propertyName, $newEntity, $targetEntity);
        } else {
            $attribute = $this->createFlatAttribute($propertyName, $property, $newEntity);
        }

        // Persist attribute
        $this->entityManager->persist($attribute);

        return $attribute;
    }

    /**
     * This function reads redoc and persists it into Endpoints objects.
     */
    private function persistPathsAsEndpoints(array $redoc, CollectionEntity $collection, array $handlersToCreateLater): array
    {
        foreach ($redoc['paths'] as $pathName => $path) {
            // if ($pathName !== '/ingeschrevenpersonen/{burgerservicenummer}') continue;

            foreach ($path as $methodName => $method) {
                if (!isset($method['responses'])) {
                    continue;
                }

                $newEndpoint = new Endpoint();
                $newEndpoint->addCollection($collection);
                $newEndpoint->setName($pathName.' '.$methodName);
                $pathParts = explode('/', $pathName);
                $pathArray = [];
                foreach ($pathParts as $key => $part) {
                    $pathArray[$key] = $part;
                }
                $newEndpoint->setPath(array_values(array_filter($pathArray)));
                isset($method['description']) && $newEndpoint->setDescription($method['description']);
                isset($method['tags']) && $newEndpoint->setTags($method['tags']);

                // Set pathRegex
                $pathRegex = '#^(';
                foreach ($pathParts as $key => $part) {
                    if (empty($part)) {
                        continue;
                    }
                    substr($part, 0)[0] == '{' ? $pathRegex .= '/[^/]*' : ($key <= 1 ? $pathRegex .= $part : $pathRegex .= '/' . $part);
                }
                $pathRegex .= ')$#';
                $newEndpoint->setPathRegex($pathRegex);

                // Checks pathName if there are Properties that need to be created and sets Endpoint.operationType
                $createdPropertiesInfo = $this->createEndpointsProperties($redoc, $pathName, $newEndpoint);

                $newEndpoint->setOperationType($createdPropertiesInfo['operationType']);

                $this->entityManager->persist($newEndpoint);

                foreach ($method['responses'] as $response) {
                    if (!isset($response['content'])) {
                        continue;
                    }
                    foreach ($response['content'] as $content) {

                        // Use json definition
                        if (isset($response['content']['application/json'])) {
                            if (isset($response['content']['application/json']['schema']['$ref'])) {
                                $entityNameToLinkTo = substr($response['content']['application/json']['schema']['$ref'], strrpos($response['content']['application/json']['schema']['$ref'], '/') + 1);
                            } else {
                                $entityNameToLinkTo = substr($response['content']['application/json']['schema']['properties']['results']['items']['$ref'], strrpos($response['content']['application/json']['schema']['properties']['results']['items']['$ref'], '/') + 1);
                            }
                            if (isset($handlersToCreateLater[$entityNameToLinkTo])) {
                                $handlersToCreateLater[$entityNameToLinkTo]['endpoints'][] = $newEndpoint;
                                !isset($handlersToCreateLater[$entityNameToLinkTo]['methods']) && $handlersToCreateLater[$entityNameToLinkTo]['methods'] = [];
                                !in_array($method, $handlersToCreateLater[$entityNameToLinkTo]['methods']) && $handlersToCreateLater[$entityNameToLinkTo]['methods'][] = $methodName;
                            }
                            break;
                        }

                        // Else use first definition found
                        if (isset($content['schema']['$ref'])) {
                            $entityNameToLinkTo = substr($content['schema']['$ref'], strrpos($content['schema']['$ref'], '/') + 1);

                            // Replace Hal schema with normal schema
                            $replaceHalInfo = $this->replaceHalWithJsonEntity($entityNameToLinkTo, $redoc);
                            isset($replaceHalInfo['entityName']) && $entityNameToLinkTo = $replaceHalInfo['entityName'];

                            if (isset($handlersToCreateLater[$entityNameToLinkTo])) {
                                $handlersToCreateLater[$entityNameToLinkTo]['endpoints'][] = $newEndpoint;
                                !isset($handlersToCreateLater[$entityNameToLinkTo]['methods']) && $handlersToCreateLater[$entityNameToLinkTo]['methods'] = [];
                                !in_array($method, $handlersToCreateLater[$entityNameToLinkTo]['methods']) && $handlersToCreateLater[$entityNameToLinkTo]['methods'][] = $methodName;
                            }
                        }
                        break;
                    }
                    break;
                }
            }
        }

        // Execute sql to database
        $this->entityManager->flush();

        return $handlersToCreateLater;
    }

    /**
     * This function reads redoc and persists it into Properties of an Endpoint.
     *
     * @return string Endpoint.operationType based on if the last Property is a identifier or not.
     */
    private function createEndpointsProperties(array $redoc, string $pathName, Endpoint $newEndpoint): array
    {
        // Check for subpaths and variables
        $partsOfPath = array_filter(explode('/', $pathName));
        $endpointOperationType = 'collection';

        $createdPropertiesCount = 0;
        $createdProperties = [];

        foreach ($partsOfPath as $property) {
            // If we have a variable in a path (thats not id or uuid) search for parameter and create Property
            if ($property !== '{id}' && $property !== '{uuid}' && $property[0] === '{') {
                $endpointOperationType = 'item';

                // Remove {} from property
                $property = trim($property, '{');
                $property = trim($property, '}');

                // Check if property exist as parameter in the OAS
                if (isset($redoc['components']['parameters'][$property])) {
                    $oasParameter = $redoc['components']['parameters'][$property];

                    // Search if Property already exists
                    $propertyRepo = $this->entityManager->getRepository('App:Property');
                    $propertyToPersist = $propertyRepo->findOneBy([
                        'name' => $oasParameter['name'],
                    ]);

                    // Create new Property if we haven't found one
                    if (!isset($propertyToPersist)) {
                        $propertyToPersist = new Property();
                        $propertyToPersist->setName($oasParameter['name']);
                        isset($oasParameter['description']) && $propertyToPersist->setDescription($oasParameter['description']);
                        isset($oasParameter['required']) && $propertyToPersist->setRequired($oasParameter['required']);
                        $propertyToPersist->setInType($oasParameter['in']);
                        $propertyToPersist->setSchemaArray($oasParameter['schema']);

                        // Set pathOrder
                        $pathPropertiesCount = 0;
                        foreach ($newEndpoint->getProperties() as $property) {
                            $property->getInType() === 'path' && $pathPropertiesCount++;
                        }
                        $propertyToPersist->setPathOrder($pathPropertiesCount + $createdPropertiesCount);
                    }

                    // Set Endpoint and persist Property
                    $propertyToPersist->setEndpoint($newEndpoint);
                    $this->entityManager->persist($propertyToPersist);

                    // Created properties + 1 for property.pathOrder
                    $createdPropertiesCount++;
                }
            } elseif ($property === '{id}' || $property === '{uuid}') {
                $endpointOperationType = 'item';
            } else {
                $endpointOperationType = 'collection';
            }
        }

        return ['operationType' => $endpointOperationType, 'createdProperties' => $createdProperties];
    }

    private function replaceHalWithJsonEntity($entityName, $redoc)
    {
        // If string contains Hal search for the schema without Hal
        if (str_contains($entityName, 'Hal')) {
            $entityNameWithoutHal = substr($entityName, 0, strpos($entityName, 'Hal'));

            // If schema without Hal is found make that the current iteration
            if (isset($redoc['components']['schemas'][$entityNameWithoutHal])) {
                $entityInfo = $redoc['components']['schemas'][$entityNameWithoutHal];
                $entityName = $entityNameWithoutHal;

                return ['entityName' => $entityName, 'entityInfo' => $entityInfo];
            }
        }

        return [];
    }

    // public function getOAS(string $url): array
    // {
    //     $oas = [];

    //     // @todo validate on url
    //     $pathinfo = parse_url($url);
    //     $extension = explode('.', $pathinfo['path']);
    //     $extension = end($extension);
    //     $pathinfo['extension'] = $extension;

    //     // file_get_contents(
    //     $file = file_get_contents($url);

    //     switch ($pathinfo['extension']) {
    //         case 'yaml':
    //             $oas = Yaml::parse($file);
    //             break;
    //         case 'json':
    //             $oas = json_decode($file, true);
    //             break;
    //         default:
    //             $oas = json_decode($file, true);
    //            // @todo throw error
    //     }

    //     // Do we have servers?
    //     if (array_key_exists('servers', $oas)) {
    //         foreach ($oas['servers'] as $server) {
    //             $source = new Gateway();
    //             $source->setName($server['description']);
    //             $source->setLocation($server['url']);
    //             $source->setAuth('none');
    //             $this->em->persist($source);
    //         }
    //     }

    //     // Do we have schemse?
    //     $schemas = [];
    //     $attributeDependencies = [];
    //     if (array_key_exists('components', $oas) && array_key_exists('schemas', $oas['components'])) {
    //         foreach ($oas['components']['schemas'] as $schemaName => $schema) {
    //             $entity = new Entity();
    //             $entity->setName($schemaName);
    //             if (array_key_exists('description', $schema)) {
    //                 $entity->setDescription($schema['description']);
    //             }
    //             // Handle properties
    //             foreach ($schema['properties'] as $propertyName => $property) {
    //                 $attribute = new Attribute();
    //                 $attribute->setEntity($entity);
    //                 $attribute->setName($propertyName);
    //                 // Catching arrays
    //                 if (array_key_exists('type', $property) && $property['type'] == 'array' && is_array($property['items']) && count($property['items']) == 1) {
    //                     $attribute->setMultiple(true);
    //                     $property = $property['items']; // @todo this is wierd
    //                     //var_dump($property['items']);
    //                     //var_dump(array_values($property['items'])[0]);
    //                 }
    //                 if (array_key_exists('type', $property)) {
    //                     $attribute->setType($property['type']);
    //                 }
    //                 if (array_key_exists('format', $property)) {
    //                     $attribute->setFormat($property['format']);
    //                 }
    //                 if (array_key_exists('description', $property)) {
    //                     $attribute->setDescription($property['description']);
    //                 }
    //                 if (array_key_exists('enum', $property)) {
    //                     $attribute->setEnum($property['enum']);
    //                 }
    //                 if (array_key_exists('maxLength', $property)) {
    //                     $attribute->setMaxLength((int) $property['maxLength']);
    //                 }
    //                 if (array_key_exists('minLength', $property)) {
    //                     $attribute->setMinLength((int) $property['minLength']);
    //                 }
    //                 //if(array_key_exists('pattern',$property)){ $attribute->setPatern($property['pattern']);} /* @todo hey een oas spec die we niet ondersteunen .... */
    //                 if (array_key_exists('example', $property)) {
    //                     $attribute->setExample($property['example']);
    //                 }
    //                 if (array_key_exists('uniqueItems', $property)) {
    //                     $attribute->setUniqueItems($property['uniqueItems']);
    //                 }

    //                 // Handling required
    //                 if (array_key_exists('required', $schema) && in_array($propertyName, $schema['required'])) {
    //                     $attribute->setRequired(true);
    //                 }

    //                 // Handling external references is a bit more complicated since we can only set the when all the objects are created
    //                 if (array_key_exists('$ref', $property)) {
    //                     $attribute->setRef($property['$ref']);
    //                     $attributeDependencies[] = $attribute;
    //                 }// this is a bit more complicaties;
    //                 $entity->addAttribute($attribute);
    //                 $this->em->persist($attribute);
    //             }
    //             // Handle Gateway
    //             if ($source) {
    //                 $entity->setGateway($source);
    //             }

    //             // Build an array for interlinking schema's
    //             $schemas['#/components/schemas/'.$schemaName] = $entity;
    //             $this->em->persist($entity);
    //         }

    //         foreach ($attributeDependencies as $attribute) {
    //             $attribute->setObject($schemas[$attribute->getRef()]);
    //             $attribute->setType('object');
    //             $this->em->persist($attribute);
    //         }
    //     } else {
    //         // @throw error
    //     }

    //     // Do we have paths?
    //     if (array_key_exists('paths', $oas)) {
    //         // Lets grap the paths
    //         foreach ($oas['paths'] as $path => $info) {
    //             // Let just grap the post for now
    //             if (array_key_exists('post', $info)) {
    //                 $schema = $info['post']['requestBody']['content']['application/json']['schema']['$ref'];
    //                 $schemas[$schema]->setEndpoint($path);
    //             }
    //         }
    //     } else {
    //         // @todo throw error
    //     }

    //     $this->em->flush();

    //     return $oas;
    // }
}
