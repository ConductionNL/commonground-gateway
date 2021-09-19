<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Paginator;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;


class EavDocumentationService
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
        $this->supportedValidators = [
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

        // Lets define the validator that we support for docummentation right now
        $this->supportedTypes = [
            'string',
            'date',
            'date-time',
            'integer',
            'array',
        ];
    }


    /**
     * Places an schema.yaml and schema.json in the /public/eav folder for use by redoc and swagger
     *
     * @return boolean returns true if succcesfull or false on failure
     */
    public function write(): bool
    {
        // Get the docs
        $docs = $this->getRenderDocumentation();

        // Setup the file system
        $filesystem = new Filesystem();

        // Check if there is a eav folder in the /public folder
        if(!$filesystem->exists('public/eav')){
            $filesystem->mkdir('public/eav');
        }

        $filesystem->dumpFile('public/eav/schema.json', json_encode($docs, JSON_UNESCAPED_SLASHES));
        $filesystem->dumpFile('public/eav/schema.yaml',  Yaml::dump($docs));

        return true;

    }

    /**
     * Generates an OAS3 documentation for the exposed eav entities in the form of an array
     *
     * @return array
     */
    public function getRenderDocumentation(): array
    {
        $docs = [];

        // General info
        $docs['openapi'] = '3.0.3';
        $docs['info'] = [
            "title"=>"Commonground Gateway EAV endpoints", /*@todo pull from config */
            "description"=>"This documentation contains the EAV endpoints on your commonground gateway.",  /*@todo pull from config */
            "termsOfService"=>"http://example.com/terms/",  /*@todo pull from config */
            "contact"=> [
                "name"=> "Gateway Support", /*@todo pull from config */
                'url'=> 'http://www.conduction.nl/contact', /*@todo pull from config */
                'email'=> "info@conduction.nl" /*@todo pull from config */
            ],
            'license'=> [
                "name"=> "Apache 2.0", /*@todo pull from config */
                "url"=> 'https://www.apache.org/licenses/LICENSE-2.0.html' /*@todo pull from config */
            ],
          "version"=>"1.0.1"
        ];
        $docs['servers']=[
            ["url"=>'/','description'=>'Gateway server']
        ];
        $docs['tags'] = [];

        // General reusable components for the documentation
        $docs['components']=[
            "schemas"=>[
                "MessageModel" =>[
                    "type"=>"object",
                    "properties"=>[
                        "message" => ["type"=>"string","format"=>"string","decription"=>"The message"],
                        "type" => ["type"=>"string","format"=>"string","decription"=>"the type of error","default"=>"error"],
                        "data"=> ["type"=>"array","format"=>"string","decription"=>"the data concerning this message"],
                    ],

                ],
                "ListModel" =>[
                    "type"=>"object",
                    "properties"=>[
                        "results" => ["type"=>"array","decription"=>"The results of your query"],
                        "total" => ["type"=>"integer","decription"=>"The total amount of items that match your current query"],
                        "pages"=> ["type"=>"integer","decription"=>"the amount of pages in the dataset based on your current limit"],
                        "page"=> ["type"=>"integer","decription"=>"the curent page of your dataset"],
                        "limit"=> ["type"=>"integer","decription"=>"the desired items per resultset or page","default" =>25],
                        "start"=> ["type"=>"integer","decription"=>"thsetarting position (or offset) of your dataset","default" =>1],
                    ],

                ]
            ],
            "responces"=>[
                "ErrorResponce"=>[
                    "description"=>"error payload",
                    "content"=>[
                        "application/json" => [
                            "schema"=>[
                                '$ref'=>'#/components/schemas/MessageModel'
                            ]
                        ]
                    ]
                ],
                "DeleteResponce"=>[
                    "description"=>"Succesfully deleted",
                    "content"=>[
                        "application/json" => [
                            "schema"=>[
                                '$ref'=>'#/components/schemas/MessageModel'
                            ]
                        ]
                    ]
                ],
                "ListResponce"=>[
                    "description"=>"List payload",
                    "content"=>[
                        "application/json" => [
                            "schema"=>[
                                '$ref'=>'#/components/schemas/ListModel'
                            ]
                        ]
                    ]
                ]
            ],
            "parameters"=>[
                "ID" => [
                    "name"=>"id",
                    "in"=>"path",
                    "description"=>"ID of the object that you want to target",
                    "required"=>true,
                    "style"=>"simple"
                ],
                "Page" => [
                    "name"=>"page",
                    "in"=>"path",
                    "description"=>"The page of the  list that you want to use",
                    "style"=>"simple"
                ],
                "Limit" => [
                    "name"=>"limit",
                    "in"=>"path",
                    "description"=>"The total amount of items that you want to include in a list",
                    "style"=>"simple"
                ],
                "Start" => [
                    "name"=>"start",
                    "in"=>"path",
                    "description"=>"The firts item that you want returned, is used to determine the list offset. E.g. if you start at 50 the first 49 items wil not be returned",
                    "style"=>"simple"
                ]
            ]
        ];

        /* @todo we want to make exposing objects a choice */
        $entities = $this->em->getRepository('App:Entity')->findAll(); ///findBy(['expose_in_docs'=>true]);

        foreach($entities as $entity){
            $docs = $this->addEntityToDocs($entity, $docs);
        }

        /* This is hacky */
        $docs = $this->addOtherRoutes($docs);

        return $docs;
    }

    /**
     * This adds the non EAV routes
     *
     * @param array $docs
     * @return array
     */
    public function addOtherRoutes(array $docs): array
    {

        //$docs['paths']['reports/{type}'] =

        $docs['tags'][] = [
            "name"=>'Reports',
            "description"=>'Administratice reports about this environment'
        ];

        // $docs['tags']['paths']['users/login'] = []
        // $docs['tags']['paths']['users/reset'] = []
        // $docs['tags']['paths']['reports/learning_needs'] = []
        // $docs['tags']['paths']['reports/students'] = []
        return $docs;
    }

    /**
     * Generates an OAS3 documentation for the exposed eav entities in the form of an array
     *
     * @param Entity $entity
     * @param array $docs
     * @return array
     */
    public function addEntityToDocs(Entity $entity, array $docs): array
    {

        /* @todo this only goes one deep */
        $docs['components']['schemas'][ucfirst($this->toCamelCase($entity->getName()))] = $this->getItemSchema($entity);

        // Lets only add the main entities as root
        if(!$entity->getRoute()){
            return $docs;
        }

        $docs['paths'][$entity->getRoute()] = $this->getCollectionPaths($entity);
        $docs['paths'][$entity->getRoute().'/{id}'] = $this->getItemPaths($entity);


        // create the tag
        $docs['tags'][] = [
            "name"=>ucfirst($entity->getName()),
	        "description"=>$entity->getDescription()
        ];

        return $docs;
    }

    /**
     * Generates an OAS3 documentation for the colleection paths of an entity
     *
     * @param Entity $entity
     * @return array
     */
    public function getCollectionPaths(Entity $entity): array
    {
        $docs = [
            "get" => [
                "description"=>"Get a filterd list of ".$entity->getName()." objects",
                "summary"=>"Get a ".$entity->getName()." list",
                "operationId"=>"get".$this->toCamelCase($entity->getName()),
                "tags"=>[ucfirst($entity->getName())],
                "parameters"=>$this->getFilterParameters($entity),
                "responses"=>[
                    "200"=>[
                        "description"=>"List payload",
                        "content"=>[
                            "application/json" => [
                                "schema"=>[
                                    "type"=>"object",
                                    "properties"=>[
                                        "results" => ["type"=>"array","decription"=>"The results of your query","items"=>['$ref'=>'#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName()))]],
                                        "total" => ["type"=>"integer","decription"=>"The total amount of items that match your current query"],
                                        "pages"=> ["type"=>"integer","decription"=>"the amount of pages in the dataset based on your current limit"],
                                        "page"=> ["type"=>"integer","decription"=>"the curent page of your dataset"],
                                        "limit"=> ["type"=>"integer","decription"=>"the desired items per resultset or page","default" =>25],
                                        "start"=> ["type"=>"integer","decription"=>"thsetarting position (or offset) of your dataset","default" =>1],
                                    ],
                                ]
                            ]
                        ]
                    ],
                    "404"=>['$ref'=>'#/components/responces/ErrorResponce']
                ],
            ],
            "post" => [
                "description"=>"Creates a new".$entity->getName()." object",
                "summary"=>"Create a ".$entity->getName(),
                "operationId"=>"post".$this->toCamelCase($entity->getName()),
                "tags"=>[ucfirst($entity->getName())],
                "requestBody"=>[
                    "description"=>"Create ".$entity->getName(),
                    "content"=>[
                        "application/json" => [
                            "schema"=>[
                                '$ref'=>'#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName()))
                            ]
                        ]
                    ]
                ],
                "responses"=>[
                    "201"=>[
                        "description"=>"succesfully created ".$entity->getName(),
                        "content"=>[
                            "application/json" => [
                                "schema"=>[
                                   '$ref'=>'#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName()))
                               ]
                            ]
                        ]
                    ],
                    "404"=>['$ref'=>'#/components/responces/ErrorResponce']
                ]
            ],
        ];

        return $docs;
    }


    /**
     * Generates an OAS3 documentation for the item paths of an entity
     *
     * @param Entity $entity
     * @return array
     */
    public function getItemPaths(Entity $entity): array
    {
        $docs = [];
        $types = ['get','put','delete'];
        foreach ($types as $type){

            // Basic path operations
            $docs[$type] = [
                "description"=>ucfirst($type)." a ".$entity->getName(),
                "summary"=>ucfirst($type)." a ".$entity->getName(),
                "operationId"=>$type.$this->toCamelCase($entity->getName())."ById",
                "tags"=>[ucfirst($entity->getName())],
                "responses"=>[
                    "404"=>['$ref'=>'#/components/responces/ErrorResponce']
                ]
            ];

            // Type specific path responces
            switch ($type) {
                case 'put':
                    $docs[$type]["requestBody"] = [
                        "description"=>"Update ".$entity->getName(),
                        "content"=>[
                            "application/json" => [
                                "schema"=>[
                                    '$ref'=>'#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName()))
                                ]
                            ]
                        ]
                    ];
                    $docs[$type]["responses"]["200"] = [
                        "description"=>"succesfully created ".$entity->getName(),
                        "content"=>[
                            "application/json" => [
                                "schema"=>[
                                    '$ref'=>'#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName()))
                                ]
                            ]
                        ]
                    ];
                case 'delete':
                    $docs[$type]["responses"]["204"] = ['$ref'=>'#/components/responces/DeleteResponce'];
            }
        }

        // Pat parameters
        //$docs['parameters'] = [
        //    // Each parameter is a loose array
        //    "\$ref"=>'#/components/parameters/ID'
        //
        //];

        return $docs;
    }

    /**
     * Generates an OAS3 schema for the item  of an entity
     *
     * @param Entity $entity
     * @return array
     */
    public function getItemSchema(Entity $entity): array
    {
        $schema = [
            "type"=>"object",
            "required"=>[],
            "properties"=>[],

        ];

        // Lets see if there are external properties
        if(
            $entity->getExtend() &&
            $entity->getGateway() &&
            !empty($entity->getGateway()->getPaths()) &&
            array_key_exists('/'.$entity->getEndpoint(),$entity->getGateway()->getPaths() ) &&
            $externalSchema = $entity->getGateway()->getPaths()['/'.$entity->getEndpoint()]
        ){
            // Lets get the correct schema
            foreach($externalSchema['properties'] as $key => $property){
                // We only want to port supported types
                //if(!array_key_exists($property['type'], $this->supportedValidators)){
                //    continue;
                //}

                // Das magic
                $property['externalDocs'] = $entity->getGateway()->getLocation();
                $schema['properties'][$key] = $property;
            }
        }

        // Add our own properties
        foreach($entity->getAttributes() as $attribute){

            // Handle requireded fields
            if($attribute->getRequired() and $attribute->getRequired() != null){
                $schema['required'][] = $attribute->getName();
            }

            // Add the attribute
            $schema['properties'][$attribute->getName()] = [
                "type"=>$attribute->getType(),
                "title"=>$attribute->getName(),
                "description"=>$attribute->getDescription(),
            ];

            // The attribute might be a scheme on its own
            if($attribute->getObject() && $attribute->getCascade()){
                $schema['properties'][$attribute->getName()] = ['$ref'=>'#/components/schemas/'.ucfirst($this->toCamelCase($attribute->getObject()->getName()))];
                // that also means that we don't have to do the rest
                //continue;
            }
            elseif($attribute->getObject() && !$attribute->getCascade()){
                $schema['properties'][$attribute->getName()]['type'] = "string";
                $schema['properties'][$attribute->getName()]['format'] = "uuid";
                $schema['properties'][$attribute->getName()]['description'] = $schema['properties'][$attribute->getName()]['description'].'The uuid of the ['.$attribute->getObject()->getName().']() object that you want to link, you can unlink objects by setting this field to null';
            }

            // Handle conditional logic
            if($attribute->getRequiredIf()){
                foreach($attribute->getRequiredIf() as $requiredIfKey=>$requiredIfValue){
                    $schema['properties'][$attribute->getName()]['description'] = $schema['properties'][$attribute->getName()]['description'].'(this property is required if the '.$requiredIfKey.' property equals '.(string) $requiredIfValue.' )';
                }
            }

            // Handle inversed by
            if($attribute->getInversedBy()){
                $schema['properties'][$attribute->getName()]['description'] = $schema['properties'][$attribute->getName()]['description'].'(this object is inversed by the '.$attribute->getInversedBy().' of its subobject)';
            }


            /* @todo we nee to add supoort for https://swagger.io/specification/#schema-object
             *
             *
             */

            /* @todo ow nooz a loopin a loop */
            foreach($attribute->getValidations() as $validator => $validation){
                if(!array_key_exists($validator, $this->supportedValidators) && $validation != null){
                    $schema['properties'][$attribute->getName()][$validator] = $validation;
                }
            }
        }

        return $schema;
    }

    /**
     * Turns a string to toSnakeCase
     *
     * @param string $string the string to convert to toSnakeCase
     * @return string the toSnakeCase represention of the string
     */
    public function toSnakeCase(string $value, ?string $delimiter = null): string
    {
        if (!\ctype_lower($value)) {
            $value = (string) \preg_replace('/\s+/u', '', \ucwords($value));
            $value = (string) \mb_strtolower(\preg_replace(
                '/(.)(?=[A-Z])/u',
                '$1' . ($delimiter ?? '_'),
                $value
            ));
        }

        return $value;
    }

    /**
     * Turns a string to CammelCase
     *
     * @param string $string the string to convert to CamelCase
     * @return string the CamelCase represention of the string
     */
    public function toCamelCase($string, $dontStrip = []){
        /*
         * This will take any dash or underscore turn it into a space, run ucwords against
         * it so it capitalizes the first letter in all words separated by a space then it
         * turns and deletes all spaces.
         */
        return lcfirst(str_replace(' ', '', ucwords(preg_replace('/^a-z0-9'.implode('',$dontStrip).']+/', ' ',$string))));
    }

    public function getFilterParameters(Entity $Entity, string $prefix = '', int $level = 1): array
    {
        $parameters = [];

        foreach($Entity->getAttributes() as $attribute){
            if($attribute->getType() == 'string' && $attribute->getSearchable()){
                $parameters[]= [
                    "name"=>$prefix.$attribute->getName(),
                    "in"=>"query",
                    "description"=>"Search ".$prefix.$attribute->getName().' on an exact match of the string',
                    "required"=>false,
                    "style"=>"simple"
                ];
            }
            elseif($attribute->getObject()  && $level < 5){
                $parameters = array_merge($parameters, $this->getFilterParameters($attribute->getObject(), $attribute->getName().'.',  $level+1));
            }
            continue;
        }

        return $parameters;
    }
}
