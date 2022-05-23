<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Endpoint;
use Cassandra\Uuid;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class OasDocumentationService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private ValidationService $validationService;
    private array $supportedValidators;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, ValidationService $validationService)
    {
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
     * @return array
     */
    public function getRenderDocumentation(Uuid $application): array
    {
        $docs = [];

        // General info
        $docs['openapi'] = '3.0.3';
        $docs['info'] = $this->getDocumentationInfo();

        /* @todo the server should include the base url */
        $docs['servers'] = [
            ['url'=>'/api', 'description'=>'Gateway server'],
        ];

        $docs['tags'] = [];

        $endpoints = $this->em->getRepository('App:Endpoint')->findAll(); ///findBy(['expose_in_docs'=>true]);

        foreach ($endpoints as $endpoint) {
            $docs = $this->addEnpointToDocs($endpoint, $docs);
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
            'title'         => 'Commonground Gateway EAV endpoints', /*@todo pull from config */
            'description'   => 'This documentation contains the endpoints on your commonground gateway.',  /*@todo pull from config */
            'termsOfService'=> 'http://example.com/terms/',  /*@todo pull from config */
            'contact'       => [
                'name' => 'Gateway Support', /*@todo pull from config */
                'url'  => 'http://www.conduction.nl/contact', /*@todo pull from config */
                'email'=> 'info@conduction.nl', /*@todo pull from config */
            ],
            'license'=> [
                'name'=> 'Apache 2.0', /*@todo pull from config */
                'url' => 'https://www.apache.org/licenses/LICENSE-2.0.html', /*@todo pull from config */
            ],
            'version'=> '1.0.1',
        ];
    }

    /**
     * Generates an OAS3 path for an specific endoint.
     *
     * @param Endpoint $endpoint
     * @param array  $docs
     *
     * @return array
     */
    public function addEnpointToDocs(Endpoint $endpoint, array $docs): array
    {

        /* @todo this only goes one deep */
        //$docs['components']['schemas'][ucfirst($this->toCamelCase($entity->getName()))] = $this->getItemSchema($entity);

        // Lets only add the main entities as root
        if (!$endpoint->getPath()) {
            return $docs;
        }

        // Lets add the path
        $docs['paths'][$endpoint->getPath()] = $this->getEnpointMethods($endpoint);

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
    public function getEnpointMethods(Endpoint $endpoint): array
    {
        $methods = [];
        foreach ($endpoint->getMethods() as $method){
            $methods[$method] = $this->getEnpointMethod($endpoint, $method);
        }
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
    public function getEnpointMethod(Endpoint $endpoint, string $method): array
    {
        /* @todo name should be cleaned before being used like this */
        $method = [
            "operationId" => $endpoint->getName().'_'.$method,
            "summary"=>$endpoint->getDescription(),
            "description"=>$endpoint->getDescription(),
            "parameters"=>[],
            "responses"=>[],
        ];

        // Parameters
        foreach($endpoint->getPathArray() as $parameter){

        }

        // Primary Responce (succes)
        $responceTypes = ["application/json","application/json-ld","application/json-hal","application/xml","application/yaml","text/csv"];
        $responce = false;
        switch ($method) {
            case 'get':
                $description = 'OK';
                $responce = 200;
                break;
            case 'post':
                $description = 'Created';
                $responce = 201;
                break;
            case 'put':
                $description = 'Accepted';
                $responce = 202;
                break;
        }

        if($responce){
            $method['responses'][$responce] = [
                'description' = $description,
                'content' => []
            ];

            foreach($responceTypes as $responceType){
                $method['responses'][$responce]['content'] = $this->getRequestScheme();
            }
        }

        // Let see is we need request bodies
        $requestTypes = ["application/json","application/xml","application/yaml"];
        if(in_array($method, ['put','post'])){
            foreach($requestTypes as $requestType){

            }
        }

        return $method;
    }
}
