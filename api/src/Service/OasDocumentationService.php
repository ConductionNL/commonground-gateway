<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Handler;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class OasDocumentationService
{
    private ParameterBagInterface $params;
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private ValidationService $validationService;
    private array $supportedValidators;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, CommonGroundService $commonGroundService, ValidationService $validationService)
    {
        $this->params = $params;
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->validationService = $validationService;

        // Lets define the validator that we support for docummentation right now
        $this->supportedValidators = $this->supportedValidators();

        // Lets define the validator that we support for docummentation right now
        $this->supportedTypes = [
            'string',
            'date',
            'date-time',
            'integer',
            'array',
        ];
    }

    private function supportedValidators(): array
    {
        return [
            'multipleOf',
            'maximum',
            'exclusiveMaximum',
            'minimum',
            'exclusiveMinimum',
            'maxLength',
            'minLength',
            'maxItems',
            'uniqueItems',
            'maxProperties',
            'minProperties',
            'required',
            'enum',
            'allOf',
            'oneOf',
            'anyOf',
            'not',
            'items',
            'additionalProperties',
            'default',
        ];
    }

    /**
     * Places an schema.yaml and schema.json in the /public/eav folder for use by redoc and swagger.
     *
     * @return bool returns true if succcesfull or false on failure
     */
    public function write(array $docs): bool
    {
        // Setup the file system
        $filesystem = new Filesystem();

        // Check if there is a eav folder in the /public folder
        if (!$filesystem->exists('public/eav')) {
            $filesystem->mkdir('public/eav');
        }

        $filesystem->dumpFile('public/eav/schema.json', json_encode($docs, JSON_UNESCAPED_SLASHES));
        $filesystem->dumpFile('public/eav/schema.yaml', Yaml::dump($docs));

        return true;
    }


    /**
     * Generates an OAS3 documentation for the exposed eav entities in the form of an array.
     *
     * @param string $applicationId
     * @return array
     */
    public function getRenderDocumentation(string $applicationId): array
    {
        $docs = [];

        // General info
        $docs['openapi'] = '3.0.3';
        $docs['info'] = $this->getDocumentationInfo();

//        $host = $request->server->get('HTTP_HOST');
//        $path = $request->getPathInfo();

        /* @todo the server should include the base url */
        $docs['servers'] = [
            ['url'=> "test", 'description'=>'Gateway server'],
        ];

        $docs['tags'] = [];

        $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $applicationId]); ///findBy(['expose_in_docs'=>true]);
        $endpoints = $this->em->getRepository('App:Endpoint')->findByApplication($application); ///findBy(['expose_in_docs'=>true]);
