<?php

namespace App\Swagger;

use App\Entity\Gateway;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
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
                try {
                    $results[] = $this->retrieveDocumentation($gateway);
                } catch (\Throwable $e) {
                    continue;
                }
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

        if (isset($array['tags'])) {
            $result['tags'] = $this->handleOpenApiTags($array['tags'], $gateway);
        } else {
            $result['tags'] = [];
        }

        return $result;
    }

    public function handleOpenApiTags(array $tags, Gateway $gateway): array
    {
        foreach ($tags as &$tag) {
            $tag['name'] = 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag['name']);
        }

        return $tags;
    }

    public function handleArray(array $items, Gateway $gateway): array
    {
        if (isset($items['$ref'])) {
            $tag = $this->handleTag($items['$ref']);
            $items['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $items['$ref']);

            return $items;
        } elseif (isset($items['items'])) {
            $this->handleArray($items['items'], $gateway);
        }

        return $items;
    }

    public function handleOpenApiComponents(array $components, Gateway $gateway): array
    {
        $results = [];
        if (isset($components['schemas'])) {
            foreach ($components['schemas'] as $key => &$schema) {
                if (isset($schema['allOf'])) {
                    foreach ($schema['allOf'] as &$item) {
                        if (isset($item['$ref'])) {
                            $tag = $this->handleTag($item['$ref']);
                            $item['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $item['$ref']);
                        }
                        if (isset($item['properties']['coordinates']['$ref'])) {
                            $tag = $this->handleTag($item['properties']['coordinates']['$ref']);
                            $item['properties']['coordinates']['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $item['properties']['coordinates']['$ref']);
                        }
                        if (isset($item['properties']['coordinates']['items'])) {
                            $item['properties']['coordinates']['items'] = $this->handleArray($item['properties']['coordinates']['items'], $gateway);
                        }
                    }
                }
                if (isset($schema['properties'])) {
                    foreach ($schema['properties'] as $propKey => &$property) {
                        if (isset($property['$ref'])) {
                            $tag = $this->handleTag($property['$ref']);
                            $property['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $property['$ref']);
                        }

                        if (isset($property['anyOf'][0]['$ref'])) {
                            $tag = $this->handleTag($property['anyOf'][0]['$ref']);
                            $property['anyOf'][0]['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $property['anyOf'][0]['$ref']);
                        }

                        if (isset($property['items']['$ref'])) {
                            $tag = $this->handleTag($property['items']['$ref']);
                            $property['items']['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $property['items']['$ref']);
                        }
                    }
                }
                $results['Gateway'.ucfirst($gateway->getName()).$key] = $schema;
            }
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

                if (isset($operation['tags'][0])) {
                    $operation['tags'] = [
                        'Gateway'.ucfirst($gateway->getName()).ucfirst($operation['tags'][0]),
                    ];
                }

                $operation['parameters'][] = [
                    'name'        => 'Prefer',
                    'description' => 'Prefer header',
                    'in'          => 'header',
                ];

                if (isset($operation['produces'])) {
                    unset($operation['produces']);
                }
                foreach ($operation['responses'] as &$response) {
                    if (isset($response['$ref'])) {
                        $tag = $this->handleTag($response['$ref']);
                        $response['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $response['$ref']);
                    }
                    if (isset($response['content'])) {
                        foreach ($response['content'] as &$item) {
                            if (isset($item['schema']['$ref'])) {
                                $tag = $this->handleTag($item['schema']['$ref']);
                                $item['schema']['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $item['schema']['$ref']);
                            }
                            if (isset($item['schema']['properties']['hydra:member'])) {
                                $tag = $this->handleTag($item['schema']['properties']['hydra:member']['items']['$ref']);
                                $item['schema']['properties']['hydra:member']['items']['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $item['schema']['properties']['hydra:member']['items']['$ref']);
                            }
                            if (isset($item['schema']['items']['$ref'])) {
                                $tag = $this->handleTag($item['schema']['items']['$ref']);
                                $item['schema']['items']['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $item['schema']['items']['$ref']);
                            }
                            if (isset($item['schema']['properties']['results']['items']['$ref'])) {
                                $tag = $this->handleTag($item['schema']['properties']['results']['items']['$ref']);
                                $item['schema']['properties']['results']['items']['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $item['schema']['properties']['results']['items']['$ref']);
                            }
                        }
                    }
                }

                if (isset($operation['requestBody']['content'])) {
                    foreach ($operation['requestBody']['content'] as &$content) {
                        if (isset($content['schema']['$ref'])) {
                            $tag = $this->handleTag($content['schema']['$ref']);
                            $content['schema']['$ref'] = str_replace($tag, 'Gateway'.ucfirst($gateway->getName()).ucfirst($tag), $content['schema']['$ref']);
                        }
                    }
                }
            }

            $results['/gateways/'.$gateway->getName().$key] = $value;
        }

        return $results;
    }

    public function handleTag($data)
    {
        $exploded = explode('/', $data);
        $tags = explode('-', end($exploded));
        $tag = $tags[0];
        if (count($tags) == 1) {
            $tag = explode(':', end($exploded))[0];
        }

        return $tag;
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
