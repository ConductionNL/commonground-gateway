<?php

namespace App\Service;

use App\Entity\Gateway;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Yaml\Yaml;

class GatewayDocumentationService
{
    private EntityManagerInterface $em;
    private Client $client;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->client = new Client();
    }

    /**
     * This functions loop trough the available information per gateway to retrieve paths.
     *
     * @return bool returns true if succcesfull or false on failure
     */
    public function getPaths()
    {
        $paths = [];

        foreach ($this->em->getRepository('App:Gateway')->findAll() as $source) {
            if ($source->getDocumentation()) {
                $source = $this->getPathsForGateway($source);

                $this->em->persist($source);
            }
            continue;
        }
        $this->em->flush();

        return $paths;
    }

    /**
     * This functions loop trough the available information per gateway to retrieve paths.
     *
     * @return bool returns true if succcesfull or false on failure
     */
    public function getPathsForGateway(Gateway $source)
    {
        try {
            // Get the docs
            if (empty($source->getOas()) && $source->getDocumentation()) {
                $response = $this->client->get($source->getDocumentation());
                $response = $response->getBody()->getContents();
                // Handle json
                if ($oas = json_decode($response, true)) {
                }
                // back up for yaml
                elseif ($oas = Yaml::parse($response)) {
                }

                $source->setOas($oas);
            }

            $source->setPaths($this->getPathsFromOas($source->getOas()));
        } catch (\Guzzle\Http\Exception\BadResponseException $e) {
            $raw_response = explode("\n", $e->getResponse());

            throw new IDPException(end($raw_response));
        }

        return $source;
    }

    /**
     * Places a schema.yaml and schema.json in the /public/eav folder for use by redoc and swagger.
     *
     * @param array $oas
     *
     * @return array
     */
    public function getPathsFromOas(array $oas): array
    {
        $paths = [];

        // Apperently the are OAS files without paths out there
        if (!array_key_exists('paths', $oas)) {
            return $paths;
        }

        foreach ($oas['paths'] as $path=>$methods) {
            // We dont want to pick up the id endpoint
            if (str_contains($path, '{id}')) {
                continue;
            }

            if (!$this->checkOasMethodsKeys($methods)) {
                continue;
            }

            $schema = $methods['post']['requestBody']['content']['application/json']['schema'];
            // lets pick up on general schemes
            if (array_key_exists('$ref', $schema) && array_key_exists($this->idFromRef($schema['$ref']), $oas['components']['schemas'])) {
                $schema = $oas['components']['schemas'][$this->idFromRef($schema['$ref'])];
            } elseif (array_key_exists('$ref', $schema) && array_key_exists($this->idFromRef($schema['$ref']), $oas['components']['schemas'])) {
                var_dump('referenced schema '.$schema['$ref'].' is not pressent in the components.schemas array');
            }

            $schema = $this->getSchema($oas, $schema);
            $paths[$path] = $schema;
        }

        return $paths;
    }

    /**
     * Checks if the methods array contains what we expect it to contain. For getPathsFromOas().
     *
     * @param array $methods
     *
     * @return bool
     */
    private function checkOasMethodsKeys(array $methods): bool
    {
        // er are going to assume that a post gives the complete opbject, so we are going to use the general post
        if (!array_key_exists('post', $methods)) {
            var_dump('no post method');

            return false;
        }
        if (!array_key_exists('requestBody', $methods['post'])) {
            var_dump('no requestBody in method');

            return false;
        } // Wierd stuf, but a api might not have a requestBody
        if (!array_key_exists('content', $methods['post']['requestBody'])) {
            var_dump('no requestBody in method');

            return false;
        }
        if (!array_key_exists('application/json', $methods['post']['requestBody']['content'])) {
            var_dump('no json schema present');

            return false;
        }
        if (!array_key_exists('schema', $methods['post']['requestBody']['content']['application/json'])) {
            var_dump('no json schema present');

            return false;
        }

        return true;
    }

    /**
     * Places a schema.yaml and schema.json in the /public/eav folder for use by redoc and swagger.
     *
     * @param array $oas
     * @param array $schema
     * @param int   $level  the level of recursion of this function
     *
     * @return array
     */
    public function getSchema(array $oas, array $schema, int $level = 1): array
    {
//        var_dump($level);

        // lets pick up on general schemes
        if (array_key_exists('$ref', $schema)) {
            $schema = $oas['components']['schemas'][$this->idFromRef($schema['$ref'])];
        }

        // Apperently the are schemes without properties.....
        if (!array_key_exists('properties', $schema)) {
            return $schema;
        }

        $schema = $this->checkForSubSchemas($oas, $schema, $level);

        // Any of
        if (array_key_exists('anyOf', $schema)) {
            foreach ($schema['anyOf'] as $anyOfkey=>$value) {
                if (array_key_exists('$ref', $value)) {
                    if ($level > 3) {
                        unset($schema['anyOf'][$anyOfkey]);
                        continue;
                    }
                    $schema['anyOf'][$anyOfkey] = [$this->getSchema($oas, $schema['anyOf'][$anyOfkey], $level + 1)];
                }
            }
        }

        return $schema;
    }

    /**
     * Checks for sub schemas for the getSchema function.
     *
     * @param array $oas
     * @param array $schema
     * @param int   $level  the level of recursion of this function
     *
     * @return array
     */
    private function checkForSubSchemas(array $oas, array $schema, int $level): array
    {
        /* @todo change this to array filter and clear it up*/
        // We need to check for sub schemes
        foreach ($schema['properties'] as $key => $property) {
            // Lets see if we have main stuf
            if (array_key_exists('$ref', $property)) {
                // we only go 5 levels deep
                if ($level > 3) {
                    unset($schema['properties'][$key]);
                    continue;
                }
                $schema['properties'][$key] = $this->getSchema($oas, $property, $level + 1);
            }
            // The schema might also be in an array
            if (array_key_exists('items', $property) && array_key_exists('$ref', $property['items'])) {
                // we only go 5 levels deep
                if ($level > 3) {
                    unset($schema['properties'][$key]['items']);
                    continue;
                }
                $schema['properties'][$key]['items'] = [$this->getSchema($oas, $schema['properties'][$key]['items'], $level + 1)];
            }
            // Any of
            if (array_key_exists('anyOf', $property)) {
                foreach ($property['anyOf'] as $anyOfkey=>$value) {
                    if (array_key_exists('$ref', $value)) {
                        if ($level > 3) {
                            unset($schema['properties'][$key]['anyOf'][$anyOfkey]);
                            continue;
                        }
                        $schema['properties'][$key]['anyOf'][$anyOfkey] = [$this->getSchema($oas, $schema['properties'][$key]['anyOf'][$anyOfkey], $level + 1)];
                    }
                }
            }
        }

        return $schema;
    }

    /**
     * Gets the identifier form a OAS 3 $ref reference.
     *
     * @param string $ref the OAS 3 reference
     *
     * @return string identiefer
     */
    public function idFromRef(string $ref): string
    {
        $id = explode('/', $ref);
        $id = end($id);

        return $id;
    }
}
