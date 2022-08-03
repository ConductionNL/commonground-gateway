<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\CollectionEntity;
use App\Entity\Endpoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

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
        $requestContent = json_decode($event->getRequest()->getContent());

        if (
            $event->getRequest()->attributes->get('_route') !== 'api_collection_entities_put_item' || !isset($requestContent->prefix)
        ) {
            return;
        }

        $oldObject = $event->getRequest()->get('previous_data');
        $collectionEntity = $this->entityManager->getRepository(CollectionEntity::class)->find($oldObject->getId()->toString());

        // Replace endpoints paths
        if (isset($collectionEntity)) {
            foreach ($collectionEntity->getEndpoints() as $endpoint) {
                $this->setNewPath($endpoint, $requestContent->prefix, $oldObject->getPrefix());
            }
            $this->entityManager->flush();
        }
    }

    /**
     * Sets a new path and pathRegex for given Endpoint.
     *
     * @param Endpoint $endpoint  This is the endpoint which path we update.
     * @param string   $newPrefix This is the new prefix that will be set.
     * @param ?string  $oldPrefix This is the old prefix which will be replaced by the new prefix.
     */
    private function setNewPath(Endpoint $endpoint, string $newPrefix, ?string $oldPrefix): void
    {
        if ($oldPrefix && str_contains($endpoint->getPathRegex(), $oldPrefix)) {
            // Remove old prefix with new
            $endpoint->setPathRegex(str_replace($oldPrefix, $newPrefix, $endpoint->getPathRegex()));
            $endpoint->setPath(str_replace($oldPrefix, $newPrefix, $endpoint->getPath()));
        } else {
            // Set prefix for first time
            $newPath = $endpoint->getPath();
            $endpointPathRegex = $endpoint->getPathRegex();
            array_unshift($newPath, $newPrefix);
            $endpoint->setPath($newPath);

            str_contains($endpointPathRegex, '^(') ?
                $strToReplace = '^(' :
                $strToReplace = '^';
            $newPrefix = $strToReplace.$newPrefix.'/';
            $endpoint->setPathRegex(str_replace($strToReplace, $newPrefix, $endpointPathRegex));
        }
        $this->entityManager->persist($endpoint);
    }
}
