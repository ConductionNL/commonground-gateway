<?php

namespace App\Service;

use App\Entity\CollectionEntity;
use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

class ParseDataService
{
    private EntityManagerInterface $entityManager;
    private Client $client;
    private EavService $eavService;
    private ValidationService $validationService;

    public const SUPPORTED_FILE_TYPES = ['yaml', 'yml'];

    public function __construct(EntityManagerInterface $entityManager, ValidationService $validationService, EavService $eavService)
    {
        $this->entityManager = $entityManager;
        $this->client = new Client();
        $this->eavService = $eavService;
        $this->validationService = $validationService;
    }

    private function getFiletypeOnExtension(string $dataFile): string
    {
        $result = '';

        foreach (self::SUPPORTED_FILE_TYPES as $type) {
            if (strpos($dataFile, $type) !== 0) {
                return $type;
            }
        }

        return $result;
    }

    private function decideFormat(Response $response, string $dataFile): string
    {
        $result = '';

        switch ($response->getHeader('Content-Type')) {
            case 'application/json':
                return 'json';
            case 'text/x-yaml':
                return 'yaml';
            case 'text/csv':
                return 'csv';
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                return 'xlsx';
            default:
                break;
        }

        $result = $this->getFiletypeOnExtension($dataFile);

        if (!$result) {
            throw new \Exception('Format not supported');
        }

        return $result;
    }

    private function parseYamlFile(string $data): array
    {
        return Yaml::parse($data);
    }

    public function findData(string $dataFile): array
    {
        $result = [];
        $response = $this->client->get($dataFile);
        switch ($this->decideFormat($response, $dataFile)) {
            case 'yml':
            case 'yaml':
                $result = $this->parseYamlFile($response->getBody()->getContents());
                break;
            default:
                throw new \Exception('Format not supported');
        }

        return $result;
    }

    public function createObjects(Entity $entity, array $schema): array
    {
        $result = [];
        foreach ($schema as $properties) {
            $object = $this->eavService->getObject(null, 'POST', $entity);
            $object = $this->validationService->validateEntity($object, $properties['properties']);
            var_dump($object->getAllErrors());
            $this->entityManager->persist($object);
            $result[] = $object;
        }

        return $result;
    }

    public function parseData(array $data, CollectionEntity $collectionEntity): array
    {
        $result = [];
        foreach ($collectionEntity->getEntities() as $entity) {
            if (array_key_exists($entity->getName(), $data['schemas'])) {
                $result = array_merge($result, $this->createObjects($entity, $data['schemas'][$entity->getName()]));
            }
        }

        return $result;
    }

    public function loadData(?string $dataFile, string $oas): bool
    {
        if (empty($dataFile)) {
            return false;
        }

        $data = $this->findData($dataFile);
        if ($data['collection'] !== $oas) {
            throw new \Exception('OAS locations don\'t match');
        }
        $mockRequest = new Request();
        $mockRequest->setMethod('POST');

        $this->validationService->setRequest($mockRequest);

        $collection = $this->entityManager->getRepository('App:CollectionEntity')->findOneBy(['locationOAS' => $oas]);
        if (!$collection instanceof CollectionEntity) {
            throw new \Exception('Collection not found');
        }
        $results = $this->parseData($data, $collection);

        $this->entityManager->flush();

        return true;
    }
}
