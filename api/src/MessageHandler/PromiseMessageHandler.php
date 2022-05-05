<?php

namespace App\MessageHandler;

use App\Entity\ObjectEntity;
use App\Message\PromiseMessage;
use App\Repository\ObjectEntityRepository;
use App\Service\ObjectEntityService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Promise\Utils;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class PromiseMessageHandler implements MessageHandlerInterface
{
    private ObjectEntityRepository $objectEntityRepository;
    private ObjectEntityService $objectEntityService;
    private EntityManagerInterface $entityManager;

    public function __construct(ObjectEntityRepository $objectEntityRepository, ObjectEntityService $objectEntityService, EntityManagerInterface $entityManager)
    {
        $this->objectEntityRepository = $objectEntityRepository;
        $this->objectEntityService = $objectEntityService;
        $this->entityManager = $entityManager;
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
    }

    public function getPromises(ObjectEntity $objectEntity): array
    {
        $promises = [];
        foreach($objectEntity->getSubresources() as $subresource) {
            $promises = array_merge($promises, $this->getPromises($subresource));
        }
        if($objectEntity->getEntity()->getGateway()) {
            $promise = $this->objectEntityService->createPromise($objectEntity);
            $promises[] = $promise;
            $objectEntity->addPromise($promise);
        }
        return $promises;
    }
}
