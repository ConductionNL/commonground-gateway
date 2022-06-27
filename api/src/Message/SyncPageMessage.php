<?php

namespace App\Message;

use App\Entity\Entity;
use Ramsey\Uuid\UuidInterface;

class SyncPageMessage
{
    private array $callServiceData;
    private int $page;
    private string $entityId;
    private array $sessionData;

    /**
     * @TODO
     *
     * @param array $callServiceData Must contain the following keys: 'component', 'url', 'query', 'headers'
     * @param int $page
     * @param string $entityId
     * @param array $sessionData
     */
    public function __construct(array $callServiceData, int $page, string $entityId, array $sessionData)
    {
        $this->callServiceData = $callServiceData;
        $this->page = $page;
        $this->entityId = $entityId;
        $this->sessionData = $sessionData;
    }

    public function getCallServiceData(): array
    {
        return $this->callServiceData;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getSessionData(): array
    {
        return $this->sessionData;
    }
}
