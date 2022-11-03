<?php

namespace App\Message;

use Ramsey\Uuid\UuidInterface;

class ActionMessage
{
    private UuidInterface $objectEntityId;
    private array $data;
    private ?string $currentThrow;

    public function __construct(UuidInterface $actionId, array $data, ?string $currentThrow)
    {
        $this->objectEntityId = $actionId;
        $this->data = $data;
        $this->currentThrow = $currentThrow;
    }

    public function getObjectEntityId(): UuidInterface
    {
        return $this->objectEntityId;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getCurrentThrow(): ?string
    {
        return $this->currentThrow;
    }
}
