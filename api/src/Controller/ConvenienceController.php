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
     * @Route("/admin/load/{type}", name="dynamic_route_load_type")
     */
    public function loadAction(Request $request, string $type): Response {
      switch ($type) {
        case 'redoc':
          $request->query->get('url') ? $url = $request->query->get('url') : $errMsg = 'No url given';
          if ($url) {
            $client = new Client();
            $response = $client->get($url);
            $redoc = Yaml::parse($response->getBody()->getContents());
            try {
              $this->persistRedoc($redoc);
            } catch (\Exception $e) {
              $errMessage = $this->serializer->serialize([
                'message' => $e
              ], 'json');
            }
          }
          break;
      }

      return new Response(
        isset($errMessage) ? $errMessage : $this->serializer->serialize(['message' => 'Configuration succesfully loaded from: ' . $url], 'json'),
        isset($errMessage) ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK,
        ['content-type' => 'json']
    );;
    }

    private function persistRedoc(array $redoc) {

      // Persist Entities and Attributes
      foreach ($redoc['components']['schemas'] as $entityName => $entityInfo) {
        // Continue if we have no properties
        if (!isset($entityInfo['properties'])) continue;

        // Create Entity with entityName
        $newEntity = new Entity();
        $newEntity->setName($entityName);

        $this->entityManager->persist($newEntity);

        // Loop through properties and create Attribute(s)
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

            $newAttribute->setEntity($newEntity);

            // Persist attribute
            $this->entityManager->persist($newAttribute);
        }
      }

      // Persist Endpoints
      foreach ($redoc['paths'] as $pathName => $path) {
        $newEndpoint = new Endpoint();
        $newEndpoint->setName($pathName);
        $newEndpoint->setPath($pathName);

        // Set description from first method description
        (isset($path[array_key_first($path)]) && isset($path[array_key_first($path)]['description'])) && $newEndpoint->setDescription($path[array_key_first($path)]['description']);

        $this->entityManager->persist($newEndpoint);
      }

      $this->entityManager->flush();
    }
}
