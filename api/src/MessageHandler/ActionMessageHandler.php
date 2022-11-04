<?php

namespace App\MessageHandler;

use App\Entity\Action;
use App\Entity\ObjectEntity;
use App\Message\ActionMessage;
use App\Repository\ActionRepository;
use App\Subscriber\ActionSubscriber;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ActionMessageHandler implements MessageHandlerInterface
{
    private ActionSubscriber $actionSubscriber;
    private ActionRepository $repository;

    public function __construct(ActionSubscriber $actionSubscriber, ActionRepository $repository)
    {
        $this->actionSubscriber = $actionSubscriber;
        $this->repository = $repository;
    }

    public function __invoke(ActionMessage $message): void
    {
        $object = $this->repository->find($message->getObjectEntityId());
        if ($object instanceof Action) {
//            var_dump('DispatchNotification: '.$object->getEntity()->getName().' - '.$message->getObjectEntityId()->toString().' - '.$object->getExternalId().' - '.$message->getMethod());
            $this->actionSubscriber->runFunction($object, $message->getData(), $message->getCurrentThrow());
        } else {
//            var_dump('No ObjectEntity found with id: '.$message->getObjectEntityId()->toString());
        }
    }
}
