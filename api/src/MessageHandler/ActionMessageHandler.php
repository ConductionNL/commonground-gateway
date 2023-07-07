<?php

namespace App\MessageHandler;

use App\Entity\Action;
use App\Message\ActionMessage;
use App\Repository\ActionRepository;
use CommonGateway\CoreBundle\Subscriber\ActionSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ActionMessageHandler implements MessageHandlerInterface
{
    private ActionSubscriber $actionSubscriber;
    private ActionRepository $repository;
    private EntityManagerInterface $entityManager;

    public function __construct(ActionSubscriber $actionSubscriber, ActionRepository $repository, EntityManagerInterface $entityManager)
    {
        $this->actionSubscriber = $actionSubscriber;
        $this->repository = $repository;
        $this->entityManager = $entityManager;
    }

    public function __invoke(ActionMessage $message): void
    {
        $object = $this->repository->find($message->getObjectEntityId());

        try {
            if ($object instanceof Action) {
                var_dump('Running action '.$object->getName());
                $this->actionSubscriber->runFunction($object, $message->getData(), $message->getCurrentThrow());
            }
            $this->entityManager->clear();
        } catch (Exception $exception) {
            $this->entityManager->clear();

            throw $exception;
        }
    }
}
