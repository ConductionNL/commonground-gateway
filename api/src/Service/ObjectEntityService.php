<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\File;
use App\Entity\Handler;
use App\Entity\ObjectEntity;
use App\Entity\Unread;
use App\Entity\Value;
use App\Event\ActionEvent;
use App\Exception\GatewayException;
use App\Security\User\AuthenticationUser;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Twig\Environment;

/**
 * @Author Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 * @deprecated TODO: This service still contains some logic used by CoreBundle->ObjectEntitySubscriber (old CoreBundle->ObjectSyncSubscriber)
 * todo: this service is also used by the UserService for showing data when calling the /me endpoint.
 * todo: and this service still contains some old logic for Files and Promises we might still need at some point?
 */
class ObjectEntityService
{
    private Security $security;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    public array $notifications;
    private SymfonyStyle $io;

    public function __construct(
        Security $security,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->security = $security;
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->notifications = [];
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Dispatches an event for CRUD actions.
     *
     * @param string $type The type of event to dispatch
     * @param array  $data The data that should in the event
     */
    public function dispatchEvent(string $type, array $data, $subType = null, array $triggeredParentEvents = []): void
    {
        if ($this->session->get('io')) {
            $this->io = $this->session->get('io');
            $this->io->text("Dispatch ActionEvent for Throw: \"$type\"".($subType ? " and SubType: \"$subType\"" : ''));
            $this->io->newLine();
        }
        $event = new ActionEvent($type, $data, null);
        if ($subType) {
            $event->setSubType($subType);
        }
        $this->eventDispatcher->dispatch($event, $type);

        if (array_key_exists('entity', $data) &&
            ($type === 'commongateway.object.update' || $subType === 'commongateway.object.update')
        ) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $data['entity']]);
            if ($entity instanceof Entity) {
                $this->checkTriggerParentEvents($entity, $data, $triggeredParentEvents);

                return;
            }
            if (isset($this->io)) {
                $this->io->warning("Trying to look if we need to trigger parent events for Throw: \"$type\""
                    .($subType ? " and SubType: \"$subType\"" : '')
                    ." But couldn't find an Entity with id: \"{$data['entity']}\"");
            }
        }
    }

    /**
     * Checks if the given Entity has parent attributes with TriggerParentEvents = true.
     * And will dispatch put events for each parent object found for these parent attributes.
     *
     * @param Entity $entity
     * @param array  $data
     * @param array  $triggeredParentEvents An array used to keep track of objects we already triggered parent events for. To prevent endless loops.
     *
     * @return void
     */
    private function checkTriggerParentEvents(Entity $entity, array $data, array $triggeredParentEvents): void
    {
        $parentAttributes = $entity->getUsedIn();
        $triggerParentAttributes = $parentAttributes->filter(function ($parentAttribute) {
            return $parentAttribute->getTriggerParentEvents();
        });
        if (isset($this->io) && count($triggerParentAttributes) > 0) {
            $count = count($triggerParentAttributes);
            $this->io->text("Found $count attributes with triggerParentEvents = true for this entity: {$entity->getName()} ({$entity->getId()->toString()})");
            $this->io->newLine();
        }

        if (isset($data['response']['id'])) {
            // Get the object that triggered the initial PUT dispatchEvent.
            $object = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
            if ($object instanceof ObjectEntity and !in_array($data['response']['id'], $triggeredParentEvents)) {
                // Prevent endless loop of dispatching events.
                $triggeredParentEvents[] = $data['response']['id'];
                $this->dispatchTriggerParentEvents($object, $triggerParentAttributes, $data, $triggeredParentEvents);
            }
        }
    }

    /**
     * Follow-up function of checkTriggerParentEvents() function, that actually dispatches the put events for parent objects.
     *
     * @param ObjectEntity    $object
     * @param ArrayCollection $triggerParentAttributes
     * @param array           $data
     * @param array           $triggeredParentEvents   An array used to keep track of objects we already triggered parent events for. To prevent endless loops.
     *
     * @return void
     */
    private function dispatchTriggerParentEvents(ObjectEntity $object, ArrayCollection $triggerParentAttributes, array $data, array $triggeredParentEvents): void
    {
        foreach ($triggerParentAttributes as $triggerParentAttribute) {
            // Get the parent value & parent object using the attribute with triggerParentEvents = true.
            $parentValues = $object->findSubresourceOf($triggerParentAttribute);
            foreach ($parentValues as $parentValue) {
                $parentObject = $parentValue->getObjectEntity();
                // Create a data array for the parent Object data. (Also add entity) & dispatch event.
                if (isset($this->io)) {
                    $this->io->text("Trigger event for parent object ({$parentObject->getId()->toString()}) of object with id = {$data['response']['id']}");
                    $this->io->text('Dispatch ActionEvent for Throw: commongateway.object.update');
                    $this->io->newLine();
                }
                // Make sure we set dateModified of the parent object before dispatching an event so the synchronization actually happens.
                $now = new DateTime();
                $parentObject->setDateModified($now);
                $this->dispatchEvent(
                    'commongateway.object.update',
                    [
                        'response' => $parentObject->toArray(),
                        'entity'   => $parentObject->getEntity()->getId()->toString(),
                    ],
                    null,
                    $triggeredParentEvents
                );
            }
        }
    }

    /**
     * This function checks the owner of the object.
     *
     * @param ObjectEntity $result The object entity
     *
     * @return bool
     * @deprecated
     */
    public function checkOwner(ObjectEntity $result): bool
    {
        // TODO: what if somehow the owner of this ObjectEntity is null? because of ConvertToGateway ObjectEntities for example?
        $user = $this->security->getUser();

        if ($user && $result->getOwner() === $user->getUserIdentifier()) {
            return true;
        }

        return false;
    }
}
