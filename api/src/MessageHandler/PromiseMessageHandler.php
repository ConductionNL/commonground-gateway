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
    private array $notifications;

    public function __construct(ObjectEntityRepository $objectEntityRepository, ObjectEntityService $objectEntityService, EntityManagerInterface $entityManager, MessageBusInterface $messageBus)
    {
        $this->objectEntityRepository = $objectEntityRepository;
        $this->objectEntityService = $objectEntityService;
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
        $this->notifications = [];
    }

    public function __invoke(PromiseMessage $promiseMessage): void
    {
        $object = $this->objectEntityRepository->find($promiseMessage->getObjectEntityId());
        $parents = [];
        $promises = $this->getPromises($object, $parents);
        if (!empty($promises)) {
            Utils::settle($promises)->wait();

            foreach ($promises as $promise) {
                echo $promise->wait();
            }
        }
        $this->entityManager->persist($object);
        $this->entityManager->flush();

        foreach ($this->notifications as $objectEntityId) {
            $this->messageBus->dispatch(new NotificationMessage($objectEntityId, $promiseMessage->getMethod()));
        }
    }

    public function getPromises(ObjectEntity $objectEntity, array &$parentObjects): array
    {
        $promises = [];
        $parentObjects[] = $objectEntity;
        foreach ($objectEntity->getSubresources() as $subresource) {
            if(in_array($objectEntity, $parentObjects)){
                continue;
            }
            $promises = array_merge($promises, $this->getPromises($subresource, $parentObjects));
        }
        if ($objectEntity->getEntity()->getGateway()) {
            $promise = $this->objectEntityService->createPromise($objectEntity);
            $promises[] = $promise;
            $objectEntity->addPromise($promise);
        }

        $this->notifications[] = $objectEntity->getId();

        return $promises;
    }
}
