<?php

namespace App\Event;

use App\Entity\Action;
use Symfony\Contracts\EventDispatcher\Event;

class ActionEvent extends Event
{
    public const EVENTS = [
        'commongateway.handler.pre',
        'commongateway.handler.post',
        'commongateway.response.pre',
        'commongateway.cronjob.trigger',
        'commongateway.object.create',
        'commongateway.object.read',
        'commongateway.object.update',
        'commongateway.object.delete',
    ];

    protected string $type;
    protected array $data;

    protected Action $action;

    /**
     * Construct.
     *
     * @param string $type The type of event, must be in the EVENTS constant
     * @param array  $data The data from the request or elsewhere
     */
    public function __construct(string $type, array $data)
    {
        if (in_array($type, self::EVENTS)) {
            $this->type = $type;
        } else {
            return;
        }
        $this->data = $data;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
