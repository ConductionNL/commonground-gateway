<?php

namespace App\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SessionInterface       $session       The sesion interface
     */
    public function __construct(EntityManagerInterface $entityManager, SessionInterface $session)
    {
        $this->entityManager = $entityManager;
        $this->session = $session;
    }//end __construct()

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['request'],
        ];
    }//end getSubscribedEvents()

    /**
     * @param ResponseEvent $event The Responce Event
     */
    public function request(ResponseEvent $event)
    {
        $response = $event->getResponse();

        // Set multiple headers simultaneously
        $response->headers->add([
            'Access-Control-Allow-Credentials' => 'true',
            'Process-ID'                       => $this->session->get('process'),
        ]);

        $response->headers->remove('Access-Control-Allow-Origin');
    }//end request()
}//end class
