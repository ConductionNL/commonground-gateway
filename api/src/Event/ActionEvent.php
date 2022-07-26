<?php

namespace App\Event;

use App\Entity\Action;
use App\Entity\Endpoint;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class ActionEvent extends Event
{
    public const EVENTS = [
        'commongateway.endpoint.triggered'
    ];

    protected string $type;
    protected array $data;

    protected Action $action;

    /**
     * Construct.
     *
     * @param string $type The type of event, must be in the EVENTS constant
     * @param array $data The data from the request or elsewhere
     */
    public function __construct(string $type, array $data)
    {
        if(in_array($type, self::EVENTS)) {
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
}
