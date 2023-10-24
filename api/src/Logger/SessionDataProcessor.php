<?php

namespace App\Logger;

use App\Event\ActionEvent;
use Doctrine\ORM\EntityManagerInterface;
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
     * @param array $context The context to update.
     *
     * @return array The updated context.
     */
    public function updateContext($context): array
    {
        $context['session'] = $this->session->getId();
        $context['process'] = $this->session->has('process') ? $this->session->get('process') : '';
        $context['endpoint'] = $this->session->has('endpoint') ? $this->session->get('endpoint') : '';
        $context['schema'] = $this->session->has('schema') ? $this->session->get('schema') : '';
        $context['object'] = $this->session->has('object') === true ? $this->session->get('object') : '';
        $context['cronjob'] = $this->session->has('cronjob') ? $this->session->get('cronjob') : '';
        $context['action'] = $this->session->has('cronjob') ? $this->session->get('action') : '';
        $context['mapping'] = $this->session->has('mapping') ? $this->session->get('mapping') : '';
        $context['source'] = $this->session->has('source') ? $this->session->get('source') : '';
        $context['plugin'] = isset($record['data']['plugin']) === true ? $record['data']['plugin'] : '';
        $context['user'] = $this->session->has('user') ? $this->session->get('user') : '';
        $context['organization'] = $this->session->has('organization') ? $this->session->get('organization') : '';
        $context['application'] = $this->session->has('application') ? $this->session->get('application') : '';
        $context['host'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getHost() : '';
        $context['ip'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getClientIp() : '';

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
            && in_array($record['level_name'], ['DEBUG', 'INFO', 'NOTICE', 'WARNING']) === false
        ){
            $event = new ActionEvent('commongateway.action.event', $record, 'core.log.create');

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
        $record['context'] = $this->updateContext($record['context']);

        return $this->dispatchLogCreateAction($record);
    }
}
