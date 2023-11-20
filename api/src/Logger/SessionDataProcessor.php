<?php

namespace App\Logger;

use App\Event\ActionEvent;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionDataProcessor
{
    private SessionInterface $session;
    private RequestStack $requestStack;

    /**
     * @var EventDispatcherInterface The event dispatcher.
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var EntityManagerInterface The entity manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @param SessionInterface $session
     * @param RequestStack $requestStack
     * @param EventDispatcherInterface $eventDispatcher
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        SessionInterface $session,
        RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher,
        EntityManagerInterface $entityManager
    )
    {
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->entityManager = $entityManager;
    }

    /**
     * Update the context with data from the session and the request stack.
     *
     * @param array $record The log record to update the context for.
     *
     * @return array The updated context.
     */
    public function updateContext(array $record): array
    {
        $context = $record['context'];

        $context['session'] = $this->session->getId();
        $context['process'] = $this->session->has('process') === true ? $this->session->get('process') : '';
        $context['endpoint'] = $this->session->has('endpoint') === true ? $this->session->get('endpoint') : '';
        $context['schema'] = $this->session->has('schema') === true ? $this->session->get('schema') : '';
        $context['object'] = $this->session->has('object') === true ? $this->session->get('object') : '';
        $context['cronjob'] = $this->session->has('cronjob') === true ? $this->session->get('cronjob') : '';
        $context['action'] = $this->session->has('action') === true ? $this->session->get('action') : '';
        $context['mapping'] = $this->session->has('mapping') === true ? $this->session->get('mapping') : '';
        $context['source'] = $this->session->has('source') === true ? $this->session->get('source') : '';

        // Add more to context if we are dealing with a log containing sourceCall data
        if (isset($record['data']['sourceCall'])) {
            $context = $this->addSourceCallContext($context, $record['data']['sourceCall'], $record['level_name']);
        }

        $context['user'] = $this->session->has('user') === true ? $this->session->get('user') : '';
        $context['organization'] = $this->session->has('organization') === true ? $this->session->get('organization') : '';
        $context['application'] = $this->session->has('application') === true ? $this->session->get('application') : '';
        $context['plugin'] = isset($record['data']['plugin']) === true ? $record['data']['plugin'] : '';
        $context['host'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getHost() : '';
        $context['ip'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getClientIp() : '';
        $context['method'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getMethod() : '';

        // Add more to context for higher level logs
        if (in_array($record['level_name'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']) === true) {
            $context = $this->addErrorContext($context, $record['level_name']);
        }

        return $context;
    }

    /**
     * Update the context with data from the session and the request stack. For log records with level ERROR or higher.
     *
     * @param array $context The log context we are updating.
     * @param array $callData The [data][sourceCall] info of the log record we are updating the context for.
     * @param string $levelName The level name of the log record we are updating the context for.
     *
     * @return array The updated context.
     */
    private function addSourceCallContext(array $context, array $callData, string $levelName): array
    {
        $maxStrLength = 500;
        if (in_array($levelName, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']) === true) {
            $maxStrLength = 2000;
        }

        $context['callMethod'] = isset($callData['callMethod']) === true ? $callData['callMethod'] : '';
        $context['callUrl'] = isset($callData['callUrl']) === true ? $callData['callUrl'] : '';
        $context['callQuery'] = isset($callData['callQuery']) === true ? json_encode($callData['callQuery']) : '';
        $context['callContentType'] = isset($callData['callContentType']) === true ? $callData['callContentType'] : '';
        $context['callBody'] = isset($callData['callBody']) === true ? $callData['callBody'] : '';
        if (strlen($context['callBody']) > $maxStrLength) {
            $context['callBody'] = substr($context['callBody'], 0, $maxStrLength) . '...';
        }

        $context['responseStatusCode'] = isset($callData['responseStatusCode']) === true ? $callData['responseStatusCode'] : '';
        $context['responseContentType'] = isset($callData['responseContentType']) === true ? $callData['responseContentType'] : '';
        $context['responseBody'] = isset($callData['responseBody']) === true ? $callData['responseBody'] : '';
        if (strlen($context['responseBody']) > $maxStrLength) {
            $context['responseBody'] = substr($context['responseBody'], 0, $maxStrLength) . '...';
        }

        return $context;
    }

    /**
     * Update the context with data from the session and the request stack. For log records with level ERROR or higher.
     * See: https://github.com/Seldaek/monolog/blob/main/doc/01-usage.md for all possible log levels.
     *
     * @param array $context The log context we are updating.
     * @param string $levelName The level name of the log record we are updating the context for.
     *
     * @return array The updated context.
     */
    private function addErrorContext(array $context, string $levelName): array
    {
        $context['pathRaw'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getPathInfo() : '';
        $context['querystring'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getQueryString() : '';
        $context['mongoDBFilter'] = $this->session->has('mongoDBFilter') === true ? json_encode($this->session->get('mongoDBFilter')) : '';
        $context['contentType'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getContentType() : '';

        // Do not log entire body for normal errors, only critical and higher
        if ($levelName !== 'ERROR') {
            try {
                $context['body'] = $this->requestStack->getMainRequest()->toArray();
            } catch (Exception $exception) {
                $context['body'] = '';
            }
            $context['crude_body'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getContent() : '';
        }

        return $context;
    }

    /**
     * Dispatches a log create action.
     *
     * @param array $record The log record that is created.
     *
     * @return array The resulting log record after the action.
     */
    public function dispatchLogCreateAction(array $record): array
    {
        if ($this->entityManager->getConnection()->isConnected() === true
            && in_array(
                $this->entityManager->getConnection()->getDatabase(),
                $this->entityManager->getConnection()->getSchemaManager()->listDatabases()
            ) === true
            && $this->entityManager->getConnection()->getSchemaManager()->tablesExist('action') === true
            && in_array($record['level_name'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']) === true
        ){
            $event = new ActionEvent('commongateway.action.event', $record, 'commongateway.log.create');

            $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

            $record = $event->getData();
        }

        return $record;
    }

    /**
     * Updates the log record with data from the session, request and from actions.
     *
     * @param array $record The log record.
     *
     * @return array The updated log record.
     */
    public function __invoke(array $record): array
    {
        $record['context'] = $this->updateContext($record);

        return $this->dispatchLogCreateAction($record);
    }
}
