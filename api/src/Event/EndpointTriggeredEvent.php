<?php

namespace App\Event;

use App\Entity\Endpoint;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

// TODO: maybe we want to make an interface for Events?
class EndpointTriggeredEvent extends Event
{
    public const NAME = 'commongateway.endpoint.triggered';

    protected Endpoint $endpoint;
    protected Request $request;
    protected array $allowedHooks = ['DEFAULT', 'PRE_HANDLER', 'POST_HANDLER'];
    protected array $hooks;
//    protected bool $async = false; // todo: support async

    /**
     * Construct
     *
     * @param Endpoint $endpoint
     * @param Request $request
     * @param array $hooks
     *
     * @throws Exception
     */
    public function __construct(Endpoint $endpoint, Request $request, array $hooks = ['DEFAULT'])
    {
        $this->endpoint = $endpoint;
        $this->request = $request;
        if (count($hooks) == count(array_intersect($hooks, $this->allowedHooks))) {
            $this->hooks = $hooks;
        } else {
            throw new Exception("Invalid input for hooks: [".implode(', ', $hooks)."] allowed options: [".implode(', ', $this->allowedHooks)."]");
        }
    }

    public function getEndpoint(): Endpoint
    {
        return $this->endpoint;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getHooks(): array
    {
        return $this->hooks;
    }
}
