<?php

namespace App\Logger;

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionDataProcessor
{
    private SessionInterface $session;

    public function __construct(
        private readonly RequestStack $requestStack
    )
    {
        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }
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

        return $record;
    }
}
