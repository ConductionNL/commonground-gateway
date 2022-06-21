<?php

namespace App\Event;

use App\Entity\Endpoint;
use Symfony\Contracts\EventDispatcher\Event;

class EndpointTriggeredEvent extends Event
{
    public const NAME = 'common-gateway.endpoint.triggered';

    protected Endpoint $endpoint;
    protected string $method;

    public function __construct(Endpoint $endpoint, string $method)
    {
        $this->endpoint = $endpoint;
        $this->method = $method;
    }

    public function getEndpoint(): Endpoint
    {
        return $this->endpoint;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
