<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\Endpoint;
use App\Entity\Gateway;
use App\Entity\Property;
use App\Entity\Handler;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Yaml\Yaml;
use App\Entity\CollectionEntity;

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
        $objectsToCreateLater = $this->persistSchemasAsEntities($redoc, $collection);

        // Persist Endpoints and its Properties
        $objectsToCreateLater['handlers'] = $this->persistPathsAsEndpoints($redoc, $collection, $objectsToCreateLater['handlers']);

        // Create Attributes that are references to other Entities
        foreach ($objectsToCreateLater['attributes'] as $attribute) {
            $newAttribute = new Attribute();
            $newAttribute->setName($attribute['name']);
            $parentEntity = $this->entityRepo->find($attribute['parentEntityId']);
            isset($parentEntity) && $newAttribute->setEntity($parentEntity);

            $entityToLinkTo = $this->entityRepo->findByName($attribute['entityNameToLinkTo']);
            isset($entityToLinkTo[0]) && $newAttribute->setType('object') && $newAttribute->setObject($entityToLinkTo[0]);

            // If we have set attribute.entity and attribute.object, persist attribute
            $newAttribute->getObject() !== null && $newAttribute->getEntity() !== null && $this->entityManager->persist($newAttribute);
        }

        $this->entityManager->flush();
        
        // Create Handlers between the Entities and Endpoints
        // var_dump($objectsToCreateLater['handlers']);
        foreach ($objectsToCreateLater['handlers'] as $entityName => $handler) {
            if (!isset($handler['endpoints']) || !isset($handler['entity'])) continue;
            $newHandler = new Handler();
            $newHandler->setName($entityName . ' handler');
            $newHandler->setSequence(0);
            $newHandler->setConditions('{}');
            isset($handler['methods']) && $newHandler->setMethods($handler['methods']);
            $newHandler->setEntity($handler['entity']);
            foreach ($handler['endpoints'] as $endpoint) {
                $newHandler->addEndpoint($endpoint);
            }

            $this->entityManager->persist($newHandler);
        }

        // Execute sql to database
        $this->entityManager->flush();
    }

    /**
     * This function reads redoc and persists it into Entity objects.
     */
    private function persistSchemasAsEntities(array $redoc, CollectionEntity $collection): array
    {
        // These attributes can only be set when entities are flushed, otherwise they cant find eachother, so these will be persisted at the end of the code
        $attributesToSetLaterAsObject = [];
        $handlersToSetLaterAsObject = [];
        foreach ($redoc['components']['schemas'] as $entityName => $entityInfo) {
          // if ($entityName !== 'IngeschrevenPersoonHal' && $entityName !== 'IngeschrevenPersoonHal' && $entityName !== 'IngeschrevenPersoonHalBasis'  && $entityName !== 'IngeschrevenPersoon') continue;

            // Create Entity with entityName
            $newEntity = new Entity();
            $newEntity->setName($entityName);
            $newEntity->addCollection($collection);
            $collection->getSource() !== null && $newEntity->setGateway($collection->getSource());

            // Persist entity
            $this->entityManager->persist($newEntity);

            $handlersToSetLaterAsObject[$entityName]['entity'] = $newEntity; 

            // Loop through allOf and create Attributes
            if (isset($entityInfo['allOf'])) {
                foreach ($entityInfo['allOf'] as $propertyName => $property) {
                    $attributesToSetLaterAsObject = $this->createAttribute($property, $propertyName, $newEntity, $attributesToSetLaterAsObject);
                }
            }

            // Loop through properties and create Attributes
            if (isset($entityInfo['properties'])) {
                foreach ($entityInfo['properties'] as $propertyName => $property) {
                    $attributesToSetLaterAsObject = $this->createAttribute($property, $propertyName, $newEntity, $attributesToSetLaterAsObject);
                }
            }    
        }

        $this->entityManager->flush();
        return ['attributes' => $attributesToSetLaterAsObject, 'handlers' => $handlersToSetLaterAsObject];
    }

    /**
     * This function creates a Attribute from a OAS property
     */
    private function createAttribute(array $property, string $propertyName, Entity $newEntity, array $attributesToSetLaterAsObject): array
    {
        // Check reference to other object
        if (isset($property['$ref'])) {
            $attrToSetLater = [];
            $attrToSetLater['entityNameToLinkTo'] = substr($property['$ref'], strrpos($property['$ref'], '/') + 1);
            if (is_numeric($propertyName)) {
                $attrToSetLater['name'] = lcfirst($attrToSetLater['entityNameToLinkTo']);
            } else {
                $attrToSetLater['name'] = $propertyName;
            }
            $attrToSetLater['parentEntityId'] = $newEntity->getId();
            $attributesToSetLaterAsObject[] = $attrToSetLater;

            // Continue to next iteration as this attribute will be saved later.
            return $attributesToSetLaterAsObject;
        }

        $newAttribute = new Attribute();
        $newAttribute->setName($propertyName);

        (isset($entityInfo['required']) && in_array($propertyName, $entityInfo['required'])) && $newAttribute->setRequired(true);
        isset($property['description']) && $newAttribute->setDescription($property['description']);
        isset($property['type']) ? $newAttribute->setType($property['type']) : $newAttribute->setType('string');
        // If format == date-time set type: datetime
        isset($property['format']) && $property['format'] === 'date-time' && $newAttribute->setType('datetime');
        isset($property['format']) && $property['format'] !== 'date-time' && $newAttribute->setFormat($property['format']);
        isset($property['readyOnly']) && $newAttribute->setReadOnly($property['readOnly']);
        isset($property['maxLength']) && $newAttribute->setMaxLength($property['maxLength']);
        isset($property['minLength']) && $newAttribute->setMinLength($property['minLength']);
        isset($property['enum']) && $newAttribute->setEnum($property['enum']);
        isset($property['maximum']) && $newAttribute->setMaximum($property['maximum']);
        isset($property['minimum']) && $newAttribute->setMinimum($property['minimum']);
        // @TODO do something with pattern
        // isset($property['pattern']) && $newAttribute->setPattern($property['pattern']);
        isset($property['readOnly']) && $newAttribute->setPattern($property['readOnly']);

        $newAttribute->setEntity($newEntity);

        // Persist attribute
        $this->entityManager->persist($newAttribute);

        return $attributesToSetLaterAsObject;
    }

    /**
     * This function reads redoc and persists it into Endpoints objects.
     */
    private function persistPathsAsEndpoints(array $redoc, CollectionEntity $collection, array $handlersToCreateLater): array
    {
        foreach ($redoc['paths'] as $pathName => $path) {
            // if ($pathName !== '/ingeschrevenpersonen/{burgerservicenummer}') continue;

            foreach ($path as $methodName => $method) {
                $newEndpoint = new Endpoint();
                $newEndpoint->addCollection($collection);
                $newEndpoint->setName($pathName . ' ' . $methodName);
                $newEndpoint->setPath($pathName);
                isset($method['description']) && $newEndpoint->setDescription($method['description']);
                isset($method['tags']) && $newEndpoint->setTags($method['tags']);

                // Checks pathName if there are Properties that need to be created and sets Endpoint.operationType
                $createdPropertiesInfo = $this->createEndpointsProperties($redoc, $pathName, $newEndpoint);
                $newEndpoint->setOperationType($createdPropertiesInfo['operationType']);

                $this->entityManager->persist($newEndpoint);

                foreach ($method['responses'] as $response) {
                    foreach ($response['content'] as $content) {

                        // Use json definition
                        if (isset($response['content']['application/json'])) {
                            $entityNameToLinkTo = substr($response['content']['application/json']['schema']['$ref'], strrpos($response['content']['application/json']['schema']['$ref'], '/') + 1); 
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
                        'name' => $oasParameter['name']
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
