<?php


namespace App\Swagger;

use App\Entity\Gateway;
use Doctrine\Common\Annotations\Reader as AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class GatewayDecorator implements NormalizerInterface
{
    private $decorated;
    private EntityManagerInterface $entityManager;

    public function __construct(NormalizerInterface $decorated, EntityManagerInterface $entityManager)
    {
        $this->decorated = $decorated;
        $this->entityManager = $entityManager;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $docs = $this->decorated->normalize($object, $format, $context);
        $gateways = $this->retrieveGateways();

        if (count($gateways) == 0) {
            return $docs;
        }

        $gateways = $this->processGateways($gateways);

        foreach ($gateways as $gateway) {
            $docs['paths'] = array_merge($docs['paths'], $gateway['paths']);
            $docs['components']['schemas'] = array_merge($docs['components']['schemas'], $gateway['components']);
            $docs['tags'] = array_merge($docs['tags'], $gateway['tags']);
        }

        return $docs;
    }

    public function processGateways(array $gateways): array
    {
        $results = [];
        foreach ($gateways as $gateway) {
            if ($gateway instanceof Gateway && $gateway->getDocumentation() !== null) {
                    $results[] = $this->retrieveDocumentation($gateway);
            }
        }

        return $results;
    }

    public function retrieveDocumentation(Gateway $gateway): array
    {
        $client = new Client();
        $result = [];

        $response = $client->request('GET', $gateway->getDocumentation());
        $type = $this->retrieveDocumentationType($gateway->getDocumentation());

        $array = $this->responseBodyToArray($response->getBody()->getContents(), $type);
        $result['paths'] = $this->handleOpenApiPaths($array['paths'], $gateway);
        $result['components'] = $this->handleOpenApiComponents($array['components'], $gateway);
        $result['tags'] = $this->handleOpenApiTags($array['tags'], $gateway);

        return $result;
    }

    public function handleOpenApiTags(array $tags, Gateway $gateway): array
    {
        foreach ($tags as &$tag){
            $tag['name'] = 'Gateway' . ucfirst($gateway->getName()) . ucfirst($tag['name']);
        }
        return $tags;
    }

    public function handleOpenApiComponents(array $components, Gateway $gateway): array
    {
        $results = [];
        foreach ($components['schemas'] as $key => &$schema) {
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as &$property){
                    if (isset($property['$ref'])) {
                        $exploded = explode('/', $property['$ref']);
                        $tags = explode('-', end($exploded));
                        if (count($tags) == 1) {
                            $tags = explode(':', end($exploded));
                        }
                        $property['$ref'] = str_replace($tags[0], 'Gateway' . ucfirst($gateway->getName()) . ucfirst($tags[0]), $property['$ref']);
                    }

                    if (isset($property['anyOf'][0]['$ref'])) {
                        $exploded = explode('/', $property['anyOf'][0]['$ref']);
                        $tags = explode('-', end($exploded));
                        if (count($tags) == 1) {
                            $tags = explode(':', end($exploded));
                        }
                        $property['anyOf'][0]['$ref'] = str_replace($tags[0], 'Gateway' . ucfirst($gateway->getName()) . ucfirst($tags[0]), $property['anyOf'][0]['$ref']);
                    }

                    if (isset($property['items']['$ref'])) {
                        $exploded = explode('/', $property['items']['$ref']);
                        $tags = explode('-', end($exploded));
                        if (count($tags) == 1) {
                            $tags = explode(':', end($exploded));
                        }
                        $property['items']['$ref'] = str_replace($tags[0], 'Gateway' . ucfirst($gateway->getName()) . ucfirst($tags[0]), $property['items']['$ref']);
                    }
                }
            }
            $results['Gateway' . ucfirst($gateway->getName()) . $key] = $schema;
        }

        return $results;
    }

    public function handleOpenApiPaths(array $paths, Gateway $gateway): array
    {
        $results = [];

        foreach ($paths as $key => $value) {
            foreach ($value as $operationKey => &$operation) {

                if ($operationKey == 'parameters') {
                    continue;
                }

                $tag = $operation['tags'][0];
                $operation['tags'] = [
                    'Gateway' . ucfirst($gateway->getName()) . ucfirst($operation['tags'][0])
                ];

                $operation['parameters'][] = [
                    'name' => 'Prefer',
                    'description' => 'Prefer header',
                    'in' => 'header'
                ];

                if (isset($operation['produces'])) {
                    unset($operation['produces']);
                }
                foreach ($operation['responses'] as &$response) {
                    if (isset($response['content'])) {
                        foreach ($response['content'] as &$item) {
                            if (isset($item['schema']['$ref'])) {
                                $item['schema']['$ref'] = str_replace($tag, 'Gateway' . ucfirst($gateway->getName()) . ucfirst($tag), $item['schema']['$ref']);
                            }
                            if (isset($item['schema']['properties']['hydra:member'])) {
                                $item['schema']['properties']['hydra:member']['items']['$ref'] = str_replace($tag, 'Gateway' . ucfirst($gateway->getName()) . ucfirst($tag), $item['schema']['properties']['hydra:member']['items']['$ref']);
                            }
                            if (isset($item['schema']['items']['$ref'])) {
                                $exploded = explode('/',$item['schema']['items']['$ref']);
                                $tags = explode('-', end($exploded));
                                if (count($tags) == 1) {
                                    $tags = explode(':', end($exploded));
                                }
                                $item['schema']['items']['$ref'] = str_replace($tags[0], 'Gateway' . ucfirst($gateway->getName()) . ucfirst($tags[0]), $item['schema']['items']['$ref']);
                            }
                        }
                    }
                }

                if (isset($operation['requestBody']['content'])) {
                    foreach ($operation['requestBody']['content'] as &$content) {
                        $content['schema']['$ref'] = str_replace($tag, 'Gateway' . ucfirst($gateway->getName()) . ucfirst($tag), $content['schema']['$ref']);

                    }
                }
            }

            $results['/gateways/' . $gateway->getName() . $key] = $value;
        }

        return $results;
    }

    public function responseBodyToArray(string $responseBody, string $type): array
    {
        switch ($type) {
            case 'json':
                return json_decode($responseBody, true);
                break;
            default:
                return Yaml::parse($responseBody);
                break;
        }
    }

    public function retrieveDocumentationType($documentationUrl): string
    {
        $exploded = explode('.', $documentationUrl);

        if (count($exploded) == 1) {
            throw new BadRequestException('No file extension');
        } else {
            return end($exploded);
        }
    }

    public function retrieveGateways(): array
    {
        return $this->entityManager->getRepository('App\Entity\Gateway')->findAll();
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }
}
