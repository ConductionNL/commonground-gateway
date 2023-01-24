<?php

namespace App\Service;

use App\Entity\Endpoint;
use App\Entity\Log;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Stopwatch\Stopwatch;

class LogService
{
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;
    private Stopwatch $stopwatch;
    private Security $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        Stopwatch $stopwatch,
        Security $security
    ) {
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->stopwatch = $stopwatch;
        $this->security = $security;
    }

    /**
     * Creates or updates a Log object with current request and response or given content.
     *
     * @param Request       $request         The request to fill this Log with.
     * @param Response|null $response        The response to fill this Log with.
     * @param int           $stopWatchNumber Any number, used to keep stopwatch categories apart from each other.
     * @param string|null   $content         The content to fill this Log with if there is no response.
     * @param bool|null     $finalSave
     * @param string        $type
     *
     * @return Log
     */
    public function saveLog(Request $request, Response $response = null, int $stopWatchNumber = 0, string $content = null, bool $finalSave = null, string $type = 'in'): Log
    {
        $this->stopwatch->start('getRepository(App:Log)'.$stopWatchNumber, "saveLog$stopWatchNumber");
        $logRepo = $this->entityManager->getRepository('App:Log');
        $this->stopwatch->stop('getRepository(App:Log)'.$stopWatchNumber);

        $this->stopwatch->start('getCallIdFromSession+getExistingLog'.$stopWatchNumber, "saveLog$stopWatchNumber");
        $this->session->get('callId') !== null && $type == 'in' ? $existingLog = $logRepo->findOneBy(['callId' => $this->session->get('callId'), 'type' => $type]) : $existingLog = null;
        $this->stopwatch->stop('getCallIdFromSession+getExistingLog'.$stopWatchNumber);

        $existingLog ? $callLog = $existingLog : $callLog = new Log();

        $callLog->setType($type);
        $callLog->setRequestMethod($request->getMethod());
        $callLog->setRequestHeaders($request->headers->all());
        //todo use eavService->realRequestQueryAll(), maybe replace this function to another service than eavService?
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
            $this->stopwatch->start('setCallId+sessionId'.$stopWatchNumber, "saveLog$stopWatchNumber");
            $callLog->setCallId($this->session->get('callId'));
            $callLog->setSession($this->session->getId());
            $this->stopwatch->stop('setCallId+sessionId'.$stopWatchNumber);

            $this->stopwatch->start('setEndpoint(getEndpointFromSession)'.$stopWatchNumber, "saveLog$stopWatchNumber");
            if ($this->session->get('endpoint')) {
                $endpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['id' => $this->session->get('endpoint')]);
            }
            $callLog->setEndpoint(!empty($endpoint) ? $endpoint : null);
            $this->stopwatch->stop('setEndpoint(getEndpointFromSession)'.$stopWatchNumber);

            if ($this->session->get('entitySource')) {
                $this->stopwatch->start('getEntitySourceFromSession'.$stopWatchNumber, "saveLog$stopWatchNumber");
                $sessionInfo = $this->session->get('entitySource');
                $this->stopwatch->stop('getEntitySourceFromSession'.$stopWatchNumber);
                $this->stopwatch->start('getEntitySourceFromDB'.$stopWatchNumber, "saveLog$stopWatchNumber");
                $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $sessionInfo['entity']]);
                $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['id' => $sessionInfo['source']]);
                $this->stopwatch->stop('getEntitySourceFromDB'.$stopWatchNumber);
            }
            $this->stopwatch->start('setEntitySource'.$stopWatchNumber, "saveLog$stopWatchNumber");
            $callLog->setEntity(!empty($entity) ? $entity : null);
            $callLog->setSource(!empty($source) ? $source : null);
            $this->stopwatch->stop('setEntitySource'.$stopWatchNumber);

            $this->stopwatch->start('setHandler(getHandlerFromSession)'.$stopWatchNumber, "saveLog$stopWatchNumber");
            if ($this->session->get('handler')) {
                $handler = $this->entityManager->getRepository('App:Handler')->findOneBy(['id' => $this->session->get('handler')]);
            }
            $callLog->setHandler(!empty($handler) ? $handler : null);
            $this->stopwatch->stop('setHandler(getHandlerFromSession)'.$stopWatchNumber);

            if ($this->session->get('object')) {
                $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $this->session->get('object')]);
            }
            $callLog->setObjectId(!empty($object) ? $this->session->get('object') : null);

            $user = $this->security->getUser();
            $callLog->setUserId($user !== null ? $user->getUserIdentifier() : null);

            // remove before setting the session values
//            if ($finalSave === true) {
//                $this->session->remove('callId');
//                $this->session->remove('endpoint');
//                $this->session->remove('entity');
//                $this->session->remove('source');
//                $this->session->remove('handler');
//            }

            // Set session values without relations we already know
            // $sessionValues = $this->session->all();
            // unset($sessionValues['endpoint']);
            // unset($sessionValues['source']);
            // unset($sessionValues['entity']);
            // unset($sessionValues['endpoint']);
            // unset($sessionValues['handler']);
            // unset($sessionValues['application']);
            // unset($sessionValues['applications']);
            // $callLog->setSessionValues($sessionValues);
        }

        // Make sure to remove unreads when we logged a successful get item call by a logged-in user.
        $this->stopwatch->start('RemoveUnreads'.$stopWatchNumber, "saveLog$stopWatchNumber");
        $this->removeUnreads($callLog);
        $this->stopwatch->stop('RemoveUnreads'.$stopWatchNumber);

        $this->stopwatch->start('PersistFlush'.$stopWatchNumber, "saveLog$stopWatchNumber");
        $this->entityManager->persist($callLog);
        $this->entityManager->flush();
        $this->stopwatch->stop('PersistFlush'.$stopWatchNumber);

        return $callLog;
    }

    /**
     * If a log is created for a successful get item call by a logged-in user, remove al unread objects for this user+object.
     *
     * @param Log $callLog
     *
     * @return void
     */
    private function removeUnreads(Log $callLog)
    {
        if ($callLog->getResponseStatusCode() === 200 && !empty($callLog->getEndpoint())
            && $callLog->getUserId() !== null && !empty($callLog->getObjectId()) &&
            strtolower($callLog->getRequestMethod()) === 'get' &&
            (strtolower($callLog->getEndpoint()->getMethod()) === 'get') ||
            in_array('get', array_map('strtolower', $callLog->getEndpoint()->getMethods()))) {
            // Check if there exist Unread objects for this Object+User. If so, delete them.
            $object = $this->entityManager->getRepository('App:ObjectEntity')->find($callLog->getObjectId());
            if ($object instanceof ObjectEntity) {
                $unreads = $this->entityManager->getRepository('App:Unread')->findBy(['object' => $object, 'userId' => $callLog->getUserId()]);
                foreach ($unreads as $unread) {
                    $this->entityManager->remove($unread);
                }
            }
        }
    }

    public function makeRequest(): Request
    {
        return new Request(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER
        );
    }

    public function getStatusWithCode(int $statusCode): ?string
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
