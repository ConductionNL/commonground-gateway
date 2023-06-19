<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Gino Kok
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class EavDocumentationService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private array $supportedValidators;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;

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
    public function write(): bool
    {
        // Get the docs
        $docs = $this->getRenderDocumentation();

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
     * @return array
     */
    public function getRenderDocumentation(): array
    {
        $docs = [];

        // General info
        $docs['openapi'] = '3.0.3';
        $docs['info'] = $this->getDocumentationInfo();
        $docs['servers'] = [
            ['url'=>'/api', 'description'=>'Gateway server'],
        ];
        $docs['tags'] = [];

        // General reusable components for the documentation
        $docs['components'] = $this->getDocumentationComponents();

        /* @todo we want to make exposing objects a choice */
        $entities = $this->em->getRepository('App:Entity')->findAll(); ///findBy(['expose_in_docs'=>true]);

        foreach ($entities as $entity) {
            $docs = $this->addEntityToDocs($entity, $docs);
        }

        /* This is hacky */
        $docs = $this->addOtherRoutes($docs);

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
     * Returns components for the getRenderDocumentation function.
     *
     * @return array
     */
    private function getDocumentationComponents(): array
    {
        return [
            'schemas'   => $this->getComponentsSchemas(),
            'responses' => $this->getComponentsResponses(),
            'parameters'=> $this->getComponentsParameters(),
        ];
    }

    /**
     * Returns schemas for the getDocumentationComponents function.
     *
     * @return array[]
     */
    private function getComponentsSchemas(): array
    {
        return [
            'MessageModel' => [
                'type'      => 'object',
                'properties'=> [
                    'message' => ['type'=>'string', 'format'=>'string', 'decription'=>'The message'],
                    'type'    => ['type'=>'string', 'format'=>'string', 'decription'=>'the type of error', 'default'=>'error'],
                    'data'    => ['type'=>'array', 'format'=>'string', 'decription'=>'the data concerning this message'],
                ],

            ],
            'ListModel' => [
                'type'      => 'object',
                'properties'=> [
                    'results' => ['type'=>'array', 'decription'=>'The results of your query'],
                    'total'   => ['type'=>'integer', 'decription'=>'The total amount of items that match your current query'],
                    'pages'   => ['type'=>'integer', 'decription'=>'the amount of pages in the dataset based on your current limit'],
                    'page'    => ['type'=>'integer', 'decription'=>'the curent page of your dataset'],
                    'limit'   => ['type'=>'integer', 'decription'=>'the desired items per resultset or page', 'default' =>25],
                    'start'   => ['type'=>'integer', 'decription'=>'thsetarting position (or offset) of your dataset', 'default' =>1],
                ],

            ],
        ];
    }

    /**
     * Returns responses for the getDocumentationComponents function.
     *
     * @return array[]
     */
    private function getComponentsResponses(): array
    {
        return [
            'ErrorResponse' => $this->getComponentResponse('error payload'),
            'DeleteResponse'=> $this->getComponentResponse('Succesfully deleted'),
            'ListResponse'  => $this->getComponentResponse('List payload'),
        ];
    }

    /**
     * Returns a response for the getComponentsResponses function.
     *
     * @param string $description
     *
     * @return array
     */
    private function getComponentResponse(string $description): array
    {
        return [
            'description'=> $description,
            'content'    => [
                'application/json' => [
                    'schema'=> [
                        '$ref'=> '#/components/schemas/MessageModel',
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns parameters for the getDocumentationComponents function.
     *
     * @return array
     */
    private function getComponentsParameters(): array
    {
        return [
            'ID' => [
                'name'       => 'id',
                'in'         => 'path',
                'description'=> 'ID of the object that you want to target',
                'required'   => true,
                'style'      => 'simple',
            ],
            'Page' => [
                'name'       => 'page',
                'in'         => 'path',
                'description'=> 'The page of the  list that you want to use',
                'style'      => 'simple',
            ],
            'Limit' => [
                'name'       => 'limit',
                'in'         => 'path',
                'description'=> 'The total amount of items that you want to include in a list',
                'style'      => 'simple',
            ],
            'Start' => [
                'name'       => 'start',
                'in'         => 'path',
                'description'=> 'The firts item that you want returned, is used to determine the list offset. E.g. if you start at 50 the first 49 items wil not be returned',
                'style'      => 'simple',
            ],
        ];
    }

    /**
     * This adds the non EAV routes.
     *
     * @param array $docs
     *
     * @return array
     */
    public function addOtherRoutes(array $docs): array
    {
        //$docs['paths']['reports/{type}'] =

        $docs['tags'][] = [
            'name'        => 'Reports',
            'description' => 'Administratice reports about this environment',
        ];

        $docs['paths']['/users/login'] = $this->addRouteUsersLogin();
        $docs['paths']['/users/logout'] = $this->addRouteUsersLogout();
        $docs['paths']['users/request_password_reset'] = $this->addRouteUsersRequestPR();
        $docs['paths']['users/me'] = $this->addRouteUsersMe();
        $docs['paths']['users/reset_password'] = $this->addRouteUsersRP();
        $docs['paths']['reports/learning_needs'] = $this->addRouteReportsLearningNeeds();
        $docs['paths']['reports/students'] = $this->addRouteReportsStudents();

        return $docs;
    }

    /**
     * Returns non EAV route /users/login.
     *
     * @return array[]
     */
    private function addRouteUsersLogin(): array
    {
        return [
            'post' => [
                'description' => 'Test user credentials and return a JWT token',
                'summary'     => 'Login',
                'operationId' => 'login',
                'tags'        => ['Users'],
                'requestBody' => [
                    'description' => 'Create a login request',
                    'content'     => [
                        'application/json' => [
                            'schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'username' => ['type' => 'string', 'decription' => 'The username'],
                                    'password' => ['type' => 'string', 'decription' => 'The password'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => $this->routeUsersLoginResponses(),
            ],
        ];
    }

    /**
     * Returns responses for non EAV route /users/login.
     *
     * @return array
     */
    private function routeUsersLoginResponses(): array
    {
        return [
            '200' => [
                'description' => 'Login succefull',
                'content'     => [
                    'application/json' => [
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                '@context'     => ['type' => 'string', 'decription' => 'The internal datataype of the object', 'example' => '/contexts/User'],
                                '@id'          => ['type' => 'string', 'decription' => 'The url of the obbject', 'example' => 'https://taalhuizen-bisc.commonground.nu/api/v2/uc/loginusers/c53f17ea-89d6-4cd3-8a26-e87ca148688d'],
                                '@type'        => ['type' => 'string', 'decription' => 'The linked data type of the object', 'example' => 'User'],
                                'id'           => ['type' => 'string', 'decription' => 'The id of this user', 'example' => 'c53f17ea-89d6-4cd3-8a26-e87ca148688d'],
                                'organization' => ['type' => 'string', 'decription' => 'The organizations that this user belongs to', 'example' => 'https://taalhuizen-bisc.commonground.nu/api/v1/cc/organizations/3a885f21-6884-4128-8182-56aa8dd57a4f'],
                                'username'     => ['type' => 'string', 'decription' => 'The username', 'example' => 'test@bisc.nl'],
                                'locale'       => ['type' => 'string', 'decription' => 'The users langouge', 'example' => 'nl'],
                                'person'       => ['type' => 'string', 'decription' => 'The url of the person object for the user'],
                                'roles'        => ['type' => 'array', 'decription' => 'The users roles'],
                                'userGroups'   => ['type' => 'array', 'decription' => 'The users sercurity groups'],
                                'jwtToken'     => ['type' => 'string', 'decription' => 'The jwtToken for authorisation', 'example' => 'eyJhbGciOiJSUzUxMiJ9.eyJ1c2VySWQiOiJjNTNmMTdlYS04OWQ2LTRjZDMtOGEyNi1lODdjYTE0ODY4OGQiLCJyb2xlcyI6WyJ1c2VyIl0sInNlc3Npb24iOiIxNzQ5ZjdhYy0yNGI5LTQxZjAtOTBiYy04MjJmZjEyZjUxY2QiLCJjc3JmVG9rZW4iOiJjYTE4ZmFjOGVjZTU3NzE5NWNjLmtncUQ4TFFpbGVwNFhiblpORDZ1TWV0R0tSUkg3TDJCMFVsYkgwcGJhdGsuMFdETGlQbHQ5OWdTREl1V1kzVHNCcDBmSEZ4M3FkRFg0RE1RVkFNNUJKVG1STEdEM2tmdGpUc196ZyIsImlzcyI6Imh0dHBzOlwvXC90YWFsaHVpemVuLWJpc2MuY29tbW9uZ3JvdW5kLm51XC9hcGlcL3YyXC91YyIsImlhcyI6MTYzMjA5MzA5OSwiZXhwIjoxNjMyNTI1MDk5fQ.S_ikVB5TtGl8mobvNEeQsGF6txf3kgtks6lENlXcwaoykOy3vIwtFv-ppIXJH0hbUHBoyQ7cX2fVS5pXi2h-pTm-IbXtWUVSbcN-3YIE3WbFEGHoWeHV2ZP1gQf3dqUjMwyFlnazFUFm-eK6Ui3MDfs28FFs_xCsRa4lu3hkJ4iYGl-EeKnLOJHuSUXy3KIbdPIeBwy3iTeNAXn8ExYKfLRAioE98ojOlQoV9wiRJahjy7JXMl51xHmq1BxxAW2D1pZStOf5UUk9XCSf4tWkrsc0iNktLyLB1-eGOVTpzYVYQw0CcMUnjJU3ZfXKO7-Z77kXSZK6AjKv3bcp18C_VUsb0_LHCLi0f_I4fikL-iSkJ8Hu7iLfSXTGe50pNbHC_2DHywWYcFy8sqMpTwn_Auwr-UzFBNwkPF6UiyzFYN8kN_60riw2uTxN18xF8dLG8xZ5WCkMm3SVmYAO4BgmWNrHvxC0P1kz9UVIYKxjzMy77zEyaeAxOaEa6o4u3K1aOFskFUMgJ7wYOfChnKTvrotQoy44HcOttIfqEZC-yfsFPPcCJ7SOc7IIcKTmmZynQcL_8oYPXtL0W7C7uCYOGjB5L-MqTlr9XbJXaSEPcDmRZO0EkOOGP3X6AUwDS_vo1On2ELbJvW5NRLpj51f8eeA9ezSBnvdueIVs11MqYrs'],
                                'csrfToken'    => ['type' => 'string', 'decription' => 'The csef token', 'example' => 'ca18fac8ece577195cc.kgqD8LQilep4XbnZND6uMetGKRRH7L2B0UlbH0pbatk.0WDLiPlt99gSDIuWY3TsBp0fHFx3qdDX4DMQVAM5BJTmRLGD3kftjTs_zg'],
                                '@self'        => ['type' => 'string', 'decription' => 'The login event', 'example' => 'https://taalhuizen-bisc.commonground.nu/api/v2/uc/loginusers/c53f17ea-89d6-4cd3-8a26-e87ca148688d'],
                                'name'         => ['type' => 'string', 'decription' => 'The user id', 'example' => 'c53f17ea-89d6-4cd3-8a26-e87ca148688d'],
                            ],
                        ],
                    ],
                ],
            ],
            '401' => ['$ref' => '#/components/responses/ErrorResponse'],
        ];
    }

    /**
     * Returns non EAV route /users/logout.
     *
     * @return array[]
     */
    private function addRouteUsersLogout(): array
    {
        return [
            'post' => [
                'description' => 'Logout the user by destroying the JWT token server side',
                'summary'     => 'Logout',
                'operationId' => 'Logout',
                'tags'        => ['Users'],
                'requestBody' => [
                    'description' => 'Create a logout request',
                    'content'     => [
                        'application/json' => [
                            'schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'jwtToken' => ['type' => 'string', 'decription' => 'The jwtToken to destroy'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => $this->routeUsersLogoutResponses(),
            ],
        ];
    }

    /**
     * Returns responses for non EAV route /users/logout.
     *
     * @return array[]
     */
    private function routeUsersLogoutResponses(): array
    {
        return [
            '202' => [
                'description' => 'Logout succefull',
            ],
            '401' => ['$ref' => '#/components/responses/ErrorResponse'],
        ];
    }

    /**
     * Returns non EAV route /users/request_password_reset.
     *
     * @return array[]
     */
    private function addRouteUsersRequestPR(): array
    {
        return [
            'post' => [
                'description' => 'Requests a reset token to be sent to the user by email, from a security point of view this endpoint wil always return a 200 responce to prevent fishing for user names',
                'summary'     => 'Request token',
                'operationId' => 'request_token',
                'tags'        => ['Users'],
                'requestBody' => [
                    'description' => 'Create a reset token',
                    'content'     => [
                        'application/json' => [
                            'schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'username' => ['type' => 'string', 'decription' => 'The username'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => $this->routeUsersRequestPRResponses(),
            ],
        ];
    }

    /**
     * Returns responses for non EAV route /users/request_password_reset.
     *
     * @return array[]
     */
    private function routeUsersRequestPRResponses(): array
    {
        return [
            '200' => [
                'description' => 'Request handled ',
                'content'     => [
                    'application/json' => [
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'username' => ['type' => 'string', 'decription' => 'The username'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns non EAV route /users/me.
     *
     * @return array[]
     */
    private function addRouteUsersMe(): array
    {
        return [
            'get' => [
                'description' => 'Requests the current user data based on the user token provided at login',
                'summary'     => 'Current User',
                'operationId' => 'get_me',
                'tags'        => ['Users'],
                'requestBody' => [
                    'description' => 'Create a reset token',
                    'content'     => [
                        'application/json' => [
                            'schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'jwtToken' => ['type' => 'string', 'decription' => 'The jwt token for wich to get the user'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => $this->routeUsersMeResponses(),
            ],
        ];
    }

    /**
     * Returns responses for non EAV route /users/me.
     *
     * @return array[]
     */
    private function routeUsersMeResponses(): array
    {
        return [
            '200' => [
                'description' => 'Request handled ',
                'content'     => [
                    'application/json' => [
                        'schema' => [
                            '$ref'=> '#/components/schemas/Users',
                        ],
                    ],
                ],
            ],
            '401' => ['$ref' => '#/components/responses/ErrorResponse'],
        ];
    }

    /**
     * Returns non EAV route /users/reset_password.
     *
     * @return array[]
     */
    private function addRouteUsersRP(): array
    {
        return [
            'post' => [
                'description' => 'Resets the users password trough suplieng a new password and securty token, as a security step the user name is also required',
                'summary'     => 'Reset password',
                'operationId' => 'reset_password',
                'tags'        => ['Users'],
                'requestBody' => [
                    'description' => 'Reset the user password',
                    'content'     => [
                        'application/json' => [
                            'schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'username' => ['type' => 'string', 'decription' => 'The username'],
                                    'password' => ['type' => 'string', 'decription' => 'The password'],
                                    'token'    => ['type' => 'string', 'decription' => 'The password reset token'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => $this->routeUsersRPResponses(),
            ],
        ];
    }

    /**
     * Returns responses for non EAV route /users/reset_password.
     *
     * @return array
     */
    private function routeUsersRPResponses(): array
    {
        return [
            '200' => [
                'description' => 'Reset succefull',
                'content'     => [
                    'application/json' => [
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'username' => ['type' => 'string', 'decription' => 'The username'],
                            ],
                        ],
                    ],
                ],
            ],
            '401' => ['$ref' => '#/components/responses/ErrorResponse'],
        ];
    }

    /**
     * Returns non EAV route /reports/learning_needs.
     *
     * @return array[]
     */
    private function addRouteReportsLearningNeeds(): array
    {
        return [
            'get' => [
                'description' => 'Generates the Learning Needs cvs report',
                'summary'     => "Learning Needs'",
                'operationId' => 'reports_learning_needs',
                'tags'        => ['Reports'],
                'responses'   => [
                    '200' => [
                        'description' => 'Download succefull',
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorResponse'],
                ],
            ],
        ];
    }

    /**
     * Returns non EAV route /reports/students.
     *
     * @return array[]
     */
    private function addRouteReportsStudents(): array
    {
        return [
            'get' => [
                'description' => 'Generates the Students cvs report',
                'summary'     => 'Students',
                'operationId' => 'reports_students',
                'tags'        => ['Reports'],
                'responses'   => [
                    '200' => [
                        'description' => 'Download succefull',
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorResponse'],
                ],
            ],
        ];
    }

    /**
     * Generates an OAS3 documentation for the exposed eav entities in the form of an array.
     *
     * @param Entity $entity
     * @param array  $docs
     *
     * @return array
     */
    public function addEntityToDocs(Entity $entity, array $docs): array
    {
        /* @todo this only goes one deep */
        $docs['components']['schemas'][ucfirst($this->toCamelCase($entity->getName()))] = $this->getItemSchema($entity);

        // Lets only add the main entities as root
        if (!$entity->getRoute()) {
            return $docs;
        }

        $docs['paths'][$entity->getRoute()] = $this->getCollectionPaths($entity);
        $docs['paths'][$entity->getRoute().'/{id}'] = $this->getItemPaths($entity);

        // create the tag
        $docs['tags'][] = [
            'name'       => ucfirst($entity->getName()),
            'description'=> $entity->getDescription(),
        ];

        return $docs;
    }

    /**
     * Generates an OAS3 documentation for the colleection paths of an entity.
     *
     * @param Entity $entity
     *
     * @return array
     */
    public function getCollectionPaths(Entity $entity): array
    {
        return [
            'get'  => $this->getCollectionGetPath($entity),
            'post' => $this->getCollectionPostPath($entity),
        ];
    }

    private function getCollectionGetPath(Entity $entity): array
    {
        return [
            'description'=> 'Get a filterd list of '.$entity->getName().' objects',
            'summary'    => 'Get a '.$entity->getName().' list',
            'operationId'=> 'get'.$this->toCamelCase($entity->getName()),
            'tags'       => [ucfirst($entity->getName())],
            'parameters' => array_merge($this->gePaginationParameters(), $this->getFilterParameters($entity)),
            'responses'  => [
                '200'=> [
                    'description'=> 'List payload',
                    'content'    => [
                        'application/json' => [
                            'schema'=> [
                                'type'      => 'object',
                                'properties'=> [
                                    'results' => ['type'=>'array', 'decription'=>'The results of your query', 'items'=>['$ref'=>'#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName()))]],
                                    'total'   => ['type'=>'integer', 'decription'=>'The total amount of items that match your current query'],
                                    'pages'   => ['type'=>'integer', 'decription'=>'the amount of pages in the dataset based on your current limit'],
                                    'page'    => ['type'=>'integer', 'decription'=>'the curent page of your dataset'],
                                    'limit'   => ['type'=>'integer', 'decription'=>'the desired items per resultset or page', 'default' =>25],
                                    'start'   => ['type'=>'integer', 'decription'=>'thsetarting position (or offset) of your dataset', 'default' =>1],
                                ],
                            ],
                        ],
                    ],
                ],
                '404'=> ['$ref'=>'#/components/responses/ErrorResponse'],
            ],
        ];
    }

    private function getCollectionPostPath(Entity $entity): array
    {
        return [
            'description'=> 'Creates a new'.$entity->getName().' object',
            'summary'    => 'Create a '.$entity->getName(),
            'operationId'=> 'post'.$this->toCamelCase($entity->getName()),
            'tags'       => [ucfirst($entity->getName())],
            'requestBody'=> [
                'description'=> 'Create '.$entity->getName(),
                'content'    => [
                    'application/json' => [
                        'schema'=> [
                            '$ref'=> '#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName())),
                        ],
                    ],
                ],
            ],
            'responses'=> [
                '201'=> [
                    'description'=> 'succesfully created '.$entity->getName(),
                    'content'    => [
                        'application/json' => [
                            'schema'=> [
                                '$ref'=> '#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName())),
                            ],
                        ],
                    ],
                ],
                '404'=> ['$ref'=>'#/components/responses/ErrorResponse'],
            ],
        ];
    }

    /**
     * Generates an OAS3 documentation for the item paths of an entity.
     *
     * @param Entity $entity
     *
     * @return array
     */
    public function getItemPaths(Entity $entity): array
    {
        $docs = [];
        $types = ['get', 'put', 'delete'];
        foreach ($types as $type) {
            // Basic path operations
            $docs[$type] = [
                'description'=> ucfirst($type).' a '.$entity->getName(),
                'summary'    => ucfirst($type).' a '.$entity->getName(),
                'operationId'=> $type.$this->toCamelCase($entity->getName()).'ById',
                'tags'       => [ucfirst($entity->getName())],
                'responses'  => [
                    '404'=> ['$ref'=>'#/components/responses/ErrorResponse'],
                ],
            ];

            // Type specific path responses
            $docs = $this->getItemPathTypeResponses($docs, $entity, $type);
        }

        // Pat parameters
        //$docs['parameters'] = [
        //    // Each parameter is a loose array
        //    "\$ref"=>'#/components/parameters/ID'
        //
        //];

        return $docs;
    }

    private function getItemPathTypeResponses(array $docs, Entity $entity, string $type): array
    {
        // Type specific path responses
        switch ($type) {
            case 'put':
                $docs[$type]['requestBody'] = [
                    'description'=> 'Update '.$entity->getName(),
                    'content'    => [
                        'application/json' => [
                            'schema'=> [
                                '$ref'=> '#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName())),
                            ],
                        ],
                    ],
                ];
                $docs[$type]['responses']['200'] = [
                    'description'=> 'succesfully created '.$entity->getName(),
                    'content'    => [
                        'application/json' => [
                            'schema'=> [
                                '$ref'=> '#/components/schemas/'.ucfirst($this->toCamelCase($entity->getName())),
                            ],
                        ],
                    ],
                ];
            case 'delete':
                $docs[$type]['responses']['204'] = ['$ref'=>'#/components/responses/DeleteResponse'];
        }

        return $docs;
    }

    /**
     * Generates an OAS3 schema for the item  of an entity.
     *
     * @param Entity $entity
     *
     * @return array
     */
    public function getItemSchema(Entity $entity): array
    {
        $schema = [
            'type'      => 'object',
            'required'  => [],
            'properties'=> [],

        ];

        // Lets see if there are external properties
        if (
            $entity->getExtend() &&
            $entity->getSource() &&
            !empty($entity->getSource()->getPaths()) &&
            array_key_exists('/'.$entity->getEndpoint(), $entity->getSource()->getPaths()) &&
            $externalSchema = $entity->getSource()->getPaths()['/'.$entity->getEndpoint()]
        ) {
            // Lets get the correct schema
            foreach ($externalSchema['properties'] as $key => $property) {
                // We only want to port supported types
                //if(!array_key_exists($property['type'], $this->supportedValidators)){
                //    continue;
                //}

                // Das magic
                $property['externalDocs'] = $entity->getSource()->getLocation();
                $schema['properties'][$key] = $property;
            }
        }

        // Add our own properties
        return $this->getItemSchemaProperties($schema, $entity);
    }

    /**
     * Generates an OAS3 schema for the properties of an item. For getItemSchema().
     *
     * @param array  $schema
     * @param Entity $entity
     *
     * @return array
     */
    private function getItemSchemaProperties(array $schema, Entity $entity): array
    {
        // Add our own properties
        foreach ($entity->getAttributes() as $attribute) {
            // Handle requireded fields
            if ($attribute->getRequired() and $attribute->getRequired() != null) {
                $schema['required'][] = $attribute->getName();
            }

            // Add the attribute
            $schema = $this->getItemSchemaPropertyBase($schema, $attribute);

            // Handle conditional logic
            $schema = $this->getItemSchemaPropertyConditions($schema, $attribute);

            // Handle inversed by
            if ($attribute->getInversedBy()) {
                /* this now thros an error */
                //$schema['properties'][$attribute->getName()]['description'] = $schema['properties'][$attribute->getName()]['description'].'(this object is inversed by the '.$attribute->getInversedBy().' of its subobject)';
            }

            /* @todo we nee to add supoort for https://swagger.io/specification/#schema-object
             *
             *
             */

            /* @todo ow nooz a loopin a loop */
            foreach ($attribute->getValidations() as $validator => $validation) {
                if (!array_key_exists($validator, $this->supportedValidators) && $validation != null) {
                    $schema['properties'][$attribute->getName()][$validator] = $validation;
                }
            }
        }

        return $schema;
    }

    /**
     * Generates an OAS3 schema for the base of a property. For getItemSchemaProperties().
     *
     * @param array     $schema
     * @param Attribute $attribute
     *
     * @return array
     */
    private function getItemSchemaPropertyBase(array $schema, Attribute $attribute): array
    {
        // Add the attribute
        $schema['properties'][$attribute->getName()] = [
            'type'       => $attribute->getType(),
            'title'      => $attribute->getName(),
            'description'=> $attribute->getDescription(),
        ];

        // The attribute might be a scheme on its own
        if ($attribute->getObject() && $attribute->getCascade()) {
            $schema['properties'][$attribute->getName()] = ['$ref'=>'#/components/schemas/'.ucfirst($this->toCamelCase($attribute->getObject()->getName()))];
            // that also means that we don't have to do the rest
            //continue;
        } elseif ($attribute->getObject() && !$attribute->getCascade()) {
            $schema['properties'][$attribute->getName()]['type'] = 'string';
            $schema['properties'][$attribute->getName()]['format'] = 'uuid';
            $schema['properties'][$attribute->getName()]['description'] = $schema['properties'][$attribute->getName()]['description'].'The uuid of the ['.$attribute->getObject()->getName().']() object that you want to link, you can unlink objects by setting this field to null';
        }

        return $schema;
    }

    /**
     * Generates an OAS3 schema for the conditions of a property. For getItemSchemaProperties().
     *
     * @param array     $schema
     * @param Attribute $attribute
     *
     * @return array
     */
    private function getItemSchemaPropertyConditions(array $schema, Attribute $attribute): array
    {
        // Handle conditional logic
        if ($attribute->getRequiredIf()) {
            foreach ($attribute->getRequiredIf() as $requiredIfKey=>$requiredIfValue) {
                /* @todo lelijk */
                if (is_array($requiredIfValue)) {
                    foreach ($requiredIfValue as $requiredIfVal) {
                        $schema['properties'][$attribute->getName()]['description'] = $schema['properties'][$attribute->getName()]['description'].'(this property is required if the '.(string) $requiredIfKey.' property equals '.(string) $requiredIfVal.' )';
                    }
                } else {
                    $schema['properties'][$attribute->getName()]['description'] = $schema['properties'][$attribute->getName()]['description'].'(this property is required if the '.(string) $requiredIfKey.' property equals '.(string) $requiredIfValue.' )';
                }
            }
        }

        return $schema;
    }

    /**
     * Turns a string to toSnakeCase.
     *
     * @param string $string the string to convert to toSnakeCase
     *
     * @return string the toSnakeCase represention of the string
     */
    public function toSnakeCase(string $value, ?string $delimiter = null): string
    {
        if (!\ctype_lower($value)) {
            $value = (string) \preg_replace('/\s+/u', '', \ucwords($value));
            $value = (string) \mb_strtolower(\preg_replace(
                '/(.)(?=[A-Z])/u',
                '$1'.($delimiter ?? '_'),
                $value
            ));
        }

        return $value;
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

    public function gePaginationParameters(): array
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

        foreach ($Entity->getAttributes() as $attribute) {
            if ($attribute->getType() == 'string' && $attribute->getSearchable()) {
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
}
