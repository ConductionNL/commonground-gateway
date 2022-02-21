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

    /**
     * Creates or updates a Log object with current request and response or given content.
     *
     * @param Request  $request  The request to fill this Log with.
     * @param Response $response The response to fill this Log with.
     * @param string   $content  The content to fill this Log with if there is no response.
     *
     * @return Log
     */
    public function saveLog(Request $request, Response $response = null, string $content = null, bool $finalSave = null): Log
    {
        $logRepo = $this->entityManager->getRepository('App:Log');
        $existingLog = $logRepo->findOneBy(['callId' => $this->session->get('callId')]);

        $existingLog ? $callLog = $existingLog : $callLog = new Log();

        $callLog->setType('in');
        $callLog->setRequestMethod($request->getMethod());
        $callLog->setRequestHeaders($request->headers->all());
        $callLog->setRequestQuery($request->query->all() ?? null);
        $callLog->setRequestPathInfo($request->getPathInfo());
        $callLog->setRequestLanguages($request->getLanguages() ?? null);
        $callLog->setRequestServer($request->server->all());
        $callLog->setRequestContent($request->getContent());
        $response && $callLog->setResponseStatus($this->getStatusWithCode($response->getStatusCode()));
        $response && $callLog->setResponseStatusCode($response->getStatusCode());
        $response && $callLog->setResponseHeaders($response->headers->all());

        if ($content) {
            $callLog->setResponseContent($content);
        // @todo Cant set response content if content is pdf
        } elseif ($response && !(is_string($response->getContent()) && strpos($response->getContent(), 'PDF'))) {
            $callLog->setResponseContent($response->getContent());
        }

        $routeName = $request->attributes->get('_route') ?? null;
        $routeParameters = $request->attributes->get('_route_params') ?? null;
        $callLog->setRouteName($routeName);
        $callLog->setRouteParameters($routeParameters);

        $time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        $callLog->setResponseTime(intval($time * 1000));

        if ($this->session) {
            // add before removing
            $callLog->setCallId($this->session->get('callId'));
            $callLog->setSession($this->session->getId());

            $callLog->setEndpoint($this->session->get('endpoint') ? $this->session->get('endpoint') : null);
            $callLog->setEntity($this->session->get('entity') ? $this->session->get('entity') : null);
            $callLog->setGateway($this->session->get('source') ? $this->session->get('source') : null);
            $callLog->setHandler($this->session->get('handler') ? $this->session->get('handler') : null);

            // remove before setting the session values
            if ($finalSave === true) {
                $this->session->remove('callId');
                $this->session->remove('endpoint');
                $this->session->remove('entity');
                $this->session->remove('source');
                $this->session->remove('handler');
            }
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
