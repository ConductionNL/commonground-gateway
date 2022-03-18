<?php

namespace App\Controller;

use App\Entity\Attribute;
use App\Entity\CollectionEntity;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Handler;
use App\Entity\Property;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

class ConvenienceController extends AbstractController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/admin/load/{collectionId}", name="dynamic_route_load_type")
     */
    public function loadAction(Request $request, string $collectionId): Response
    {
        // Get CollectionEntity to retrieve OAS from
        $collectionRepo = $this->entityManager->getRepository('App:CollectionEntity');
        $collection = $collectionRepo->find($collectionId);

        // Check collection, if not found throw error
        if (!isset($collection)) {
            return new Response($this->serializer->serialize(['message' => 'No collection found with given id: '.$collectionId], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // Check collection->source->locationOAS and set url
        $collection->getLocationOAS() !== null && $url = $collection->getLocationOAS();

        // Check url, if not set throw error
        if (!isset($url)) {
            return new Response($this->serializer->serialize(['message' => 'No location OAS found for given collection'], 'json'), Response::HTTP_BAD_REQUEST, ['content-type' => 'json']);
        }

        // // Check url as query
        // $request->query->get('url') && $url = $request->query->get('url');
        // !isset($url) && $request->getContent() && $body = json_decode($request->getContent(), true);
        // if (!isset($url) && !isset($body)) return new Response($this->serializer->serialize(['message' => 'No url given in query or body'], 'json'), Response::HTTP_BAD_REQUEST ,['content-type' => 'json']);

        // // Check url in body
        // !isset($url) && isset($body['url']) && $url = $body['url'];
        // if (!isset($url)) return new Response($this->serializer->serialize(['message' => 'No url given in query or body'], 'json'), Response::HTTP_BAD_REQUEST ,['content-type' => 'json']);

        // Send GET to fetch redoc
        $client = new Client();
        $response = $client->get($url);
        $redoc = Yaml::parse($response->getBody()->getContents());

        try {
            // Persist yaml to objects
            $this->parseRedoc($redoc, $collection);
        } catch (\Exception $e) {
            return new Response(
                $this->serializer->serialize(['message' => $e->getMessage()], 'json'),
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'json']
            );
        }

        // Set synced at
        $collection->setSyncedAt(new \DateTime('now'));
        $this->entityManager->persist($collection);
        $this->entityManager->flush();

        return new Response(
            $this->serializer->serialize(['message' => 'Configuration succesfully loaded from: '.$url], 'json'),
            Response::HTTP_OK,
            ['content-type' => 'json']
        );
    }

    /**
     * This function reads redoc and parses it into doctrine objects.
     */
    private function parseRedoc(array $redoc, CollectionEntity $collection)
    {
        $entityRepo = $this->entityManager->getRepository('App:Entity');
        $handlerRepo = $this->entityManager->getRepository('App:Handler');
        $endpointRepo = $this->entityManager->getRepository('App:Endpoint');

        // Persist Entities and Attributes
        // $attributesToSetLaterAsObject = $this->persistSchemasAsEntities($redoc, $collection);

        // Persist Endpoints and its Properties
        $this->persistPathsAsEndpoints($redoc, $collection);

        // foreach ($attributesToSetLaterAsObject as $attribute) {
        //     $newAttribute = new Attribute();
        //     $newAttribute->setName($attribute['name']);
        //     $parentEntity = $entityRepo->find($attribute['parentEntityId']);
        //     isset($parentEntity) && $newAttribute->setEntity($parentEntity);

        //     $entityToLinkTo = $entityRepo->findByName($attribute['entityNameToLinkTo']);
        //     isset($entityToLinkTo[0]) && $newAttribute->setType('object') && $newAttribute->setObject($entityToLinkTo[0]);

        //     // If we have set attribute.entity and attribute.object, persist attribute
        //     $newAttribute->getObject() !== null && $newAttribute->getEntity() !== null && $this->entityManager->persist($newAttribute);
        // }

        // // Execute sql to database
        // $this->entityManager->flush();
    }

    /**
     * This function reads redoc and persists it into Entity objects.
     */
    private function persistSchemasAsEntities(array $redoc, CollectionEntity $collection): array
    {
        // These attributes can only be set when entities are flushed, otherwise they cant find eachother, so these will be persisted at the end of the code
        $attributesToSetLaterAsObject = [];
        foreach ($redoc['components']['schemas'] as $entityName => $entityInfo) {

            // Continue if we have no properties
            if (!isset($entityInfo['properties'])) {
                continue;
            }

            // Create Entity with entityName
            $newEntity = new Entity();
            $newEntity->setName($entityName);
            $newEntity->addCollection($collection);
            $collection->getSource() !== null && $newEntity->setGateway($collection->getSource());

            // Persist entity
            $this->entityManager->persist($newEntity);

            // Loop through properties and create Attributes
            foreach ($entityInfo['properties'] as $propertyName => $property) {

                // @TODO set link to object if attribute is object (what if seeked object is not yet created?) and property['$ref'] is set
                if (isset($property['$ref'])) {
                    $attrToSetLater = [];
                    $attrToSetLater['name'] = $propertyName;
                    $attrToSetLater['parentEntityId'] = $newEntity->getId();
                    $attrToSetLater['entityNameToLinkTo'] = substr($property['$ref'], strrpos($property['$ref'], '/') + 1);
                    $attributesToSetLaterAsObject[] = $attrToSetLater;

                    // Continue to next iteration as this attribute will be saved later.
                    continue;
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
            }
        }
        $this->entityManager->flush();
        return $attributesToSetLaterAsObject;
    }

    /**
     * This function reads redoc and persists it into Endpoints objects.
     */
    private function persistPathsAsEndpoints(array $redoc, CollectionEntity $collection): void
    {
        foreach ($redoc['paths'] as $pathName => $path) {
            if ($pathName !== '/ingeschrevenpersonen/{burgerservicenummer}') continue;

            // Skip endpoints with subpaths
            if (strpos($pathName, '/', strpos($pathName, '/') + 1) !== false) {
                continue;
            }

                    // Checks pathName if there are Properties that need to be created and sets Endpoint.operationType
                    $newEndpoint->setOperationType($this->createEndpointsProperties($redoc, $pathName, $newEndpoint));

                    $this->entityManager->persist($newEndpoint);
                }

                // Find entity by tag
                // if (isset($path['tags'][0])) {
                //   $entity = $entityRepo->findOneByName(trim($path['tags'][0], ' '));

                  // if (isset($entity)) {
                  //     $entity->setRoute('/api'.$pathName);
                  //     $entity->setEndpoint($pathName);

                  //     $this->entityManager->persist($entity);
                  // }
                // }

                // // Check if we can find a entity with this name to link
                // unset($entity);
                // if (isset($entityNameToJoin)) {
                //     $entity = $entityRepo->findOneByName($entityNameToJoin);

                //     if (isset($entity)) {
                //         $entity->setRoute('/api/'.$pathName);
                //         $entity->setEndpoint($pathName);

                //         $this->entityManager->persist($entity);
                //     }
                // }

                // Create Handler
                // unset($handler);
                // !isset($existingEndpoint) ? $handler = new Handler() : isset($existingEndpoint) && isset($existingEndpoint->getHandlers()[0]) && $handler = $existingEndpoint->getHandlers()[0];

                // if (isset($handler)) {
                //     $handler->setName($pathName.' Handler');
                //     $handler->setMethods(["*"]);
                //     $handler->setSequence(0);
                //     $handler->setConditions('{}');

                //     isset($entity) && $handler->setEntity($entity);

                //     $newEndpoint->addHandler($handler);

                    // $this->entityManager->persist($handler);
                // }

                // Add handler with this method

        }

        // Execute sql to database
        $this->entityManager->flush();
    }


    /**
     * This function reads redoc and persists it into Properties of an Endpoint.
     * 
     * @return string Endpoint.operationType based on if the last Property is a identifier or not.
     */
    private function createEndpointsProperties(array $redoc, string $pathName, Endpoint $newEndpoint): string
    {
        // Check for subpaths and variables
        $partsOfPath = array_filter(explode('/', $pathName));
        $endpointOperationType = 'collection';
        $createdProperties = 0;
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

                        $propertyToPersist->setPathOrder($newEndpoint->getProperties()->count() + $createdProperties);
                    }

                    // Set Endpoint and persist Property
                    $propertyToPersist->setEndpoint($newEndpoint);
                    $this->entityManager->persist($propertyToPersist);

                    // Created properties + 1 for property.pathOrder
                    $createdProperties++;
                }
            } elseif ($property === '{id}' || $property === '{uuid}') {
                $endpointOperationType = 'item';
            } else {
                $endpointOperationType = 'collection';
            }
        }

        return $endpointOperationType;
    }
}
