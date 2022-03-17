<?php

namespace App\Controller;

use App\Entity\Attribute;
use App\Entity\CollectionEntity;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Handler;
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

        // $counter = 0;

        // These attributes can only be set when entities are flushed, otherwise they cant find eachother, so these will be persisted at the end of the code
        $attributesToSetLaterAsObject = [];

        // Persist Entities and Attributes
        foreach ($redoc['components']['schemas'] as $entityName => $entityInfo) {

      // $counter = $counter + 1;
            // if ($counter === 10) break;

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

        // Flush entities
        $this->entityManager->flush();

        // Persist Endpoints
        // $counter = 0;
        $iteratedEndpoints = [];
        foreach ($redoc['paths'] as $pathName => $path) {
            // $counter = $counter + 1;
            // if ($counter === 10) break;

            $pathName[0] === '/' && $pathName = substr($pathName, 1);
            $pathName = strtok($pathName, '/');

            // Continue with methods from last iteration if same pathname
            end($iteratedEndpoints) !== $pathName && $methods = [];

            // Add methods for Handler
            foreach ($path as $method => $actualPath) {
                !in_array($method, $methods) && in_array($method, ['get', 'post', 'patch', 'put', 'delete']) && $methods[] = $method;
            }

            // If we already iterated this pathname update the handler for its methods and continue
            if (in_array($pathName, $iteratedEndpoints)) {
                $handler = $handlerRepo->findOneByName($pathName.' Handler');

                if (isset($handler)) {
                    $newMethods = [];
                    foreach (array_unique(array_merge($handler->getMethods(), $methods), SORT_REGULAR) as $meth) {
                        $newMethods[] = $meth;
                    }
                    $methods = $newMethods;
                    $handler->setMethods($methods);
                    $this->entityManager->persist($handler);
                    unset($handler);
                }

                continue;
            }

            // Add pathName to iteratedEndpoints
            $iteratedEndpoints[] = $pathName;

            // Search for exisiting Endpoint with this path and check if this is no subpath
            unset($existingEndpoint);
            $existingEndpoint = $endpointRepo->findOneByPath($pathName);
            // If we dont have a existing Endpoint create a new Endpoint
            isset($existingEndpoint) ? $endpoint = $existingEndpoint : $endpoint = new Endpoint();

            $endpoint->addCollection($collection);

            if (!isset($existingEndpoint)) {
                $endpoint->setName($pathName);
                $endpoint->setPath($pathName);
            }

            // Set description from first method description
            (isset($path[array_key_first($path)]) && isset($path[array_key_first($path)]['description'])) && $endpoint->setDescription($path[array_key_first($path)]['description']);

            // Check reference to schema object to find Entity
            foreach ($path as $key => $response) {
                if ($key !== 'post') {
                    continue;
                }
                if (isset($response['requestBody']['content']['application/json']['schema']['$ref'])) {
                    $entityNameToJoin = $response['requestBody']['content']['application/json']['schema']['$ref'];
                    $entityNameToJoin = substr($entityNameToJoin, strrpos($entityNameToJoin, '/') + 1);
                    break;
                }
            }

            // Check if we can find a entity with this name to link
            unset($entity);
            if (isset($entityNameToJoin)) {
                $entity = $entityRepo->findOneByName($entityNameToJoin);

                if (isset($entity)) {
                    $entity->setRoute('/api/'.$pathName);
                    $entity->setEndpoint($pathName);

                    $this->entityManager->persist($entity);
                }
            }

            // Create Handler
            unset($handler);
            !isset($existingEndpoint) ? $handler = new Handler() : isset($existingEndpoint) && isset($existingEndpoint->getHandlers()[0]) && $handler = $existingEndpoint->getHandlers()[0];

            if (isset($handler)) {
                $handler->setName($pathName.' Handler');
                $handler->setMethods($methods);
                $handler->setSequence(0);
                $handler->setConditions('{}');

                isset($entity) && $handler->setEntity($entity);

                $endpoint->addHandler($handler);

                $this->entityManager->persist($handler);
            }

            // Add handler with this method

            $this->entityManager->persist($endpoint);
        }

        // Execute sql to database
        $this->entityManager->flush();

        foreach ($attributesToSetLaterAsObject as $attribute) {
            $newAttribute = new Attribute();
            $newAttribute->setName($attribute['name']);
            $parentEntity = $entityRepo->find($attribute['parentEntityId']);
            isset($parentEntity) && $newAttribute->setEntity($parentEntity);

            $entityToLinkTo = $entityRepo->findByName($attribute['entityNameToLinkTo']);
            isset($entityToLinkTo[0]) && $newAttribute->setType('object') && $newAttribute->setObject($entityToLinkTo[0]);

            // If we have set attribute.entity and attribute.object, persist attribute
            $newAttribute->getObject() !== null && $newAttribute->getEntity() !== null && $this->entityManager->persist($newAttribute);
        }
        // Execute sql to database
        $this->entityManager->flush();
    }
}
