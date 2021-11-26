<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\SoapRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * This enity is used to transle incommong and outgoind soap calls
 *
 * @ApiResource()
 * @ORM\Entity(repositoryClass=SoapRepository::class)
 */
class Soap
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * The internal name of this soap call
     *
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * A short description of this soap call
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * The message type of this soap translation
     *
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * The entity form the EAV stack that this SOAP connection wants to use
     *
     * @ORM\ManyToOne(targetEntity=Entity::class, inversedBy="soap")
     * @ORM\JoinColumn(nullable=false)
     */
    private $entity;

    /**
     * An XML descriping the request that we want to recieve
     *
     * @ORM\Column(type="text")
     */
    private $request;

    /**
     * An array build of request that we want to recieve
     *
     * @ORM\Column(type="array")
     */
    private $requestSkeleton = [];

    /**
     * An array containing an request to entity translation in dot notation e.g. contact.firstname => person.name
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private $requestHydration = [];

    /**
     * An XML descriping the responce that we want t0 send
     *
     * @ORM\Column(type="text")
     */
    private $reponce;

    /**
     * An array build of responce that  we want to send
     *
     * @ORM\Column(type="array")
     */
    private $responceSkeleton = [];

    /**
     * An array containing an entity to reponce transaltion in dot notation e.g. person.name => contact.firstname
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private $responceHydration = [];


    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEntity(): ?Entity
    {
        return $this->entity;
    }

    public function setEntity(?Entity $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getRequestSkeleton(): ?array
    {
        return $this->requestSkeleton;
    }

    public function setRequestSkeleton(array $requestSkeleton): self
    {
        $this->requestSkeleton = $requestSkeleton;

        return $this;
    }

    public function getResponceSkeleton(): ?array
    {
        return $this->responceSkeleton;
    }

    public function setResponceSkeleton(array $responceSkeleton): self
    {
        $this->responceSkeleton = $responceSkeleton;

        return $this;
    }

    public function getRequestHydration(): ?array
    {
        return $this->requestHydration;
    }

    public function setRequestHydration(?array $requestHydration): self
    {
        $this->requestHydration = $requestHydration;

        return $this;
    }

    public function getResponceHydration(): ?array
    {
        return $this->responceHydration;
    }

    public function setResponceHydration(?array $responceHydration): self
    {
        $this->responceHydration = $responceHydration;

        return $this;
    }

    public function getReponce(): ?string
    {
        return $this->reponce;
    }

    public function setReponce(string $reponce): self
    {
        $this->reponce = $reponce;

        // Lets use this template to generate a skeleton
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'soap:Envelope']);
        $this->responceSkeleton = $xmlEncoder->decode($reponce);

        return $this;
    }

    public function getRequest(): ?string
    {
        return $this->request;
    }

    public function setRequest(string $request): self
    {
        $this->request = $request;

        // Lets use this template to generate a skeleton
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'soap:Envelope']);
        $this->requestSkeleton = $xmlEncoder->decode($request);

        return $this;
    }
}