//        $endpoints = $this->em->getRepository('App:Endpoint')->findAll(); ///findBy(['expose_in_docs'=>true]);

        foreach ($endpoints as $endpoint) {
            $docs = $this->addEndpointToDocs($endpoint, $docs);
        }

        $this->write($docs);

        return $docs;
    }

    /**
     * Returns info for the getRenderDocumentation function.
     *
     * @return array
     */
    private function getDocumentationInfo(): array
    {
        return [
            'title'         => $this->params->get('documentation_title'),
            'description'   => $this->params->get('documentation_description'),
            'termsOfService'=> $this->params->get('documentation_terms_of_service'),
            'contact'       => [
                'name' => $this->params->get('documentation_contact_name'),
                'url'  => $this->params->get('documentation_contact_url'),
                'email'=> $this->params->get('documentation_contact_email'),
            ],
            'license'=> [
                'name'=> $this->params->get('documentation_licence_name'),
                'url' => $this->params->get('documentation_licence_url'),
            ],
            'version'=> $this->params->get('documentation_version'),
        ];
    }

    /**
     * Generates an OAS3 path for a specific endpoint.
     *
     * @param Endpoint $endpoint
     * @param array  $docs
     *
     * @return array
     */
    public function addEndpointToDocs(Endpoint $endpoint, array &$docs): array
    {
        // Let's only add the main entities as root
        if (!$endpoint->getPath()) {
            return $docs;
        }

        /* @todo fix path */
        $paths = $endpoint->getPath();
        // Let's add the path
        $method = strtolower($endpoint->getMethod());
        $handler = $this->getHandler($endpoint,$method);

        foreach ($paths as $path) {
            if ($path == '{id}') {
                $docs['paths'][$handler->getEntity()->getRoute().'/'.$path][$method] = $this->getEndpointMethod($endpoint, $method, $handler);
            }

            if ($method === 'get' || $method === ' post') {
                $docs['paths'][$handler->getEntity()->getRoute()][$method] = $this->getEndpointMethod($endpoint, $method, $handler);
            }
        }
//        var_dump($paths);

        $docs['components']['schemas'][$handler->getEntity()->getName()] = $this->getSchema($handler->getEntity(), $handler->getMappingOut());


        // @todo remove duplicates from array
        // create the tag
        $docs['tags'][] = [
            'name'       => ucfirst($handler->getEntity()->getName()),
            'description'=> $endpoint->getDescription(),
        ];

        return $docs;
    }

    /**
     * Returns als the allowed methods for an endpoint
     *
     * @param Endpoint $endpoint
     *
     * @return array
     */
    public function getEndpointMethods(Endpoint $endpoint, $handler): array
    {
        $methods = [];
        $method = strtolower($endpoint->getMethod());
        $methods[$method] = $this->getEndpointMethod($endpoint, $method, $handler);

        return $methods;
    }

    /**
     * Gets an OAS description for a specific method
     *
     * @param Endpoint $endpoint
     * @param string $method
     *
     * @return array
     */
    public function getEndpointMethod(Endpoint $endpoint, string $method, $handler): array
    {
        /* @todo name should be cleaned before being used like this */
        $methodArray = [
            "description"=>$endpoint->getDescription(),
            "operationId" => $handler->getEntity()->getName().'_'.$method,
            "tags" => [ucfirst($handler->getEntity()->getName())],
            "summary"=>$endpoint->getDescription(),
            "parameters"=>[],
            "responses"=>[],
        ];

        // Primary Response (success)
//        $responseTypes = ["application/json","application/json-ld","application/json-hal","application/xml","application/yaml","text/csv"];
        $responseTypes = ["application/json","application/json-ld","application/json-hal"]; // @todo this is a short cut, lets focus on json first */
        $response = false;
        switch ($method) {
            case 'get':
                $description = 'OK';
                $response = 200;
                break;
            case 'post':
                $description = 'Created';
                $response = 201;
                break;
            case 'put':
                $description = 'Accepted';
                $response = 202;
                break;
        }

        // Get the handler
        $handler = $this->getHandler($endpoint,$method);
        $methodArray['parameters'] = array_merge($this->getPaginationParameters(), $this->getFilterParameters($handler->getEntity()));

//        // Parameters
//        foreach($endpoint->getPathArray() as $parameter){
//            var_dump($this->getPaginationParameters());
//
//        }
//
//        var_dump($methodArray);

        if($response){
            $methodArray['responses'][$response] = [
                'description' => $description,
                'content' => []
            ];

            foreach($responseTypes as $responseType){
                $schema = $this->getSchema($handler->getEntity(), $handler->getMappingOut());
                $methodArray['responses'][$response]['content'][$responseType]['schema'] = $schema;
            }
        }

        // Let see is we need request bodies
//        $requestTypes = ["application/json","application/xml","application/yaml"];
        $requestTypes = ["application/json"]; // @todo this is a short cut, lets focus on json first */
        if(in_array($method, ['put','post', '*'])){
            foreach($requestTypes as $requestType){
                $schema = $this->getSchema($handler->getEntity(), $handler->getMappingIn());
                $methodArray['requests']['content'][$requestType] = $schema;
            }
        }

        return $methodArray;
    }

    /**
     * Gets a handler for an endpoint method combination
     *
     * @todo i would expect this function to live in the handlerservice
     *
     * @param Endpoint $endpoint
     * @param string $method
     *
     * @return Handler|boolean
     */
    public function getHandler(Endpoint $endpoint, string $method)
    {
        foreach ($endpoint->getHandlers() as $handler) {
            if (in_array( '*',$handler->getMethods())) {
                return $handler;
            }

            // Check if handler should be used for this method
            if (in_array($method,$handler->getMethods())) {
                return $handler;
            }
        }
        return false;
    }

    /**
     * Generates an OAS schema from an entity
     *
     * @param Entity $entity
     * @param array $mapping
     *
     * @return array
     */
    public function getSchema(Entity $entity, array $mapping): array
    {
        $schema = [
            'type'      => 'object',
            'required'  => [],
            'properties'=> [],

        ];

        foreach($entity->getAttributes() as $attribute ){

            // Handle requireded fields
            if ($attribute->getRequired() and $attribute->getRequired() != null) {
                $schema['required'][] = ucfirst($attribute->getName());
            }

            // Add the attribute
            $schema['properties'][$attribute->getName()] = [
                'type'       => $attribute->getType(),
                'title'      => $attribute->getName(),
                'description'=> $attribute->getDescription(),
            ];

            // Setup a mappping array
            $mappingArray = [];
            foreach($mapping as $key => $value){
                // @todo for this exercise this only goes one deep
            }

            // The attribute might be a scheme on its own
            if ($attribute->getObject() && $attribute->getCascade()) {
                /* @todo this might throw errors in the current setup */
                $schema['properties'][$attribute->getName()] = ['$ref'=>'#/components/schemas/'.ucfirst($this->toCamelCase($attribute->getObject()->getName()))];
                // Schema's dont have validators so
                continue;
            } elseif ($attribute->getObject() && !$attribute->getCascade()) {
                $schema['properties'][$attribute->getName()]['type'] = 'string';
                $schema['properties'][$attribute->getName()]['format'] = 'uuid';
                $schema['properties'][$attribute->getName()]['description'] = $schema['properties'][$attribute->getName()]['description'].'The uuid of the ['.$attribute->getObject()->getName().']() object that you want to link, you can unlink objects by setting this field to null';
                // uuids dont have validators so
                continue;
            }

            // Add the validators
            foreach ($attribute->getValidations() as $validator => $validation) {
                if (!array_key_exists($validator, $this->supportedValidators) && $validation != null) {
                    $schema['properties'][$attribute->getName()][$validator] = $validation;
                }
            }

            // Set example data
            /* @todo we need to provide example data for AOS this is iether provided by the atriute itself our should be created trough a function */
            if($attribute->getExample()){
                $schema['properties'][$attribute->getName()]['example']= $attribute->getExample();
            }
            else{
                $schema['properties'][$attribute->getName()]['example'] = $this->generateAttributeExample($attribute);
            }

            // Let do mapping (changing of property names)
            if(array_key_exists($attribute->getName(), $mappingArray)){
                $schema['properties'][$mappingArray][$attribute->getName()] =  $schema['properties'][$attribute->getName()];
                unset($schema['properties'][$attribute->getName()]);
            }

        }

        return $schema;
    }


    /**
     * Generates an OAS example (data) for an atribute)
     *
     * @param Attribute $attribute
     *
     * @return string
     */
    public function generateAttributeExample(Attribute $attribute): string
    {
        // @to use a type and format switch to generate examples */
        return 'string';
    }

    /**
     * Serializes a schema (array) to standard e.g. application/json
     *
     * @param array $schema
     * @param string $type
     *
     * @return array
     */
    public function serializeSchema(array $schema, string $type): array
    {
        // @to use a type and format switch to generate examples */
        switch ($type) {
            case "application/json":
                break;
            case "application/json+ld":
                // @todo add specific elements
                break;
            case "application/json+hal":
                // @todo add specific elements
            case "application/json+orc":
                // @todo add specific elements
            case "application/json+form.io":
                // @todo add specific elements
                break;
            default:
                /* @todo throw error */
        }

        return $schema;
    }


    /**
     * Serializes a collect for a schema (array) to standard e.g. application/json
     *
     * @param Entity $entity
     * @param string $type
     *
     * @return array
     */
    public function getCollectionWrapper(Entity $entity, string $type): array
    {
        // Basic schema setup
        $schema = [
            'type'      => 'object',
            'required'  => [],
            'properties'=> [],

        ];

        switch ($type) {
            case "application/json":
                $schema['properties'] = [
                  'count' =>  ['type'=>'integer'],
                  'next'  =>  ['type'=>'string','format'=>'uri','nullable'=>'true'],
                  'previous' => ['type'=>'string','format'=>'uri','nullable'=>'true'],
                  'results' => ['type'=>'array','items'=>'$ref: #/components/schemas/Klant'] //@todo lets think about how we are going to establish the ref
                ];
                break;
            case "application/json+ld":
                // @todo add specific elements
                break;
            case "application/json+hal":
                // @todo add specific elements
                break;
            default:
                /* @todo throw error */
        }

        return $schema;
    }

    public function getPaginationParameters(): array
    {
        $parameters = [];
        //
        $parameters[] = [
            'name'       => 'start',
            'in'         => 'query',
            'description'=> 'The start number or offset of you list',
            'required'   => false,
            'style'      => 'simple',
        ];
        //
        $parameters[] = [
            'name'       => 'limit',
            'in'         => 'query',
            'description'=> 'the total items pe list/page that you want returned',
            'required'   => false,
            'style'      => 'simple',
        ];
        //
        $parameters[] = [
            'name'       => 'page',
            'in'         => 'query',
            'description'=> 'The page that you want returned',
            'required'   => false,
            'style'      => 'simple',
        ];

        return $parameters;
    }

    public function getFilterParameters(Entity $Entity, string $prefix = '', int $level = 1): array
    {
        $parameters = [];

        // @todo searchable is set to false
        foreach ($Entity->getAttributes() as $attribute) {
            if (in_array($attribute->getType(), ['string', 'date', 'datetime'])) {
                $parameters[] = [
                    'name'       => $prefix.$attribute->getName(),
                    'in'         => 'query',
                    'description'=> 'Search '.$prefix.$attribute->getName().' on an exact match of the string',
                    'required'   => false,
                    'style'      => 'simple',
                ];
            } elseif ($attribute->getObject() && $level < 5) {
                $parameters = array_merge($parameters, $this->getFilterParameters($attribute->getObject(), $attribute->getName().'.', $level + 1));
            }
            continue;
        }

        return $parameters;
    }

    /**
     * Turns a string to CammelCase.
     *
     * @param string $string the string to convert to CamelCase
     *
     * @return string the CamelCase represention of the string
     */
    public function toCamelCase($string, $dontStrip = [])
    {
        /*
         * This will take any dash or underscore turn it into a space, run ucwords against
         * it so it capitalizes the first letter in all words separated by a space then it
         * turns and deletes all spaces.
         */
        return lcfirst(str_replace(' ', '', ucwords(preg_replace('/^a-z0-9'.implode('', $dontStrip).']+/', ' ', $string))));
    }

}
