<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about a common ground gateway.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *     collectionOperations={
 *          "post",
 *     		"get",
 *          "gateway_post"={
 *              "path"="/gateways/{name}/{endpoint}",
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
 *     },
 *      itemOperations={
 * 		    "get"={
 *              "validate"=false
 *          },
 * 	        "put",
 * 	        "delete",
 *          "gateway_get"={
 *              "path"="/gateways/{name}/{endpoint}",
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
 *              "path"="/gateways/{name}/{endpoint}",
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
 *              "path"="/gateways/{name}/{endpoint}",
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
 *              "path"="/gateways/{id}/change_log",
 *              "method"="get",
 *              "openapi_context" = {
 *                  "summary"="Changelogs",
 *                  "description"="Gets al the change logs for this resource"
 *              }
 *          },
 *          "get_audit_trail"={
 *              "path"="/gateways/{id}/audit_trail",
 *              "method"="get",
 *              "openapi_context" = {
 *                  "summary"="Audittrail",
 *                  "description"="Gets the audit trail for this resource"
 *              }
 *          },
 *     },
 * )
 * @ORM\Entity(repositoryClass="App\Repository\GatewayRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class)
 * @UniqueEntity("name")
 */
class Gateway
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     * @Groups({"read","read_secure"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private UuidInterface $id;

    /**
     * @var string The Name of the Gateway which is used in the commonGround service
     *
     * @Assert\NotNull
     * @Assert\Length(
     *      max = 255
     * )
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="arc"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @var string The location where the Gateway needs to be accessed
     *
     * @Assert\NotNull
     * @Assert\Length(
     *      max = 255
     * )
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="https://test.nl/api/v1/arc"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $location;

    /**
     * @var string The method used for authentication to the Gateway
     *
     * @Assert\NotNull
     * @Assert\Length(
     *      max = 255
     * )
     * @Assert\Choice({"apikey", "jwt", "username-password"})
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "enum"={"apikey", "jwt", "username-password"},
     *             "example"="apikey"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $auth;

    /**
     * @var ?string The Locale of the Gateway
     *
     * @Assert\Length(
     *      max = 10
     * )
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="nl"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private ?string $locale = null;

    /**
     * @var ?string The accept header used for the Gateway
     *
     * @Assert\Length(
     *      max = 255
     * )
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="application/json"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
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
     * @Groups({"read","read_secure","write"})
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
     * @Groups({"read","read_secure","write"})
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
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $secret = null;

    /**
     * @var ?string The username used for authentication to the Gateway
     *
     * @Assert\Length(
     *      max = 255
     * )
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="username@email.nl"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $username = null;

    /**
     * @var ?string The password used for authentication to the Gateway
     *
     * @Assert\Length(
     *      max = 255
     * )
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="password"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $password = null;

    /**
     * @var ?string The api key used for authentication to the Gateway
     *
     * @Assert\Length(
     *      max = 255
     * )
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="66505f8c-a80e-4bad-8678-d48ace4fbe4b"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $apikey = null;

    /**
     * @var ?string The documentation url for this gateway
     *
     * @Assert\Url
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="https://documentation.nl"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $documentation = null;

    /**
     * @ORM\OneToMany(targetEntity=GatewayResponceLog::class, mappedBy="gateway", orphanRemoval=true, fetch="EXTRA_LAZY")
     */
    private $responceLogs;

    /**
     * Setting logging to true will couse ALL responces to be logged (normaly we only log errors). Doing so wil dramaticly slow down the gateway and couse an increase in database size. This is not recomended outside of development purposes.
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $logging;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $oas = [];

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $paths = [];

    public function __construct()
    {
        $this->responceLogs = new ArrayCollection();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id): self
    {
        $this->id = $id;

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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;

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

    /**
     * @return Collection|GatewayResponceLog[]
     */
    public function getResponceLogs(): Collection
    {
        return $this->responceLogs;
    }

    public function addResponceLog(GatewayResponceLog $responceLog): self
    {
        if (!$this->responceLogs->contains($responceLog)) {
            $this->responceLogs[] = $responceLog;
            $responceLog->setGateway($this);
        }

        return $this;
    }

    public function removeResponceLog(GatewayResponceLog $responceLog): self
    {
        if ($this->responceLogs->removeElement($responceLog)) {
            // set the owning side to null (unless already changed)
            if ($responceLog->getGateway() === $this) {
                $responceLog->setGateway(null);
            }
        }

        return $this;
    }

    public function getLogging(): ?bool
    {
        return $this->logging;
    }

    public function setLogging(?bool $logging): self
    {
        $this->logging = $logging;

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
}
