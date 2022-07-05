<?php

namespace App\Event;

use App\Entity\Endpoint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class EndpointTriggeredEvent extends Event
{
    public const NAME = 'commongateway.endpoint.triggered';

    protected Endpoint $endpoint;
    protected Request $request;

    public function __construct(Endpoint $endpoint, Request $request)
    {
        $this->endpoint = $endpoint;
        $this->request = $request;
    }

    public function getEndpoint(): Endpoint
    {
        return $this->endpoint;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
