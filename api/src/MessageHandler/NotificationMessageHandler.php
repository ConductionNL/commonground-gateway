<?php

namespace App\MessageHandler;

use App\Entity\ObjectEntity;
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
        $object = $this->repository->find($message->getObjectEntityId());
        if ($object instanceof ObjectEntity) {
//            var_dump('DispatchNotification: '.$object->getEntity()->getName().' - '.$message->getObjectEntityId()->toString().' - '.$object->getExternalId().' - '.$message->getMethod());
            $this->objectEntityService->notify($object, $message->getMethod());
        } else {
//            var_dump('No ObjectEntity found with id: '.$message->getObjectEntityId()->toString());
        }
    }
}
