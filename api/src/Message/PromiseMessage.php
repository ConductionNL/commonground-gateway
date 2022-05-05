<?php

namespace App\Message;

use App\Entity\ObjectEntity;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Request;

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
