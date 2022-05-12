<?php

namespace App\MessageHandler;

use App\Message\NotificationMessage;
use App\Repository\ObjectEntityRepository;
use App\Service\ObjectEntityService;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class NotificationMessageHandler implements MessageHandlerInterface
{
    private ObjectEntityService $objectEntityService;
    private ObjectEntityRepository $repository;

    public function __construct(ObjectEntityService $objectEntityService, ObjectEntityRepository $repository)
    {
        $this->objectEntityService = $objectEntityService;
        $this->repository = $repository;
    }

    public function __invoke(NotificationMessage $message): void
    {
        $this->objectEntityService->notify($this->repository->find($message->getObjectEntityId()), $message->getMethod());
    }
}
