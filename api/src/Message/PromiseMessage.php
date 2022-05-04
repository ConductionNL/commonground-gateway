<?php

namespace App\Message;

use App\Entity\ObjectEntity;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Request;

class PromiseMessage
{
    private UuidInterface $objectEntityId;
    private array $post;
    private Request $request;

    public function __construct(UuidInterface $objectEntityId, array $post, Request $request)
    {
        $this->objectEntityId = $objectEntityId;
        $this->post = $post;
        $this->request = $request;
    }

    public function getObjectEntityId(): UuidInterface
    {
        return $this->objectEntityId;
    }

    public function getPost(): array
    {
        return $this->post;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }


}
