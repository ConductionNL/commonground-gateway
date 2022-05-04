<?php

namespace App\MessageHandler;

use App\Message\PromiseMessage;
use App\Repository\ObjectEntityRepository;
use App\Service\ValidationService;
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
        $subResources = $object->getSubresources();
        //@TODO: break out promises to recursion
        $this->validationService->validateEntity($object, $promiseMessage->getPost());
        if (!empty($this->validationService->promises)) {
            Utils::settle($this->validationService->promises)->wait();

            foreach ($this->validationService->promises as $promise) {
                echo $promise->wait();
            }
        }
        foreach ($this->validationService->notifications as $notification) {
            $this->validationService->notify($notification['objectEntity'], $notification['method']);
        }

    }
}
