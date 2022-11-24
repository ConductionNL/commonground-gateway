<?php

// src/Subscriber/DatabaseActivitySubscriber.php

namespace App\Subscriber;

use App\Entity\ObjectEntity;
use App\Service\EavService;
use App\Service\GatewayService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;

class DatabaseActivitySubscriber implements EventSubscriberInterface
{
    private EavService $eavService;
    private GatewayService $gatewayService;
    private CommonGroundService $commonGroundService;
    private CacheInterface $cache;

    public function __construct(
        EavService $eavService,
        GatewayService $gatewayService,
        CommonGroundService $commonGroundService,
        CacheInterface $cache
    ) {
        $this->eavService = $eavService;
        $this->gatewayService = $gatewayService;
        $this->commonGroundService = $commonGroundService;
        $this->cache = $cache;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            Events::postLoad,
            Events::postRemove,
        ];
    }

    // callback methods must be called exactly like the events they listen to;
    // they receive an argument of type LifecycleEventArgs, which gives you access
    // to both the entity object of the event and the entity manager itself
    public function postLoad(LifecycleEventArgs $args): void
    {
        /* @todo hotfix */
        // $this->logActivity('load', $args);
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        /* @todo hotfix */
        // $this->logActivity('remove', $args);
    }

    private function logActivity(string $action, LifecycleEventArgs $args): void
    {
        $objectEntity = $args->getObject();

        // if this subscriber only applies to certain entity types,
        // add some code to check the entity type as early as possible
        if (!$objectEntity instanceof ObjectEntity || !$objectEntity->getEntity() || !$objectEntity->getEntity()->getSource() || !$objectEntity->getUri()) {
            return;
        }

        if ($action == 'load') {
            $item = $this->cache->getItem('commonground_'.base64_encode($objectEntity->getUri()));
            // lets try to hit the cach
            if ($item->isHit()) {
                $objectEntity->setExternalResult($item->get());
            } else {
                /* @todo figure out how to this promise style */
                $component = $this->gatewayService->sourceToArray($objectEntity->getEntity()->getSource());
                $result = $this->commonGroundService->callService($component, $objectEntity->getUri(), '');
                $result = json_decode($result->getBody()->getContents(), true);
                $objectEntity->setExternalResult($result);
                $item->set($result);
                //$item->expiresAt(new \DateTime('tomorrow'));
                $this->cache->save($item);
            }
        } elseif ($action == 'remove') {
            /* @todo we should check is an entity is not ussed elswhere before removing it */
            $component = $this->gatewayService->sourceToArray($objectEntity->getEntity()->getSource());
            /* @todo we need to do some abstraction to log these calls, something like an callWrapper at the eav service  */
            $result = $this->commonGroundService->callService($component, $objectEntity->getUri(), '', [], [], false, 'DELETE');
            // Lets see if we need to clear the cache
            $item = $this->cache->getItem('commonground_'.base64_encode($objectEntity->getUri()));
            if ($item->isHit()) {
                $this->cache->delete('commonground_'.base64_encode($objectEntity->getUri()));
            }
        } else {
            /* @todo throe execption */
        }
    }
}
