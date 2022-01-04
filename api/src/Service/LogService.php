<?php

namespace App\Service;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LogService
{
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;

    public function __construct(
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ) {
        $this->entityManager = $entityManager;
        $this->session = $session;
    }

    public function createLog(Response $response, Request $request): Log
    {
        // TODO fix response

        $callLog = new Log();
        $callLog->setType('in');
        $callLog->setRequestMethod($request->getMethod());
        $callLog->setRequestHeaders($request->headers->all());
        $callLog->setRequestQuery($request->query->all() ?? null);
        $callLog->setRequestPathInfo($request->getPathInfo());
        $callLog->setRequestLanguages($request->getLanguages() ?? null);
        $callLog->setRequestServer($request->server->all());
        $callLog->setRequestContent($request->getContent());
        // @todo get status
        $callLog->setResponseStatus($this->getStatusWithCode($response->getStatusCode()));
        $callLog->setResponseStatusCode($response->getStatusCode());
        $callLog->setResponseHeaders($response->headers->all());
        // @todo Cant set response content if content is pdf
        if (is_string($response->getContent()) && strpos($response->getContent(), 'PDF')) {
            $callLog->setResponseContent('pdf');
        } else {
            $callLog->setResponseContent($response->getContent());
        }

        $routeName = $request->attributes->get('_route') ?? null;
        $routeParameters = $request->attributes->get('_route_params') ?? null;
        $callLog->setRouteName($routeName);
        $callLog->setRouteParameters($routeParameters);

        $now = new \DateTime();
        $requestTime = $request->server->get('REQUEST_TIME');
        $callLog->setResponseTime($now->getTimestamp() - $requestTime);

        if ($this->session) {
            // add before removing
            $callLog->setCallId($this->session->get('callId', null));
            $callLog->setSession($this->session->getId());

            // TODO endpoint disabled because might cause problems
            $callLog->setEndpoint($this->session->get('endpoint') ? $this->session->get('endpoint') : null);
            // $callLog->setEndpoint(null);

            $callLog->setEntity($this->session->get('entity') ? $this->session->get('entity') : null);
            $callLog->setSource($this->session->get('source') ? $this->session->get('source') : null);

            // TODO handler disabled because might cause problems
            $callLog->setHandler($this->session->get('handler') ? $this->session->get('handler') : null);
            // $callLog->setHandler(null);

            // remove before setting the session values
            $this->session->remove('callId');
            $this->session->remove('endpoint');
            $this->session->remove('entity');
            $this->session->remove('source');
            $this->session->remove('handler');

            // add session values
            $callLog->setSessionValues($this->session->all());
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
