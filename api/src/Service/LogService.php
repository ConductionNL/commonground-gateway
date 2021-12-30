<?php

namespace App\Service;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LogService
{
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;
    private RequestStack $requestStack;
    // private Response $response;

    public function __construct(EntityManagerInterface $entityManager, SessionInterface $session, RequestStack $requestStack, 
    // Response $response
    )
    {
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->request = $requestStack->getCurrentRequest();
        // $this->response = $response;
    }

    public function createLog(): Log
    {
        $callLog = new Log();
        $callLog->setType("in");
        $callLog->setRequestMethod($this->request->getMethod());
        $callLog->setRequestHeaders($this->request->headers->all());
        $callLog->setRequestQuery($this->request->query->all() ?? null);
        $callLog->setRequestPathInfo($this->request->getPathInfo());
        $callLog->setRequestLanguages($this->request->getLanguages() ?? null);
        $callLog->setRequestServer($this->request->server->all());
        $callLog->setRequestContent($this->request->getContent());
        // @todo get status
        // $callLog->setResponseStatus($this->getStatusWithCode($this->response->getStatusCode()));
        // $callLog->setResponseStatusCode($this->response->getStatusCode());
        // $callLog->setResponseHeaders($this->response->headers->all());
        // $callLog->setResponseContent($this->response->getContent());

        $routeName = $this->request->attributes->get('_route') ?? null;
        $routeParameters = $this->request->attributes->get('_route_params') ?? null;
        $callLog->setRouteName($routeName);
        $callLog->setRouteParameters($routeParameters);

        $now = new \DateTime();
        $requestTime = $this->request->server->get('REQUEST_TIME');
        $callLog->setResponseTime($now->getTimestamp() - $requestTime);

        if ($this->session) {
            // add before removing
            $callLog->setCallId($this->session->get('callId',null));
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
        // $this->entityManager->persist($callLog);
        // $this->entityManager->flush();

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
