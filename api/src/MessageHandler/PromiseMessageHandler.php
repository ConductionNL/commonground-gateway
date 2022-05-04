<?php

namespace App\MessageHandler;

use App\Entity\ObjectEntity;
use App\Message\PromiseMessage;
use App\Repository\ObjectEntityRepository;
use App\Service\ValidationService;
use Doctrine\Common\Collections\ArrayCollection;
use GuzzleHttp\Promise\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class PromiseMessageHandler implements MessageHandlerInterface
{
    private ObjectEntityRepository $objectEntityRepository;

    //@TODO: The code used in this service should be refactored and moved to another location
    private ValidationService $validationService;
    private Request $request;

    public function __construct(ObjectEntityRepository $objectEntityRepository, ValidationService $validationService)
    {
        $this->objectEntityRepository = $objectEntityRepository;
        $this->validationService = $validationService;
    }

    public function __invoke(PromiseMessage $promiseMessage): void
    {
        $this->validationService->setRequest($promiseMessage->getRequest());
        $object = $this->objectEntityRepository->find($promiseMessage->getObjectEntityId());
        $this->validationService->validateEntity($object, $promiseMessage->getPost());

        if (!empty($this->validationService->promises)) {
            Utils::settle($this->validationService->promises)->wait();

            foreach ($this->validationService->promises as $promise) {
                echo $promise->wait();
            }
        }

    }

//    public function getPromises(ObjectEntity $objectEntity, $post): array
//    {
//        $promises = [];
//        foreach($objectEntity->getAllSubresources(new ArrayCollection()) as $subresource) {
//            $promises = array_merge($promises, $this->getPromises($objectEntity, $post));
//        }
//        $promises[] = $this->validationService->createPromise($objectEntity, $post);
//
//        return $promises;
//    }
}
