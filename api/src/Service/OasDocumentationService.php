<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Endpoint;
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

        if($response){
            $methodArray['responses'][$response] = [
                'description' => $description,
                'content' => []
            ];

            foreach($responseTypes as $responseType){
                $methodArray['responses'][$response]['content'] = $this->getResponseScheme($endpoint, $method, $responseType);
            }
        }

        // Let see is we need request bodies
        $requestTypes = ["application/json","application/xml","application/yaml"];
        if(in_array($methodArray, ['put','post'])){
            foreach($requestTypes as $requestType){
                $methodArray['requests']['content'] = $this->getRequestScheme($endpoint, $method, $requestType);
            }
        }

        return $methodArray;
    }

    /**
     * Gets an OAS description for a specific method
     *
     * @param Endpoint $endpoint
     * @param string $method
     * @param string $requestType;
     *
     * @return array
     */
    public function getRequestScheme(Endpoint $endpoint, string $method, string $requestType): array
    {
        return [];
    }

    /**
     * Gets an OAS description for a specific method
     *
     * @param Endpoint $endpoint
     * @param string $method
     * @param string $responseType
     *
     * @return array
     */
    public function getResponseScheme(Endpoint $endpoint, string $method, string $responseType): array
    {
        return [];
    }
}
