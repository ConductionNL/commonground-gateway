<?php

namespace App\Subscriber;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LogSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['requestLog'],
        ];
    }

    /**
     * @param ResponseEvent $event
     */
    public function requestLog(ResponseEvent $event): Log
    {
        $response = $event->getResponse();
        $request = $event->getRequest();

        $callLog = new Log();
        $callLog->setType("in");
        if (isset($_SESSION['callId'])) {
            $callLog->setCallId($_SESSION['callId']);
        }
        $callLog->setRequestMethod($request->getMethod());
        $callLog->setRequestHeaders([$request->headers->get('host'), $request->headers->get('content-type')]);
        $callLog->setRequestQuery($request->query->all());
        $callLog->setRequestPathInfo($request->getPathInfo());
        $callLog->setRequestLanguages($request->getLanguages());
//        $callLog->setRequestServer($request->server->all());
        $callLog->setRequestContent($request->getContent());
        $callLog->setResponseStatus($this->getStatusWithCode($response->getStatusCode()) ?? null);
        $callLog->setResponseStatusCode($response->getStatusCode());
        $callLog->setResponseHeaders([$response->headers->get('host'), $response->headers->get('content-type')]);
        $callLog->setResponseContent([$response->getContent()]);
//        $callLog->setSession();
//        $callLog->setSessionValues();
//        $callLog->setResponseTime();

//        $callLog->setEndpoint();
//        $callLog->setEntity();
//        $callLog->setSource();
//        $callLog->setHandler();

        $this->entityManager->persist($callLog);
        $this->entityManager->flush();
        unset($_SESSION['callId']);

        return $callLog;
    }

    private function getStatusWithCode(int $statusCode): ?string
    {
        $reflectionClass = new ReflectionClass(Response::class);
        $constants = $reflectionClass->getConstants();

        foreach ($constants as $status => $value) {
            if ($value == $statusCode) {
                return $status;
            }
        }

        return null;
    }
}
