<?php

namespace App\MessageHandler;

use App\Entity\ObjectEntity;
use App\Message\NotificationMessage;
use App\Message\PromiseMessage;
use App\Repository\ObjectEntityRepository;
use App\Service\ObjectEntityService;
use DateInterval;
use DateTime;
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
        $this->objectEntityService->notifications = []; // Make sure we reset notifications array.
        $object = $this->objectEntityRepository->find($promiseMessage->getObjectEntityId());
        $this->objectEntityService->functionService->removeResultFromCache = [];
        $promises = $this->getPromises($object, [], $promiseMessage->getMethod());
        if (!empty($promises)) {
            Utils::settle($promises)->wait();

            foreach ($promises as $promise) {
                echo $promise->wait();
            }
        }
        $this->entityManager->persist($object);
        $this->entityManager->flush();

        foreach ($this->objectEntityService->notifications as $notification) {
            $this->messageBus->dispatch(new NotificationMessage($notification['id'], $notification['method']));
        }
    }

    public function getPromises(ObjectEntity $objectEntity, array $parentObjects, string $method, int $level = 0): array
    {
        $promises = [];
        if (in_array($objectEntity, $parentObjects) || $level > 3) {
            return $promises;
        }
        $parentObjects[] = $objectEntity;
        foreach ($objectEntity->getSubresources() as $subresource) {
            $promises = array_merge($promises, $this->getPromises($subresource, $parentObjects, $method, $level + 1));
        }
        if ($objectEntity->getEntity()->getSource()) {
            // createPromise will add a notification if promise is successful
            $promise = $this->objectEntityService->createPromise($objectEntity, $method);
            $promises[] = $promise;
            $objectEntity->addPromise($promise);
        } else {
            // todo: very hacky code
            // The code below makes sure that if we do not create a promise^, the method of the notification won't ...
            // ... be POST when the ObjectEntity already exists for more than 5 minutes. In this case we want a ...
            // ... notification with method = PUT not POST.
            $now = new DateTime();
            $interval = $objectEntity->getDateCreated()->diff($now);
            $compareDate = new DateTime();
            $compareDate->add($interval);
            $now->add(new DateInterval('PT5M'));
            if ($compareDate > $now) {
                $method = 'PUT';
            }

            // Create Notification
//            var_dump('NOTIFICATION: '.$objectEntity->getEntity()->getName().' - '.$objectEntity->getId()->toString().' - '.$objectEntity->getExternalId().' - '.$method);
            $this->objectEntityService->notifications[] = ['id' => $objectEntity->getId(), 'method' => $method];
        }

        return $promises;
    }
}
