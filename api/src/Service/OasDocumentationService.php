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
     * Generates an OAS3 documentation for the exposed eav entities in the form of an array.
     *
     * @param string $applicationId
     * @return array
     */
    public function getRenderDocumentation(Request $request, string $applicationId): array
    {
        $docs = [];

        // General info
        $docs['openapi'] = '3.0.3';
        $docs['info'] = $this->getDocumentationInfo();

//        $host = $request->server->get('HTTP_HOST');
//        $path = $request->getPathInfo();

        /* @todo the server should include the base url */
        $docs['servers'] = [
            ['url'=> $request->getBaseUrl(), 'description'=>'Gateway server'],
        ];

        $docs['tags'] = [];

        $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $applicationId]); ///findBy(['expose_in_docs'=>true]);
        $endpoints = $this->em->getRepository('App:Endpoint')->findByApplication($application); ///findBy(['expose_in_docs'=>true]);

        foreach ($endpoints as $endpoint) {
            $docs = $this->addEndpointToDocs($endpoint, $docs);
        }

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
    public function addEndpointToDocs(Endpoint $endpoint, array $docs): array
    {

        /* @todo this only goes one deep */
//        $docs['components']['schemas'][ucfirst($this->toCamelCase($entity->getName()))] = $this->getItemSchema($entity);

        // Let's only add the main entities as root
        if (!$endpoint->getPath()) {
            return $docs;
        }

        // Let's add the path
        $docs['paths'][$endpoint->getPath()] = $this->getEndpointMethods($endpoint);

        // create the tag
        $docs['tags'][] = [
            'name'       => ucfirst($endpoint->getName()),
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
    public function getEndpointMethods(Endpoint $endpoint): array
    {
        $methods = [];
        return $methods[$endpoint->getMethod()] = $this->getEndpointMethod($endpoint, $endpoint->getMethod());
    }

    /**
     * Gets an OAS description for a specific method
     *
     * @param Endpoint $endpoint
     * @param string $method
     *
     * @return array
     */
    public function getEndpointMethod(Endpoint $endpoint, string $method): array
    {

        /* @todo name should be cleaned before being used like this */
        $methodArray = [
            "operationId" => $endpoint->getName().'_'.$method,
            "summary"=>$endpoint->getDescription(),
            "description"=>$endpoint->getDescription(),
            "parameters"=>[],
            "responses"=>[],
        ];

        // Parameters
//        foreach($endpoint->getPathArray() as $parameter){
//
//        }

        // Primary Response (success)
        $responseTypes = ["application/json","application/json-ld","application/json-hal","application/xml","application/yaml","text/csv"];
        $responseTypes = ["application/json","application/json-ld","application/json-hal"]; // @todo this is a short cut, lets focus on json first */

        $response = false;
        switch ($method) {
            case 'GET':
                $description = 'OK';
                $response = 200;
                break;
            case 'POST':
                $description = 'Created';
                $response = 201;
                break;
            case 'PUT':
                $description = 'Accepted';
                $response = 202;
                break;
        }

        // Get the handler
        $handler = $this->getHandler($endpoint,$method);

        if($response){
            $methodArray['responses'][$response] = [
                'description' => $description,
                'content' => []
            ];

            foreach($responseTypes as $responseType){
                $schema = $this->getSchema($handler->getEntity(), $handler->geMappingOut());
                $methodArray['responses'][$response]['content'] = $schema;
            }
        }

        // Let see is we need request bodies
        $requestTypes = ["application/json","application/xml","application/yaml"];
        $requestTypes = ["application/json"]; // @todo this is a short cut, lets focus on json first */
        if(in_array($methodArray, ['put','post'])){
            foreach($requestTypes as $requestType){
                $schema = $this->getSchema($handler->getEntity(), $handler->getMappingIn());
                $methodArray['requests']['content'] = $schema;
            }
        }

        return $methodArray;
    }

    /**
     * Gets an handler for an endpoint method combination
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
            // Check if handler should be used for this method
            if (in_array($method,$handler->getMethods())) {
                return $handler;
            }
        }

        return false;
    }

    /**
     * Generates an OAS schema from an entty
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
                $schema['required'][] = $attribute->getName();
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
                $schema['properties'][$mappingArray[$attribute->getName()]] =  $schema['properties'][$attribute->getName()];
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
                break;
            default:
                /* @todo throw error */

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

}
