<?php

namespace App\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\CollectionEntity;

class CollectionEntitySubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['request', EventPriorities::PRE_WRITE],
        ];
    }

    /**
     * @param ViewEvent $event
     */
    public function request(ViewEvent $event)
    {
        // $responseContent = json_decode($event->getResponse()->getContent());
        $requestContent = json_decode($event->getRequest()->getContent());

        // var_dump($responseContent->id);

        if (
            $event->getRequest()->attributes->get('_route') !== 'api_collection_entities_put_item' || !isset($requestContent->prefix)
        ) {
            return;
        }

        // var_dump($);

        $object = $event->getControllerResult();
        // var_dump($object);
        var_dump($object->getPrefix());
        $collectionEntity = $this->entityManager->getRepository(CollectionEntity::class)->find($object->getId()->toString());

        $oldPrefix = $collectionEntity->getPrefix();
        $newPrefix = $requestContent->prefix;
        var_dump('oldPrefix: ' . $oldPrefix);
        var_dump('newPrefix: ' . $newPrefix);
        var_dump('dateModified: ' . $collectionEntity->getDateModified()->format('d M Y H:i:s'));

        foreach ($object->getEndpoints() as $endpoint) {
        }

        var_dump('success');
    }
}
