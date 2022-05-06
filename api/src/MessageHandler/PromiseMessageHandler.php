<?php

namespace App\MessageHandler;

use App\Entity\ObjectEntity;
use App\Message\NotificationMessage;
use App\Message\PromiseMessage;
use App\Repository\ObjectEntityRepository;
use App\Service\ObjectEntityService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Promise\Utils;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PromiseMessageHandler implements MessageHandlerInterface
{
    private ObjectEntityRepository $objectEntityRepository;
    private ObjectEntityService $objectEntityService;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;

    public function __construct(ObjectEntityRepository $objectEntityRepository, ObjectEntityService $objectEntityService, EntityManagerInterface $entityManager, MessageBusInterface $messageBus)
    {
        $this->objectEntityRepository = $objectEntityRepository;
        $this->objectEntityService = $objectEntityService;
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
    }

    public function __invoke(PromiseMessage $promiseMessage): void
    {
        $object = $this->objectEntityRepository->find($promiseMessage->getObjectEntityId());
        $promises = $this->getPromises($object);
        if (!empty($promises)) {
            Utils::settle($promises)->wait();

            foreach ($promises as $promise) {
                echo $promise->wait();
            }
        }
        $this->entityManager->persist($object);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new NotificationMessage($object->getId(), $promiseMessage->getMethod()));
    }

    public function getPromises(ObjectEntity $objectEntity): array
    {
        $promises = [];
        foreach ($objectEntity->getSubresources() as $subresource) {
            $promises = array_merge($promises, $this->getPromises($subresource));
        }
        if ($objectEntity->getEntity()->getGateway()) {
            $promise = $this->objectEntityService->createPromise($objectEntity);
            $promises[] = $promise;
            $objectEntity->addPromise($promise);
        }

        return $promises;
    }
}
