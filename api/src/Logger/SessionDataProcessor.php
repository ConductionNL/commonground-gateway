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

    public function __invoke(array $record): array
    {

        $record['context']['session'] = $this->session->getId();
        $record['context']['process'] = $this->session->has('process') ? $this->session->get('process') : '';
        $record['context']['endpoint'] = $this->session->has('endpoint') ? $this->session->get('endpoint') : '';
        $record['context']['schema'] = $this->session->has('schema') ? $this->session->get('schema') : '';
        $record['context']['object'] = $this->session->has('object') === true ? $this->session->get('object') : '';
        $record['context']['cronjob'] = $this->session->has('cronjob') ? $this->session->get('cronjob') : '';
        $record['context']['action'] = $this->session->has('cronjob') ? $this->session->get('action') : '';
        $record['context']['mapping'] = $this->session->has('mapping') ? $this->session->get('mapping') : '';
        $record['context']['source'] = $this->session->has('source') ? $this->session->get('source') : '';
        $record['context']['plugin'] = isset($record['data']['plugin']) === true ? $record['data']['plugin'] : '';
        $record['context']['user'] = $this->session->has('user') ? $this->session->get('user') : '';
        $record['context']['organization'] = $this->session->has('organization') ? $this->session->get('organization') : '';
        $record['context']['application'] = $this->session->has('application') ? $this->session->get('application') : '';
        $record['context']['host'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getHost() : '';
        $record['context']['ip'] = $this->requestStack->getMainRequest() ? $this->requestStack->getMainRequest()->getClientIp() : '';


        if ($this->entityManager->getConnection()->isConnected() === true
            && in_array(
                $this->entityManager->getConnection()->getDatabase(),
                $this->entityManager->getConnection()->getSchemaManager()->listDatabases()
            ) === true
        ){
            $event = new ActionEvent('commongateway.action.event', $record, 'core.log.create');

            $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

            $record = $event->getData();
        }

        return $record;
    }
}
