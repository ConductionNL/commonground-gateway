<?php

namespace App\Message;

use App\Entity\Entity;
use Ramsey\Uuid\UuidInterface;

class SyncPageMessage
{
    private array $callServiceData;
    private int $page;
    private Entity $entity;

    public function __construct(array $callServiceData, int $page, Entity $entity)
    {
        $this->callServiceData = $callServiceData;
        $this->page = $page;
        $this->entity = $entity;
    }

    public function getCallServiceData(): array
    {
        return $this->callServiceData;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }
}
