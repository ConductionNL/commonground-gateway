<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Yaml\Yaml;
use App\Entity\Entity;
use App\Entity\Attribute;
use App\Entity\Endpoint;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Handler;


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
  public function loadAction(Request $request, string $type, $collectionId): Response
  {
    // // Get CollectionEntity to retrieve OAS from
    // $collectionRepo = $this->entityManager->getRepsitory('App:CollectionEntity');
    // $collection = $collectionRepo->find($collectionId);
    // $url = $collection->getUrl();


    switch ($type) {
      case 'redoc':

        // Check url as query 
        $request->query->get('url') && $url = $request->query->get('url');
        !isset($url) && $request->getContent() && $body = json_decode($request->getContent(), true);
        if (!isset($url) && !isset($body)) return new Response($this->serializer->serialize(['message' => 'No url given in query or body'], 'json'), Response::HTTP_BAD_REQUEST ,['content-type' => 'json']);
        
        // Check url in body
        !isset($url) && isset($body['url']) && $url = $body['url'];
        if (!isset($url)) return new Response($this->serializer->serialize(['message' => 'No url given in query or body'], 'json'), Response::HTTP_BAD_REQUEST ,['content-type' => 'json']);

        // Send GET to fetch redoc
        $client = new Client();
        $response = $client->get($url);
        $redoc = Yaml::parse($response->getBody()->getContents());

        try {
          // Persist yaml to objects
           $this->parseRedoc($redoc);
        } catch (\Exception $e) {
          $errMessage = $this->serializer->serialize([
            'message' => $e->getMessage()
          ], 'json');
        }
        break;
    }

    return new Response(
      isset($errMessage) ? $errMessage : $this->serializer->serialize(['message' => 'Configuration succesfully loaded from: ' . $url], 'json'),
      isset($errMessage) ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK,
      ['content-type' => 'json']
    );
  }

  /**
   * This function reads redoc and parses it into doctrine objects
   */
  private function parseRedoc(array $redoc)
  {

    // $counter = 0;

    // Persist Entities and Attributes
    foreach ($redoc['components']['schemas'] as $entityName => $entityInfo) {

      // $counter = $counter + 1;
      // if ($counter === 10) break; 

      // Continue if we have no properties
      if (!isset($entityInfo['properties'])) continue;

      // Create Entity with entityName
      $newEntity = new Entity();
      $newEntity->setName($entityName);

      // var_dump('Entity name: ' . $entityName);
      // var_dump('______________________________');

      // Persist entity 
      $this->entityManager->persist($newEntity);

      // Loop through properties and create Attributes
      foreach ($entityInfo['properties'] as $propertyName => $property) {
        $newAttribute = new Attribute();
        $newAttribute->setName($propertyName);
        (isset($entityInfo['required']) && in_array($propertyName, $entityInfo['required'])) && $newAttribute->setRequired(true);
        isset($property['description']) && $newAttribute->setDescription($property['description']);
        isset($property['type']) ? $newAttribute->setType($property['type']) : $newAttribute->setType('string');
        isset($property['format']) && $newAttribute->setFormat($property['format']);
        isset($property['readyOnly']) && $newAttribute->setReadOnly($property['readOnly']);
        isset($property['maxLength']) && $newAttribute->setMaxLength($property['maxLength']);
        isset($property['minLength']) && $newAttribute->setMinLength($property['minLength']);
        isset($property['enum']) && $newAttribute->setEnum($property['enum']);
        isset($property['maximum']) && $newAttribute->setMaximum($property['maximum']);
        isset($property['minimum']) && $newAttribute->setMinimum($property['minimum']);
        isset($property['pattern']) && $newAttribute->setPattern($property['pattern']);

        // @TODO set link to object if attribute is object (what if seeked object is not yet created?)
        // if ($property['type'] === 'object') {

        // }

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

      // $pathName === 'resultaten' && var_dump('before added methods');
      // $pathName === 'resultaten' &&  var_dump($methods);
      
      // Add methods for Handler
      foreach ($path as $method => $actualPath) {

        // $pathName === 'resultaten' && var_dump('method');
        // $pathName === 'resultaten' &&  var_dump($method);
        !in_array($method, $methods) && in_array($method, ['get', 'post', 'patch', 'put', 'delete']) &&  $methods[] = $method;
      }
      // $pathName === 'resultaten' && var_dump('after added methods');
      // $pathName === 'resultaten' && var_dump($methods);

      // If we already iterated this pathname update the handler for its methods and continue
      if (in_array($pathName, $iteratedEndpoints)){
          $handlerRepo = $this->entityManager->getRepository('App:Handler');
          $handler = $handlerRepo->findOneByName($pathName . ' Handler');

          if (isset($handler)) {
            $pathName === 'resultaten' && var_dump(array_unique(array_merge($handler->getMethods(), $methods), SORT_REGULAR));
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
      $endpointRepo = $this->entityManager->getRepository('App:Endpoint');
      $existingEndpoint = $endpointRepo->findOneByPath($pathName);

      // var_dump('Existing endpoint for path: ' . $pathName);
      // isset($existingEndpoint[0]) ? var_dump($existingEndpoint[0]->getName()) : var_dump('no endpoint found for: ' . $pathName);
      // var_dump('______________________________');
      
      // If we dont have a existing Endpoint create a new Endpoint
      isset($existingEndpoint) ? $endpoint = $existingEndpoint : $endpoint = new Endpoint(); 

      if (!isset($existingEndpoint)) {
          $endpoint->setName($pathName);
          $endpoint->setPath($pathName);
      }


      // Set description from first method description
      (isset($path[array_key_first($path)]) && isset($path[array_key_first($path)]['description'])) && $endpoint->setDescription($path[array_key_first($path)]['description']);

      // Check reference to schema object to find Entity
      foreach ($path as $key => $response) {
        if ($key !== 'post') continue;
        if (isset($response['requestBody']['content']['application/json']['schema']['$ref'])) {
          $entityNameToJoin = $response['requestBody']['content']['application/json']['schema']['$ref'];
          $entityNameToJoin = substr($entityNameToJoin, strrpos($entityNameToJoin, '/') + 1);;
          break;
        }
      }

      // Check if we can find a entity with this name to link 
      unset($entity);
      if (isset($entityNameToJoin)) {
        $entityRepo = $this->entityManager->getRepository('App:Entity');
        $entity = $entityRepo->findOneByName($entityNameToJoin);
        // var_dump('Found entity: ' . $entityNameToJoin . ' to link to endpoint:');
        // isset($entity) ? var_dump($entity->getName()) : var_dump('no entity found for: ' . $entityNameToJoin);
        // var_dump('______________________________');

        if (isset($entity)) {
          $entity->setRoute('/api/' . $pathName);
          $entity->setEndpoint($pathName);
    
          $this->entityManager->persist($entity);
        }
      }

      // Create Handler
      unset($handler);
      !isset($existingEndpoint) ? $handler = new Handler() : isset($existingEndpoint) && isset($existingEndpoint->getHandlers()[0]) && $handler = $existingEndpoint->getHandlers()[0];

      if (isset($handler)) {
          $handler->setName($pathName . ' Handler');
          // $pathName === 'resultaten' && var_dump('Methods before setting');
          // $pathName === 'resultaten' &&  var_dump($methods);
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

    $this->entityManager->flush();

  }
}
