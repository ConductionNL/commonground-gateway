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
        'commongateway.action.event',
        'commongateway.object.pre.delete',
        'commongateway.object.pre.create',
        'commongateway.object.pre.update',
        'commongateway.object.post.delete',
        'commongateway.object.post.create',
        'commongateway.object.post.update',
        'commongateway.object.post.read'
    ];

    protected string $type;

    /*
     * An overwrite for type
     */
    protected ?string $subType;

    /*
     * The data for the event
     */
    protected array $data;

    protected Action $action;

    /**
     * Construct.
     *
     * @param string $type The type of event, must be in the EVENTS constant
     * @param array  $data The data from the request or elsewhere
     */
    public function __construct(string $type, array $data, $subType = null)
    {
        if (in_array($type, self::EVENTS)) {
            $this->type = $type;
        } else {
            $subType = $type;
            $this->type = 'commongateway.action.event';
        }
        $this->data = $data;
        $this->subType = $subType;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSubType(): ?string
    {
        return $this->subType;
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

    public function setSubType(?string $subType): self
    {
        $this->subType = $subType;

        return $this;
    }
}
