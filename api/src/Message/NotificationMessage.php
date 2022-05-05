<?php

namespace App\Message;

use Ramsey\Uuid\UuidInterface;

class NotificationMessage
{
    private UuidInterface $objectEntityId;
    private string $method;

    public function __construct(UuidInterface $objectEntityId, string $method)
    {
        $this->objectEntityId = $objectEntityId;
        $this->method = $method;
    }

    public function getObjectEntityId(): UuidInterface
    {
        return $this->objectEntityId;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
