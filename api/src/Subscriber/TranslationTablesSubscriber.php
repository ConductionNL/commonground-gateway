<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TranslationTablesSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['translationTablesNames', EventPriorities::PRE_SERIALIZE],
        ];
    }

    /**
     * @param ViewEvent $event
     */
    public function translationTablesNames(ViewEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');
        $path = $event->getRequest()->getPathInfo();

        if ($route === 'api_translations_get_table_names_collection' && $path === '/admin/table_names') {
            $translationsRepo = $this->entityManager->getRepository('App:Translation');

            $event->setResponse(new Response(json_encode([
                'results' => $translationsRepo->getTables(),
            ]), 200, ['Content-type' => 'application/json']));
        }

        if ($route !== 'api_translations_get_collection') {
            return false;
        }
    }
}
