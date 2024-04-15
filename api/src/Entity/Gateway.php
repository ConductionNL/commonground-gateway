<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about a common ground gateway.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *     collectionOperations={
 *          "post"={"path"="/admin/gateways"},
 *     		"get"={"path"="/admin/gateways"},
 *          "gateway_post"={
 *              "path"="/api/gateways/{name}/{endpoint}",
 *              "method"="POST",
 *              "read"=false,
 *              "validate"=false,
 *              "requirements"={
 *                  "endpoint"=".+"
 *              },
 *              "openapi_context"={
 *                  "summary"="Gateway POST calls",
 *                  "description"="routes POST calls through gateway"
 *              }
 *          },
 *          "post_proxy"={
 *              "path"="/admin/sources/{id}/proxy",
 *              "method"="POST",
 *              "read"=false,
 *              "validate"=false,
 *              "requirements"={
 *                  "endpoint"=".+"
 *              },
 *              "openapi_context"={
 *                  "summary"="Proxy POST call to source",
 *                  "description"="Proxy POST call to source",
 *              }
 *          }
 *     },
 *      itemOperations={
 * 		    "get"={
 *              "path"="/admin/gateways/{id}",
 *              "validate"=false
 *          },
 * 	        "put"={"path"="/admin/gateways/{id}"},
 * 	        "delete"={"path"="/admin/gateways/{id}"},
 *          "get_proxy"={
 *              "path"="/admin/sources/{id}/proxy",
 *              "method"="GET",
 *              "read"=false,
 *              "validate"=false,
 *              "requirements"={
 *                  "endpoint"=".+"
 *              },
 *              "openapi_context"={
 *                  "summary"="Proxy GET call to source",
 *                  "description"="Proxy GET call to source",
 *              }
 *          },
 *          "gateway_get"={
 *              "path"="/api/gateways/{name}/{endpoint}",
 *              "method"="GET",
 *              "read"=false,
 *              "validate"=false,
 *              "requirements"={
 *                  "endpoint"=".+"
 *              },
 *              "openapi_context"={
 *                  "summary"="Gateway GET calls",
 *                  "description"="routes GET calls through gateway"
 *              }
 *          },
 *          "gateway_put"={
 *              "path"="/api/gateways/{name}/{endpoint}",
 *              "method"="PUT",
 *              "read"=false,
 *              "validate"=false,
 *              "requirements"={
 *                  "endpoint"=".+"
 *              },
 *              "openapi_context"={
 *                  "summary"="Gateway PUT calls",
 *                  "description"="routes PUT calls through gateway"
 *              }
 *          },
 *          "gateway_delete"={
 *              "path"="/api/gateways/{name}/{endpoint}",
 *              "method"="DELETE",
 *              "read"=false,
 *              "validate"=false,
 *              "requirements"={
 *                  "endpoint"=".+"
 *              },
 *              "openapi_context"={
 *                  "summary"="Gateway DELETE calls",
 *                  "description"="routes DELETE calls through gateway"
 *              }
 *          },
 *          "get_change_logs"={
 *              "path"="/api/gateways/{id}/change_log",
 *              "method"="get",
 *              "openapi_context" = {
 *                  "summary"="Changelogs",
 *                  "description"="Gets al the change logs for this resource"
 *              }
 *          },
 *          "get_audit_trail"={
 *              "path"="/api/gateways/{id}/audit_trail",
 *              "method"="get",
 *              "openapi_context" = {
 *                  "summary"="Audittrail",
 *                  "description"="Gets the audit trail for this resource"
 *              }
 *          },
 *     },
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\GatewayRepository")
 *
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "name": "exact",
 *     "reference": "exact"
 * })
 *
 * @UniqueEntity("name")
 * @UniqueEntity("reference")
 */
