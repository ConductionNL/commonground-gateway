<?php

namespace App\Service;

use App\Entity\CollectionEntity;
use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Yaml\Yaml;

class ParseDataService
{
    private EntityManagerInterface $entityManager;
    private Client $client;
    private EavService $eavService;
    private ValidationService $validationService;

    /**
     * @const File types supported by the parser
     */
    public const SUPPORTED_FILE_TYPES = ['yaml', 'yml', 'json'];

    /**
     * @param EntityManagerInterface $entityManager
     * @param ValidationService $validationService
     * @param EavService $eavService
     */
    public function __construct(EntityManagerInterface $entityManager, ValidationService $validationService, EavService $eavService)
    {
        $this->entityManager = $entityManager;
        $this->client = new Client();
        $this->eavService = $eavService;
        $this->validationService = $validationService;
    }

    /**
     * Parses a filename to get the extension
     *
     * @param   string  $dataFile   The filename to parse
     * @return  string              The extension of the filename
     */
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

    /**
     * Tries to decipher what kind of data is in the body of a response
     * @param   Response    $response   The response from downloading the external data file
     * @param   string      $dataFile   The filename of the external data file, used if the filetype cannot be decided from the header
     * @return  string                  The file type in extension style
     * @throws  Exception               Thrown if format is not supported
     */
    private function decideFormat(Response $response, string $dataFile): string
    {
        $result = '';

        switch ($response->getHeader('Content-Type')) {
            case 'application/json':
                return 'json';
            case 'application/xml':
                return 'xml';
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
            throw new Exception('Format not supported');
        }

        return $result;
    }

    /**
     * Downloads the datafile and parses it into the format expected by the parser
     *
     * @param   string      $dataFile   The location of the file to parse
     * @return  array                   The data in the data format for the parser
     * @throws  Exception               Thrown if the format of the datafile is not yet supported
     */
    public function findData(string $dataFile): array
    {
        $result = [];
        $response = $this->client->get($dataFile);
        switch ($this->decideFormat($response, $dataFile)) {
            case 'yml':
            case 'yaml':
                $result = Yaml::parse($response->getBody()->getContents());
                break;
            case 'json':
                $result = json_decode($response->getBody()->getContents(), true);
                break;
            default:
                throw new Exception('Format not supported');
        }

        return $result;
    }

    /**
     * Creates objects related to an entity
     *
     * @param   Entity  $entity The entity the objects should relate to
     * @param   array   $schema The data in the object
     * @return  array           The resulting objects
     * @throws  Exception
     */
    public function createObjects(Entity $entity, array $schema): array
    {
        $result = [];
        foreach ($schema as $properties) {
            $object = $this->eavService->getObject(null, 'POST', $entity);
            //TODO: add admin scopes to grantedScopes in the session so this validateEntity function doesn't fail on missing scopes
            $object = $this->validationService->validateEntity($object, $properties['properties']);
            $this->entityManager->persist($object);
            $result[] = $object;
        }

        return $result;
    }

    /**
     * Loads objects into the database that relate to an entity
     *
     * @param   array               $data               The data to load
     * @param   CollectionEntity    $collectionEntity   The collectionEntity the entities should be found in
     * @return  array                                   The resulting objects
     * @throws  Exception                               Thrown if objects cannot be created
     */
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

    /**
     * Bridges some functionality in the validationService that cannot be deduced
     *
     * @return  void
     */
    private function bridgeValidationService(): void
    {
        $this->validationService->setIgnoreErrors(true);
        $mockRequest = new Request();
        $mockRequest->setMethod('POST');
        $this->validationService->setRequest($mockRequest);
    }

    /**
     * Loads data from a specified location
     * @param   string|null     $dataFile   The location of the datafile
     * @param   string          $oas        The OpenAPI Specification the datafile relates to
     * @return  bool                        Whether or not the datafile has been loaded
     * @throws  Exception                   Thrown if OAS locations don't match or no collection is available in the database for the specified oas
     */
    public function loadData(?string $dataFile, string $oas): bool
    {
        if (empty($dataFile)) {
            return false;
        }
        $data = $this->findData($dataFile);
        if ($data['collection'] !== $oas) {
            throw new Exception('OAS locations don\'t match');
        }

        $collection = $this->entityManager->getRepository('App:CollectionEntity')->findOneBy(['locationOAS' => $oas]);
        if (!$collection instanceof CollectionEntity) {
            throw new Exception('Collection not found');
        }
        $results = $this->parseData($data, $collection);

        $this->entityManager->flush();
        return true;
    }
}
