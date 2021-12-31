<?php

namespace App\Subscriber;

use App\Entity\Log;
use App\Service\LogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LogSubscriber implements EventSubscriberInterface
{
    private LogService $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['requestLog'],
        ];
    }

    /**
     */
    public function requestLog(ResponseEvent $event)
    {
        $response = $event->getResponse();
        $request = $event->getRequest();

        // $this->logService->createLog($response, $request);
    }
}