class Gateway
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     *
     * @Groups({"read","read_secure"})
     *
     * @ORM\Id
     *
     * @ORM\Column(type="uuid", unique=true)
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var string The Name of the Gateway which is used in the commonGround service
     *
     * @Assert\NotNull
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="arc"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $name = '';

    /**
     * @var string|null The description of the Gateway which is used in the commonGround service
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="arc"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @Groups({"read", "write"})
     *
     * @Assert\NotNull
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"default": null})
     */
    private ?string $reference = null;

    /**
     * @Groups({"read", "write"})
     *
     * @Assert\NotNull
     *
     * @ORM\Column(type="string", length=255, options={"default": "0.0.0"})
     */
    private string $version = '0.0.0';

    /**
     * @var string The location where the Gateway needs to be accessed
     *
     * @Assert\NotNull
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="https://test.nl/api/v1/arc"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    private string $location = '';

    /**
     * @var bool true if this Source is enabled and can be used.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private bool $isEnabled = true;

    /**
     * @var string The type of this gatewat
     *
     * @Assert\NotNull
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @Assert\Choice({"json", "xml", "soap", "ftp", "sftp"})
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "enum"={"json", "xml", "soap", "ftp", "sftp"},
     *             "example"="apikey"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(nullable=true, type="string", length=255)
     */
    private string $type = 'json';

    /**
     * @var string The header used for api key authorizations
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="Authorization"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $authorizationHeader = 'Authorization';

    /**
     * @var string The method used for authentication to the Gateway
     *
     * @Assert\NotNull
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @Assert\Choice({"apikey", "jwt", "username-password", "none", "jwt-HS256", "vrijbrp-jwt", "pink-jwt", "oauth"})
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "enum"={"apikey", "jwt", "username-password","none", "jwt-HS256", "vrijbrp-jwt", "pink-jwt", "oauth"},
     *             "example"="apikey"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $auth = 'none';

    /**
     * @var array|null The configuration for certain types of authentication methods.
     *
     * This contains configuration for e.g. oauth authentication. For oauth the following fields are available: `case` to set if the credential fields have to be in camelCase or snake_case, `additionalFields` for fields that the api requires on top of clientId and clientSecret, `tokenPath` for the path on the api where the token can be requested and `tokenField` for the response field containing the token.
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $authenticationConfig = [];

    /**
     * @var string The method used for authentication to the Gateway
     *
     * @Assert\NotNull
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @Assert\Choice({"header", "query", "form_params", "json"})
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "enum"={"header", "query", "form_params", "json"},
     *             "example"="header"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $authorizationPassthroughMethod = 'header';

    /**
     * @var ?string The Locale of the Gateway
     *
     * @Assert\Length(
     *      max = 10
     * )
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="nl"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private ?string $locale = null;

    /**
     * @var ?string The accept header used for the Gateway
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="application/json"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $accept = null;

    /**
     * @var ?string The JWT used for authentication to the Gateway
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $jwt = null;

    /**
     * @var ?string The JWT ID used for authentication to the Gateway
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="conduction"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $jwtId = null;

    /**
     * @var ?string The JWT secret used for authentication to the Gateway
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="secret"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $secret = null;

    /**
     * @var ?string The username used for authentication to the Gateway
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="username@email.nl"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $username = null;

    /**
     * @var ?string The password used for authentication to the Gateway
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="password"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $password = null;

    /**
     * @var ?string The api key used for authentication to the Gateway
     *
     * @Assert\Length(
     *      max = 255
     * )
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="66505f8c-a80e-4bad-8678-d48ace4fbe4b"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $apikey = null;

    /**
     * @var ?string The documentation url for this gateway
     *
     * @Assert\Url
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="https://documentation.nl"
     *         }
     *     }
     * )
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $documentation = null;

    /**
     * @var array Configuration for logging, when an api call is made on the source we can log some information for this call. With this array you can enable/disable what will be logged.
     *
     * @Assert\NotNull
     *
     * @Groups({"read","write"})
     *
     * @ORM\Column(type="array")
     */
    private array $loggingConfig = [
        'callMethod'            => true,
        'callUrl'               => true,
        'callQuery'             => true,
        'callContentType'       => true,
        'callBody'              => true,
        'responseStatusCode'    => true,
        'responseContentType'   => true,
        'responseBody'          => true,
        'maxCharCountBody'      => 500,
        'maxCharCountErrorBody' => 2000,
    ];

    /**
     * @var array ...
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private $oas = [];

    /**
     * @var array ...
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private $paths = [];

    /**
     * Headers that are required to be added for every request.
     *
     * @Groups({"read","read_secure","write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private $headers = [];

    /**
     * @var array Config to translate specific calls to a different method or endpoint. When changing the endpoint, if you want, you can use {id} to specify the location of the id in the endpoint.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private array $translationConfig = [];

    /**
     * @Groups({"read", "write"})
     *
     * @MaxDepth(1)
     *
     * @ORM\OneToMany(targetEntity=CollectionEntity::class, mappedBy="source")
     */
    private ?Collection $collections;

    /**
     * @var array|null The guzzle configuration of the source
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $configuration = [
        "verify" => true
    ];

    /**
     * @var array|null The configuration for endpoints on this source, mostly mapping for now.
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $endpointsConfig = [];

    /**
     * @var string The status from the last call made to this source
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="200 OK status received on /api-endpoint"
     *         }
     *     }
     * )
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="string", nullable=true, options={"default":"No calls have been made yet to this source"})
     */
    private string $status = 'No calls have been made yet to this source';

    /**
     * @var ?Datetime The datetime from the last request made to this source
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="datetime",
     *             "example"="2020-02-15T120:50:00"
     *         }
     *     }
     * )
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="datetime", nullable=true, options={"default":null})
     */
    private ?Datetime $lastCall = null;

    /**
     * @var ?Datetime The datetime from the last synchronization made to this source
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="datetime",
     *             "example"="2020-02-15T120:50:00"
     *         }
     *     }
     * )
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="datetime", nullable=true, options={"default":null})
     */
    private ?Datetime $lastSync = null;

    /**
     * @var int The count of total sync objects from this source
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="integer",
     *             "example"=52
     *         }
     *     }
     * )
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="integer", options={"default":0})
     */
    private int $objectCount = 0;

    /**
     * @var Collection The synchronizations of this source
     *
     * @Groups({"write"})
     *
     * @ORM\OneToMany(targetEntity=Synchronization::class, fetch="EXTRA_LAZY", mappedBy="gateway", orphanRemoval=true)
     */
    private Collection $synchronizations;

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource was last Modified
     *
     * @Groups({"read"})
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    /**
     * @var bool Whether the source is in test mode
     *
     * @Groups({"read", "write"})
     *
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private bool $test = false;

    /**
     * @ORM\OneToMany(targetEntity=Endpoint::class, mappedBy="proxy")
     */
    private $proxies;

    /**
     * Constructor for Gateway.
     *
     * @param array|null $configuration If not empty this array is used to call fromArray()
     */
    public function __construct(?array $configuration = [])
    {
        $this->collections = new ArrayCollection();
        $this->synchronizations = new ArrayCollection();
        $this->proxies = new ArrayCollection();

        if ($configuration) {
            $this->fromSchema($configuration);
        }
    }

    /**
     * Create or update this Source from an external schema array.
     *
     * This function is used to update and create sources form source.json objects.
     *
     * @param array $schema The schema to load.
     *
     * @return $this This Source.
     */
    public function fromSchema(array $schema): self
    {
        // Basic stuff
        if (array_key_exists('$id', $schema)) {
            $this->setReference($schema['$id']);
        }
        if (array_key_exists('version', $schema)) {
            $this->setVersion($schema['version']);
        }

        // Do not set jwt, secret, password or apikey this way!
        array_key_exists('title', $schema) ? $this->setName($schema['title']) : '';
        array_key_exists('description', $schema) ? $this->setDescription($schema['description']) : '';
        array_key_exists('location', $schema) ? $this->setLocation($schema['location']) : '';
        array_key_exists('authorizationHeader', $schema) ? $this->setAuthorizationHeader($schema['authorizationHeader']) : '';
        array_key_exists('auth', $schema) ? $this->setAuth($schema['auth']) : '';
        array_key_exists('authorizationPassthroughMethod', $schema) ? $this->setAuthorizationPassthroughMethod($schema['authorizationPassthroughMethod']) : '';
        array_key_exists('locale', $schema) ? $this->setLocale($schema['locale']) : '';
        array_key_exists('accept', $schema) ? $this->setAccept($schema['accept']) : '';
        array_key_exists('jwtId', $schema) ? $this->setJwtId($schema['jwtId']) : '';
        array_key_exists('username', $schema) ? $this->setUsername($schema['username']) : '';
        array_key_exists('documentation', $schema) ? $this->setDocumentation($schema['documentation']) : '';
        array_key_exists('loggingConfig', $schema) ? $this->setLoggingConfig($schema['loggingConfig']) : '';
        array_key_exists('headers', $schema) ? $this->setHeaders($schema['headers']) : '';
        array_key_exists('translationConfig', $schema) ? $this->setTranslationConfig($schema['translationConfig']) : '';
        array_key_exists('type', $schema) ? $this->setType($schema['type']) : '';
        if (isset($schema['configuration']) === true) {
            if (isset($schema['configuration']['verify']) === false) {
                $schema['configuration']['verify'] = true;
            }
            $this->setConfiguration($schema['configuration']);
        }
        array_key_exists('endpointsConfig', $schema) ? $this->setEndpointsConfig($schema['endpointsConfig']) : '';
        array_key_exists('isEnabled', $schema) ? $this->setIsEnabled($schema['isEnabled']) : '';

        return $this;
    }

    /**
     * Convert this Gateway to a schema.
     *
     * @return array Schema array.
     */
    public function toSchema(): array
    {
        // Do not return jwt, secret, password or apikey this way!
        return [
            '$id'                            => $this->getReference(), //@todo dit zou een interne uri verwijzing moeten zijn maar hebben we nog niet
            '$schema'                        => 'https://docs.commongateway.nl/schemas/Gateway.schema.json',
            'title'                          => $this->getName(),
            'description'                    => $this->getDescription(),
            'version'                        => $this->getVersion(),
            'name'                           => $this->getName(),
            'location'                       => $this->getLocation(),
            'authorizationHeader'            => $this->getAuthorizationHeader(),
            'auth'                           => $this->getAuth(),
            'authorizationPassthroughMethod' => $this->getAuthorizationPassthroughMethod(),
            'locale'                         => $this->getLocale(),
            'accept'                         => $this->getAccept(),
            'jwtId'                          => $this->getJwtId(),
            'username'                       => $this->getUsername(),
            'documentation'                  => $this->getDocumentation(),
            'loggingConfig'                  => $this->getLoggingConfig(),
            'headers'                        => $this->getHeaders(),
            'translationConfig'              => $this->getTranslationConfig(),
            'type'                           => $this->getType(),
            'configuration'                  => $this->getConfiguration(),
            'endpointsConfig'                => $this->getEndpointsConfig(),
            'isEnabled'                      => $this->getIsEnabled(),
        ];
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function export(): ?array
    {
        $data = [
            'name'                           => $this->getName(),
            'location'                       => $this->getLocation(),
            'authorizationHeader'            => $this->getAuthorizationHeader(),
            'auth'                           => $this->getAuth(),
            'authorizationPassthroughMethod' => $this->getAuthorizationPassthroughMethod(),
            'locale'                         => $this->getLocale(),
            'accept'                         => $this->getAccept(),
            'jwt'                            => $this->getJwt(),
            'jwtId'                          => $this->getJwtId(),
            'secret'                         => $this->getSecret(),
            'username'                       => $this->getUsername(),
            'password'                       => $this->getPassword(),
            'apikey'                         => $this->getApikey(),
            'documentation'                  => $this->getDocumentation(),
            'loggingConfig'                  => $this->getLoggingConfig(),
            'headers'                        => $this->getHeaders(),
            'translationConfig'              => $this->getTranslationConfig(),
            'type'                           => $this->getType(),
        ];

        return array_filter($data, fn ($value) => !is_null($value) && $value !== '' && $value !== []);
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = Uuid::fromString($id);

        return $this;
    }

    public function getTranslationConfig(): ?array
    {
        return $this->translationConfig;
    }

    public function setTranslationConfig(?array $translationConfig): self
    {
        $this->translationConfig = $translationConfig;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getAuthorizationHeader(): ?string
    {
        return $this->authorizationHeader;
    }

    public function setAuthorizationHeader(string $authorizationHeader): self
    {
        $this->authorizationHeader = $authorizationHeader;

        return $this;
    }

    public function getAuthenticationConfig(): ?array
    {
        return $this->authenticationConfig;
    }

    public function setAuthenticationConfig(?array $authenticationConfig = []): self
    {
        $this->authenticationConfig = $authenticationConfig;

        return $this;
    }

    public function getAuthorizationPassthroughMethod(): ?string
    {
        return $this->authorizationPassthroughMethod;
    }

    public function setAuthorizationPassthroughMethod(string $authorizationPassthroughMethod): self
    {
        $this->authorizationPassthroughMethod = $authorizationPassthroughMethod;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAuth(): string
    {
        return $this->auth;
    }

    public function setAuth(string $auth): self
    {
        $this->auth = $auth;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getAccept(): ?string
    {
        return $this->accept;
    }

    public function setAccept(?string $accept): self
    {
        $this->accept = $accept;

        return $this;
    }

    public function getJwt(): ?string
    {
        return $this->jwt;
    }

    public function setJwt(?string $jwt): self
    {
        $this->jwt = $jwt;

        return $this;
    }

    public function getJwtId(): ?string
    {
        return $this->jwtId;
    }

    public function setJwtId(?string $jwtId): self
    {
        $this->jwtId = $jwtId;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apikey;
    }

    public function setApiKey(?string $apikey): self
    {
        $this->apikey = $apikey;

        return $this;
    }

    public function getDocumentation(): ?string
    {
        return $this->documentation;
    }

    public function setDocumentation(?string $documentation): self
    {
        $this->documentation = $documentation;

        return $this;
    }

    public function getLoggingConfig(): ?array
    {
        return $this->loggingConfig;
    }

    public function setLoggingConfig(array $loggingConfig): self
    {
        $this->loggingConfig = array_merge($loggingConfig);

        return $this;
    }

    public function getOas(): ?array
    {
        return $this->oas;
    }

    public function setOas(?array $oas): self
    {
        $this->oas = $oas;

        return $this;
    }

    public function getPaths(): ?array
    {
        return $this->paths;
    }

    public function setPaths(?array $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function setHeaders(?array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @return Collection|CollectionEntity[]
     */
    public function getCollections(): Collection
    {
        return $this->collections;
    }

    public function addCollection(CollectionEntity $collection): self
    {
        if (!$this->collections->contains($collection)) {
            $this->collections[] = $collection;
            $collection->setSource($this);
        }

        return $this;
    }

    public function removeCollection(CollectionEntity $collection): self
    {
        if ($this->collections->removeElement($collection)) {
            // set the owning side to null (unless already changed)
            if ($collection->getSource() === $this) {
                $collection->setSource(null);
            }
        }

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getLastCall(): ?DateTime
    {
        return $this->lastCall;
    }

    public function setLastCall(?DateTime $lastCall): self
    {
        $this->lastCall = $lastCall;

        return $this;
    }

    public function getLastSync(): ?DateTime
    {
        return $this->lastSync;
    }

    public function setLastSync(?DateTime $lastSync): self
    {
        $this->lastSync = $lastSync;

        return $this;
    }

    public function getObjectCount(): int
    {
        return $this->synchronizations->count();
    }

    // Should not be used or needed
    // public function setObjectCount(int $objectCount): self
    // {
    //     $this->objectCount = $objectCount;

    //     return $this;
    // }

    /**
     * @return Collection|Synchronization[]
     */
    public function getSynchronizations(): Collection
    {
        return $this->synchronizations;
    }

    public function addSynchronization(Synchronization $synchronization): self
    {
        if (!$this->synchronizations->contains($synchronization)) {
            $this->synchronizations[] = $synchronization;
            $synchronization->setSource($this);
        }

        return $this;
    }

    public function getDateCreated(): ?DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateModified(): ?DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'auth'                  => $this->getAuth(),
            'authorizationHeader'   => $this->getAuthorizationHeader(),
            'passthroughMethod'     => $this->getAuthorizationPassthroughMethod(),
            'location'              => $this->getLocation(),
            'apikey'                => $this->getApiKey(),
            'jwt'                   => $this->getJwt(),
            'secret'                => $this->getSecret(),
            'id'                    => $this->getJwtId(),
            'locale'                => $this->getLocale(),
            'accept'                => $this->getAccept(),
            'username'              => $this->getUsername(),
            'password'              => $this->getPassword(),
        ];
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(?array $configuration = []): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getEndpointsConfig(): ?array
    {
        return $this->endpointsConfig;
    }

    public function setEndpointsConfig(?array $endpointsConfig = []): self
    {
        $this->endpointsConfig = $endpointsConfig;

        return $this;
    }

    public function getTest(): ?bool
    {
        return $this->test;
    }

    public function setTest(?bool $test): self
    {
        $this->test = $test;

        return $this;
    }

    /**
     * @return Collection|Endpoint[]
     */
    public function getProxies(): Collection
    {
        return $this->proxies;
    }

    public function addProxy(Endpoint $proxy): self
    {
        if (!$this->proxies->contains($proxy)) {
            $this->proxies[] = $proxy;
            $proxy->setProxy($this);
        }

        return $this;
    }

    public function removeProxy(Endpoint $proxy): self
    {
        if ($this->proxies->removeElement($proxy)) {
            // set the owning side to null (unless already changed)
            if ($proxy->getProxy() === $this) {
                $proxy->setProxy(null);
            }
        }

        return $this;
    }
}
