<?php

namespace App\Subscriber;

use App\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['request'],
        ];
    }

    /**
     * @param ResponseEvent $event
     */
    public function request(ResponseEvent $event)
    {
        $response = $event->getResponse();
        $response = $this->handleCorsHeader($response, $event);

        // Set multiple headers simultaneously
        $response->headers->add([
            'Access-Control-Allow-Credentials' => 'true',
        ]);
    }

    public function handleCorsHeader($response, ResponseEvent $event)
    {
        $request = $event->getRequest();

        if ($request->headers->has('host')) {
            $host = $request->headers->get('host');
        } elseif ($request->headers->has('Origin')) {
            $host = $request->headers->get('Origin');
        } else {
            return $response;
        }

        $applications = $this->entityManager->getRepository('App:Application')->findAll();
        $applications = array_values(array_filter($applications, function (Application $application) use ($host) {
            return in_array($host, $application->getDomains());
        }));

        if (count($applications) > 0) {
            $response->headers->add(['access-control-allow-origin' => $host]);
        } else {
            $response->headers->remove('access-control-allow-origin');
        }

        return $response;
    }
}
