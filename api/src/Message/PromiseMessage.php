<?php

namespace App\Message;

use Ramsey\Uuid\UuidInterface;

class PromiseMessage
{
    private UuidInterface $objectEntityId;

    public function __construct(UuidInterface $objectEntityId)
    {
        $this->objectEntityId = $objectEntityId;
    }

    public function getObjectEntityId(): UuidInterface
    {
        return $this->objectEntityId;
    }
}
