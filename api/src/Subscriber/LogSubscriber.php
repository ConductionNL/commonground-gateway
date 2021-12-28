<?php

namespace App\Subscriber;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LogSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;

    public function __construct(EntityManagerInterface $entityManager, SessionInterface $session)
    {
        $this->entityManager = $entityManager;
        $this->session = $session;
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
        if (isset($session)) {
            $callLog->setCallId($session->get('callId',null));
        }
        $callLog->setRequestMethod($request->getMethod());
        $callLog->setRequestHeaders($request->headers->all());
        $callLog->setRequestQuery($request->query->all());
        $callLog->setRequestPathInfo($request->getPathInfo());
        $callLog->setRequestLanguages($request->getLanguages());
        $callLog->setRequestServer($request->server->all());
        $callLog->setRequestContent($request->getContent());
        // @todo get status
        $callLog->setResponseStatus($this->getStatusWithCode($response->getStatusCode()) ?? null);
        $callLog->setResponseStatusCode($response->getStatusCode());
        $callLog->setResponseHeaders($response->headers->all());
        $callLog->setResponseContent($response->getContent());

        $now = new \DateTime();
        $requestTime = $request->server->get('REQUEST_TIME');
        $callLog->setResponseTime($now->getTimestamp() - $requestTime);
        $callLog->setCreatedAt($now);

        if (isset($session)) {
            // add before removing
            $callLog->setSession($session->getId());
            $callLog->setEndpoint($session->get('endpoint', null));
            $callLog->setEntity($session->get('entity', null));
            $callLog->setSource($session->get('source', null));
            $callLog->setHandler($session->get('handler', null));

            // remove before setting the session values
            $session->remove('callId');
            $session->remove('endpoint');
            $session->remove('entity');
            $session->remove('source');
            $session->remove('handler');

            // add session values
            $callLog->setSessionValues($session->all());
        }

        $this->entityManager->persist($callLog);
        $this->entityManager->flush();

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
