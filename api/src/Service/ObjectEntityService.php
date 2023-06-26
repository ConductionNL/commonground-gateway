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
use App\Message\PromiseMessage;
use App\Security\User\AuthenticationUser;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use phpDocumentor\Reflection\Types\This;
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
 * @deprecated TODO: This service still contains some logic used by the CoreBundle->RequestService for DateRead! (& CoreBundle->ObjectSyncSubscriber)
 * todo: this service is also used by the UserService for showing data when calling the /me endpoint.
 */
class ObjectEntityService
{
    private Security $security;
    private Request $request;
    private AuthorizationService $authorizationService;
    private ApplicationService $applicationService;
    private ValidatorService $validatorService;
    private SessionInterface $session;
    private ?EavService $eavService;
    private EntityManagerInterface $entityManager;
    private CommonGroundService $commonGroundService;
    private ResponseService $responseService;
    public FunctionService $functionService;
    private MessageBusInterface $messageBus;
    private GatewayService $gatewayService;
    private LogService $logService;
    private EventDispatcherInterface $eventDispatcher;
    public array $notifications;
    private Environment $twig;
    private SymfonyStyle $io;
    private TranslationService $translationService;

    public function __construct(
        Security $security,
        RequestStack $requestStack,
        AuthorizationService $authorizationService,
        ApplicationService $applicationService,
        ValidatorService $validatorService,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        CommonGroundService $commonGroundService,
        ResponseService $responseService,
        CacheInterface $cache,
        MessageBusInterface $messageBus,
        GatewayService $gatewayService,
        TranslationService $translationService,
        LogService $logService,
        EventDispatcherInterface $eventDispatcher,
        Environment $twig
    ) {
        $this->security = $security;
        $this->request = $requestStack->getCurrentRequest() ?: new Request();
        $this->authorizationService = $authorizationService;
        $this->applicationService = $applicationService;
        $this->validatorService = $validatorService;
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->commonGroundService = $commonGroundService;
        $this->responseService = $responseService;
        $this->functionService = new FunctionService($cache, $commonGroundService, $this);
        $this->messageBus = $messageBus;
        $this->gatewayService = $gatewayService;
        $this->translationService = $translationService;
        $this->logService = $logService;
        $this->notifications = [];
        $this->eventDispatcher = $eventDispatcher;
        $this->twig = $twig;
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
     * Add services for using the handleObject function todo: temp fix untill we no longer use these services here.
     *
     * @param EavService $eavService
     *
     * @return $this
     * @deprecated
     */
    public function addServices(EavService $eavService): ObjectEntityService
    {
        // EavService uses the ObjectEntityService for the handleOwner and checkOwner function.
        // The only reason we need this service in this ObjectEntityService is for the handleObject function,
        // because we use an 'old' way to create, update and get ObjectEntities there.
        $this->eavService = $eavService;

        return $this;
    }

    /**
     * A function we want to call when doing a post or put, to set the owner of an ObjectEntity, if it hasn't one already.
     *
     * @param ObjectEntity $result The object entity
     * @param string|null  $owner  The owner of the object - defaulted to owner
     *
     * @return ObjectEntity|array
     * @deprecated
     */
    public function handleOwner(ObjectEntity $result, ?string $owner = 'owner')
    {
        $user = $this->security->getUser();

        if ($user && !$result->getOwner()) {
            if ($owner == 'owner') {
                $result->setOwner($user->getUserIdentifier());
            } else {
                // $owner is allowed to be null or a valid uuid of a UC user
                if ($owner !== null) {
                    if (!Uuid::isValid($owner)) {
                        $errorMessage = '@owner ('.$owner.') is not a valid uuid.';
                    } elseif (!$this->commonGroundService->isResource($this->commonGroundService->cleanUrl(['component' => 'uc', 'type' => 'users', 'id' => $owner]))) {
                        $errorMessage = '@owner ('.$owner.') is not an existing user uuid.';
                    }
                    if (isset($errorMessage)) {
                        return [
                            'message' => $errorMessage,
                            'type'    => 'Bad Request',
                            'path'    => $result->getEntity()->getName(),
                            'data'    => ['@owner' => $owner],
                        ];
                    }
                }
                $result->setOwner($owner);
            }
        }

        return $result;
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

    /**
     * This function gets the object by its uri.
     *
     * @param string     $uri    The uri of the object
     * @param array|null $fields The fields array that can be filtered on
     * @param array|null $extend The extend array that can be extended
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     * @deprecated
     */
    public function getObjectByUri(string $uri, ?array $fields = null, ?array $extend = null): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['uri' => $uri]);
        if ($object instanceof ObjectEntity) {
            return $this->responseService->renderResult($object, $fields, $extend, 'jsonld', true);
        }

        return [];
    }

    /**
     * This function gets the object with its id and the related entity.
     *
     * @param Entity     $entity The entity the object relates to
     * @param string     $id     The id of the object entity
     * @param array|null $fields The fields array that can be filtered on
     * @param array|null $extend The extend array that can be extended
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     * @deprecated
     */
    public function getObject(Entity $entity, string $id, ?array $fields = null, ?array $extend = null): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'id' => $id]);
        if ($object instanceof ObjectEntity) {
            return $this->responseService->renderResult($object, $fields, $extend, 'jsonld', true);
        }

        return [];
    }

    /**
     * This function gets an object with the function set to person.
     *
     * @param string     $id     The id of the object entity
     * @param array|null $fields The fields array that can be filtered on
     * @param array|null $extend The extend array that can be extended
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     */
    public function getPersonObject(string $id, ?array $fields = null, ?array $extend = null): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['function' => 'person']);
        if ($entity instanceof Entity) {
            return $this->getObject($entity, $id, $fields, $extend);
        }

        return [];
    }

    /**
     * This function gets an object with the function set to organization.
     *
     * @param string     $id     The id of the object entity
     * @param array|null $fields The fields array that can be filtered on
     * @param array|null $extend The extend array that can be extended
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     */
    public function getOrganizationObject(string $id, ?array $fields = null, ?array $extend = null): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['function' => 'organization']); //todo cache this!?
        if ($entity instanceof Entity) {
            return $this->getObject($entity, $id, $fields, $extend);
        }

        return [];
    }

    /**
     * @TODO
     *
     * @param string     $username The username of the person
     * @param array|null $fields   The fields array that can be filtered on
     * @param array|null $extend   The extend array that can be extended
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     */
    public function getUserObjectEntity(string $username, ?array $fields = null, ?array $extend = null): array
    {
        // Because inversedBy wil not set the UC->user->person when creating a person with a user in the gateway.
        // We need to do this in order to find the person of this user:
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'users']);

        if ($entity == null) {
            return [];
        }

        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['username' => $username]);
        if (count($objects) == 1) {
            $user = $this->responseService->renderResult($objects[0], $fields, $extend, 'jsonld', true);
            // This: will be false if a user has no rights to do get on a person object
            if (isset($user['person'])) {
                return $user['person'];
            }
        }

        return [];
    }

    /**
     * This function get the filters array from the parameters.
     *
     * @return array
     * @deprecated
     */
    private function getFilterFromParameters(): array
    {
        if ($parameters = $this->session->get('parameters')) {
            if (array_key_exists('path', $parameters)) {
                foreach ($parameters['path'] as $key => $part) {
                    if ($key[0] === '{' && $key[strlen($key) - 1] === '}' && $part !== null) {
                        $key = substr($key, 1, -1);
                        $filters[$key] = $part;

                        return $filters;
                    } else {
                        // @todo
                    }
                }
            }
        }

        return [];
    }

    /**
     * This function handles the check for an object.
     *
     * @param string|null $id     The id of the object
     * @param string|null $method Method from request if there is a request
     * @param Entity      $entity The entity of the object
     *
     * @throws GatewayException
     *
     * @return ObjectEntity|array|mixed|null
     * @deprecated
     */
    public function checkGetObject(?string $id, string $method, Entity $entity)
    {
        // todo: re-used old code for getting an objectEntity
        $object = $this->eavService->getObject($method === 'POST' ? null : $id, $method, $entity);

        if (is_array($object) && array_key_exists('type', $object) && $object['type'] == 'Bad Request') {
            throw new GatewayException($object['message'], null, null, ['data' => $object['data'], 'path' => $object['path'], 'responseType' => Response::HTTP_BAD_REQUEST]);
        } // Let's check if the user is allowed to view/edit this resource.

        if (!$method == 'POST' && !$this->checkOwner($object)) {
            // TODO: do we want to throw a different error if there are no organizations in the session? (because of logging out for example)
            if ($object->getOrganization() && !in_array($object->getOrganization(), $this->session->get('organizations') ?? [])) {
                throw new GatewayException('You are forbidden to view or edit this resource.', null, null, ['data' => ['id' => $id ?? null], 'path' => $entity->getName(), 'responseType' => Response::HTTP_FORBIDDEN]);
            }
        }

        if ($object instanceof ObjectEntity && $object->getId() !== null) {
            $this->session->set('object', $object->getId()->toString());
        }

        // Check for scopes, if forbidden to view/edit this, throw forbidden error
        if (!isset($object) || is_array($object) || !$object->getUri() || !$this->checkOwner($object)) {
            try {
                //TODO what to do if we do a get collection and want to show objects this user is the owner of, but not any other objects?
                $this->authorizationService->checkAuthorization([
                    'method' => $method,
                    'entity' => $entity,
                    'object' => $object ?? null,
                ]);
            } catch (AccessDeniedException $e) {
                throw new GatewayException($e->getMessage(), null, null, ['data' => null, 'path' => $entity->getName(), 'responseType' => Response::HTTP_FORBIDDEN]);
            }
        }

        return $object;
    }

    /**
     * This function handles the check on operation types exceptions.
     *
     * @param Endpoint $endpoint The endpoint of the object
     * @param Entity   $entity   The entity of the object
     * @param array    $data     Data to be set into the eav
     *
     * @throws GatewayException
     *
     * @return ObjectEntity|string[]|void
     * @deprecated
     */
    public function checkGetOperationTypeExceptions(Endpoint $endpoint, Entity $entity, array &$data)
    {
        $operationType = $endpoint->getOperationType();
        if (((isset($operationType) && $operationType === 'item') || $endpoint->getOperationType() === 'item') && array_key_exists('results', $data) && count($data['results']) == 1) { // todo: $data['total'] == 1
            $data = $data['results'][0];
            isset($data['id']) && Uuid::isValid($data['id']) ?? $this->session->set('object', $data['id']);
        } elseif ((isset($operationType) && $operationType === 'item') || $endpoint->getOperationType() === 'item') {
            throw new GatewayException('No object found with these filters', null, null, ['data' => $filters ?? null, 'path' => $entity->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        return $data;
    }

    /**
     * This function handles the object entity exceptions.
     *
     *
     * @param array|null        $data       Data to be set into the eav
     * @param ObjectEntity|null $object     The objects that is being checked on exceptions
     * @param array|null        $fields     The fields array that can be filtered on
     * @param array|null        $extend     The extend array that can be extended
     * @param string            $acceptType The acceptType of the call - defaulted to jsonld
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     *
     * @return string[]
     * @deprecated
     */
    public function checkGetObjectExceptions(?array &$data, ?ObjectEntity $object, ?array $fields, ?array $extend, string $acceptType): array
    {
        if ($object instanceof ObjectEntity) {
            !$object->getSelf() ?? $object->setSelf($this->createSelf($object));
            if (isset($extend['x-commongateway-metadata']['dateRead'])
                || isset($extend['x-commongateway-metadata']['all'])) {
                $extend['x-commongateway-metadata']['dateRead'] = 'getItem';
            }
            $data = $this->eavService->handleGet($object, $fields, $extend, $acceptType);
        } else {
            $data['error'] = $object;
        }

        return $data;
    }

    /**
     * Gets fields and extend from the query params used in the request.
     *
     * @return array An array containing 2 keys: 'fields' & 'extend'.
     * @deprecated
     */
    private function getRequestQueryParams(): array
    {
        $fields = $this->eavService->getRequestFields($this->request);

        // Let's allow for extending
        $extend = $this->eavService->getRequestExtend($this->request);
        if (isset($extend['x-commongateway-metadata']) && $extend['x-commongateway-metadata'] === true) {
            $extend['x-commongateway-metadata'] = [];
            $extend['x-commongateway-metadata']['all'] = true;
        }

        return [
            'fields' => $fields,
            'extend' => $extend,
        ];
    }

    /**
     * This function handles the get case of an object entity.
     *
     * @param string|null   $id         The id of the object
     * @param array|null    $data       Data to be set into the eav
     * @param string        $method     The method of the call
     * @param Entity        $entity     The entity of the object
     * @param Endpoint|null $endpoint   The endpoint of the object
     * @param string        $acceptType The acceptType of the call - defaulted to jsonld
     *
     * @throws CacheException
     * @throws GatewayException
     * @throws InvalidArgumentException
     *
     * @return array
     * @deprecated
     */
    public function getCase(?string $id, ?array &$data, string $method, Entity $entity, ?Endpoint $endpoint, string $acceptType): array
    {
        $queryParamData = $this->getRequestQueryParams();

        if (isset($id)) {
            $object = $this->checkGetObject($id, $method, $entity);
            $data = $this->checkGetObjectExceptions($data, $object, $queryParamData['fields'], $queryParamData['extend'], $acceptType);
        } else {
            $data = $this->eavService->handleSearch(
                $entity,
                $this->request,
                $queryParamData['fields'],
                $queryParamData['extend'],
                false,
                $filters ?? [],
                $acceptType
            );

            if (isset($endpoint)) {
                $this->session->get('endpoint') ?? $data = $this->checkGetOperationTypeExceptions($endpoint, $entity, $data);
            }
        }

        return $data;
    }

    /**
     * This function checks and unsets the owner of the body of the call.
     *
     * @param array $data Data to be set into the eav
     *
     * @return string|null
     * @deprecated
     */
    public function checkAndUnsetOwner(array &$data): ?string
    {
        // todo: what about @organization? (See saveObject function, test it first, look at and compare with old code!)
        // Check if @owner is present in the body and if so unset it.
        // note: $owner is allowed to be null!
        $owner = 'owner';
        if (array_key_exists('@owner', $data)) {
            $owner = $data['@owner'];
            unset($data['@owner']);
        }

        return $owner;
    }

    /**
     * This function handles creating, updating and patching the object.
     *
     * @param array        $data       Data to be set into the eav
     * @param ObjectEntity $object     The objects that needs to be created/updated
     * @param string       $owner      The owner of the object
     * @param string       $method     The method of the call
     * @param string       $acceptType The acceptType of the call - defaulted to jsonld
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     *
     * @return string[]
     * @deprecated
     */
    public function createOrUpdateCase(array &$data, ObjectEntity $object, string $owner, string $method, string $acceptType): array
    {
        $queryParamData = $this->getRequestQueryParams();

        // Save the object (this will remove this object result from the cache)
        $this->functionService->removeResultFromCache = [];
        $object = $this->saveObject($object, $data);

        // Handle Entity Function (note that this might be overwritten when handling the promise later!)
        $object = $this->functionService->handleFunction($object, $object->getEntity()->getFunction(), [
            'method'           => $method,
            'uri'              => $object->getUri(),
            'organizationType' => array_key_exists('type', $data) ? $data['type'] : null,
            'userGroupName'    => array_key_exists('name', $data) ? $data['name'] : null,
        ]);

        $this->handleOwner($object, $owner); // note: $owner is allowed to be null!
        $object->setDateModified(new DateTime());

        $this->entityManager->persist($object);
        $this->entityManager->flush();

        $data = $this->responseService->renderResult($object, $queryParamData['fields'], $queryParamData['extend'], $acceptType);

        return $data;
    }

    /**
     * This function handles deleting the object.
     *
     * @param string     $id     the id of the object
     * @param array|null $data   Data to be set into the eav
     * @param string     $method The method of the call
     * @param Entity     $entity The entity of the object
     *
     * @throws GatewayException
     * @throws InvalidArgumentException
     *
     * @return string[]
     * @deprecated
     */
    public function deleteCase(string $id, ?array &$data, string $method, Entity $entity): array
    {
        $object = $this->checkGetObject($id, $method, $entity);
        //todo: use PromiseMessage for delete promise and notification (re-use / replace code from eavService->handleDelete

        //todo: -start- old code...
        //TODO: old code for deleting an ObjectEntity

        // delete object (this will remove this object result from the cache)
        $this->functionService->removeResultFromCache = [];
        $data = $this->eavService->handleDelete($object);
        if (array_key_exists('type', $data) && $data['type'] == 'Forbidden') {
            throw new GatewayException($data['message'], null, null, ['data' => $data['data'], 'path' => $data['path'], 'responseType' => Response::HTTP_FORBIDDEN]);
        }
        //todo: -end- old code...

        return $data;
    }

    /**
     * Saves an ObjectEntity in the DB using the $post array. NOTE: validation is and should only be done by the validatorService->validateData() function this saveObject() function only saves the object in the DB.
     *
     * @param array|null    $data       Data to be set into the eav
     * @param Endpoint|null $endpoint   The endpoint of the object
     * @param Entity        $entity     The entity of the object
     * @param string|null   $id         The id of the object
     * @param string        $method     The method of the call
     * @param string        $acceptType The acceptType of the call - defaulted to jsonld
     *
     * @throws CacheException
     * @throws ComponentException
     * @throws GatewayException
     * @throws InvalidArgumentException
     *
     * @return string[]|void
     * @deprecated
     */
    public function switchMethod(?array &$data, ?Endpoint $endpoint, Entity $entity, string $id = null, string $method = 'GET', string $acceptType = 'json')
    {
        // Get filters from query parameters
        $filters = $this->getFilterFromParameters();

        array_key_exists('id', $filters) && $id = $filters['id'];
        !isset($id) && array_key_exists('uuid', $filters) && $id = $filters['uuid'];

        $validationErrors = null;
        switch ($method) {
            case 'GET':
                $data = $this->getCase($id, $data, $method, $entity, $endpoint, $acceptType);
                // todo: this dispatch should probably be moved to the getCase function!?
                $this->dispatchEvent('commongateway.object.read', ['response' => $data, 'entity' => $entity->getId()->toString()]);
                break;
            case 'POST':
            case 'PUT':
            case 'PATCH':
                $object = $this->checkGetObject($id, $method, $entity);
                $owner = $this->checkAndUnsetOwner($data);

                // validate
//                if ($validationErrors = $this->validatorService->validateData($data, $entity, $method)) {
//                    return $validationErrors;
//                }

                $data = $this->createOrUpdateCase($data, $object, $owner, $method, $acceptType);
                // todo: this dispatch should probably be moved to the createOrUpdateCase function!?
                $this->dispatchEvent($method == 'POST' ? 'commongateway.object.create' : 'commongateway.object.update', ['response' => $data, 'entity' => $entity->getId()->toString()]);
                break;
            case 'DELETE':
                $data = $this->deleteCase($id, $data, $method, $entity);
                // todo: this dispatch should probably be moved to the deleteCase function!?
                $this->dispatchEvent('commongateway.object.delete', ['response' => $data, 'entity' => $entity->getId()->toString()]);
                break;
            default:
                throw new GatewayException('This method is not allowed', null, null, ['data' => ['method' => $method], 'path' => $entity->getName(), 'responseType' => Response::HTTP_FORBIDDEN]);
        }

        return $validationErrors;
    }

    /**
     * A function to handle calls to eav.
     *
     * @param Handler     $handler    The handler the object relates to
     * @param Endpoint    $endpoint   The endpoint of the object
     * @param array|null  $data       Data to be set into the eav
     * @param string|null $method     Method from request if there is a request
     * @param string      $acceptType The acceptType of the call - defaulted to jsonld
     *
     * @throws GatewayException|CacheException|InvalidArgumentException|ComponentException|Exception
     *
     * @return array $data
     * @deprecated
     */
    public function handleObject(Handler $handler, Endpoint $endpoint, ?array $data = null, string $method = null, string $acceptType = 'json'): array
    {
        // Set application in the session or create new application for localhost if we need it.
        $this->applicationService->getApplication();

        // set session with sessionInfo
        $sessionInfo = [
            'entity' => $handler->getEntity()->getId()->toString(),
            'source' => $handler->getEntity()->getSource() ? $handler->getEntity()->getSource()->getId()->toString() : null,
        ];
        $this->session->set('entitySource', $sessionInfo);

        $validationErrors = $this->switchMethod($data, $endpoint, $handler->getEntity(), null, $method, $acceptType);
        if (isset($validationErrors)) {
            throw new GatewayException('Validation errors', null, null, ['data' => $validationErrors, 'path' => $handler->getEntity()->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        // use events
        return $data;
    }

    /**
     * Saves an ObjectEntity in the DB using the $post array. NOTE: validation is and should only be done by the validatorService->validateData() function this saveObject() function only saves the object in the DB.
     *
     * @param ObjectEntity $objectEntity
     * @param array        $post
     *
     * @throws Exception|InvalidArgumentException
     *
     * @return ObjectEntity
     * @deprecated
     */
    public function saveObject(ObjectEntity $objectEntity, array $post): ObjectEntity
    {
        $entity = $objectEntity->getEntity();

        foreach ($entity->getAttributes() as $attribute) {
            // Check attribute function
            if ($attribute->getFunction() !== 'noFunction') {
                $objectEntity = $this->handleAttributeFunction($objectEntity, $attribute);
                continue; // Do not save this attribute(/value) in any other way!
            }

            // Check if we have a value ( a value is given in the post body for this attribute, can be null)
            // If no value is present in the post body for this attribute check for defaultValue and nullable.
            if (key_exists($attribute->getName(), $post)) {
                $objectEntity = $this->saveAttribute($objectEntity, $attribute, $post[$attribute->getName()]);
            } elseif ($this->request->getMethod() == 'POST') {
                if ($attribute->getDefaultValue()) {
                    // todo: defaultValue should maybe be a Value object, so that defaultValue can be something else than a string
                    // DefaultValue can be a uuid string to connect an object...
                    $objectEntity = $this->saveAttribute($objectEntity, $attribute, $this->twig->createTemplate($attribute->getDefaultValue())->render());
                } else {
                    // If no value is given when creating a new object, make sure we set a value to null for this attribute.
                    $objectEntity->setValue($attribute, null);
                }
            }
        }

        if (!$objectEntity->getUri()) {
            // Lets make sure we always set the uri
            $objectEntity->setUri($this->createUri($objectEntity));
        }
        if (!$objectEntity->getSelf()) {
            // Lets make sure we always set the self (@id)
            $objectEntity->setSelf($this->createSelf($objectEntity));
        }

        if (array_key_exists('@organization', $post) && $objectEntity->getOrganization() != $post['@organization']) {
            $objectEntity->setOrganization($post['@organization']);
        }

        // Only do this if we are changing an object, not when creating one.
        if ($this->request->getMethod() != 'POST') {
            // Handle setting an object as unread.
            if (array_key_exists('@dateRead', $post) && $post['@dateRead'] == false) {
                $this->setUnread($objectEntity);
            }

            // If we change an ObjectEntity we should remove it from the result cache
            $this->functionService->removeResultFromCache($objectEntity);
        }

        return $objectEntity;
    }

    /**
     * Checks if there exists an unread object for the given ObjectEntity + current UserId. If not, creation one.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return void
     */
    public function setUnread(ObjectEntity $objectEntity)
    {
        // First, check if there is an Unread object for this Object+User. If so, do nothing.
        $user = $this->security->getUser();
        if ($user !== null) {
            $unreads = $this->entityManager->getRepository('App:Unread')->findBy(['object' => $objectEntity, 'userId' => $user->getUserIdentifier()]);
            if (empty($unreads)) {
                $unread = new Unread();
                $unread->setObject($objectEntity);
                $unread->setUserId($user->getUserIdentifier());
                $this->entityManager->persist($unread);
                // Do not flush, will always be done after the api-call that triggers this function, if that api-call doesn't throw an exception.
            }
        }
    }

    /**
     * @return string
     * @deprecated
     */
    private function getUserName(): string
    {
        $user = $this->security->getUser();

        if ($user instanceof AuthenticationUser) {
            return $user->getName();
        }

        return '';
    }

    /**
     * Handles saving the value for an Attribute when the Attribute has a function set. A function makes it 'function' (/behave) differently.
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     *
     * @throws Exception
     *
     * @return ObjectEntity
     * @deprecated
     */
    private function handleAttributeFunction(ObjectEntity $objectEntity, Attribute $attribute): ObjectEntity
    {
        switch ($attribute->getFunction()) {
            case 'id':
                $objectEntity->setValue($attribute, $objectEntity->getId()->toString());
                // Note: attributes with function = id should also be readOnly and type=string
                break;
            case 'self':
                $self = $objectEntity->getSelf() ?? $objectEntity->setSelf($this->createSelf($objectEntity))->getSelf();
                $objectEntity->setValue($attribute, $self);
                // Note: attributes with function = self should also be readOnly and type=string
                break;
            case 'uri':
                $uri = $objectEntity->getUri() ?? $objectEntity->setUri($this->createUri($objectEntity))->getUri();
                $objectEntity->setValue($attribute, $uri);
                // Note: attributes with function = uri should also be readOnly and type=string
                break;
            case 'externalId':
                $objectEntity->setValue($attribute, $objectEntity->getExternalId());
                // Note: attributes with function = externalId should also be readOnly and type=string
                break;
            case 'dateCreated':
                $objectEntity->setValue($attribute, $objectEntity->getDateCreated()->format("Y-m-d\TH:i:sP"));
                // Note: attributes with function = dateCreated should also be readOnly and type=string||date||datetime
                break;
            case 'dateModified':
                $objectEntity->setValue($attribute, $objectEntity->getDateModified()->format("Y-m-d\TH:i:sP"));
                // Note: attributes with function = dateModified should also be readOnly and type=string||date||datetime
                break;
            case 'userName':
                $objectEntity->getValueObject($attribute)->getValue() ?? $objectEntity->setValue($attribute, $this->getUserName());
                break;
        }

        return $objectEntity;
    }

    /**
     * Saves a Value for an Attribute (of the Entity) of an ObjectEntity.
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param              $value
     *
     * @throws Exception|InvalidArgumentException
     *
     * @return ObjectEntity
     * @deprecated
     */
    private function saveAttribute(ObjectEntity $objectEntity, Attribute $attribute, $value): ObjectEntity
    {
        //todo: check owner?
//        try {
//            if (!$this->checkOwner($objectEntity)) {
//                $this->authorizationService->checkAuthorization([
//                    'method'    => $this->request->getMethod(),
//                    'attribute' => $attribute,
//                    'value'     => $value,
//                ]);
//            }
//        } catch (AccessDeniedException $e) {
//            throw new GatewayException('message', null, null, ['data' => ['info' => 'info'], 'path' => 'somePath', 'responseType' => Response::HTTP_FORBIDDEN]);
//        }

        $valueObject = $objectEntity->getValueObject($attribute);

        // If the value given by the user is empty...
        if (empty($value) && !(in_array($attribute->getType(), ['bool', 'boolean']) && $value === false)) {
            if ($attribute->getMultiple() && $value === []) {
                if ($attribute->getType() == 'object' && ($this->request->getMethod() == 'PUT' || $this->request->getMethod() == 'PATCH')) {
                    foreach ($valueObject->getObjects() as $object) {
                        // If we are not re-adding this object...
                        $object->removeSubresourceOf($valueObject);
                        if (count($object->getSubresourceOf()) == 0) {
                            $this->eavService->handleDelete($object);
                        }
                    }
                    $valueObject->getObjects()->clear();
                } else {
                    $valueObject->setValue([]);
                }
            } else {
                $valueObject->setValue(null);
            }

            return $objectEntity;
        }

        // Save the actual value, unless type is object or file, we save those differently.
        if (!in_array($attribute->getType(), ['object', 'file'])) {
            $valueObject->setValue($value);
        } elseif ($attribute->getMultiple()) {
            // If multiple, this is an array, loop through $value and save as array of $attribute->getType()
            $objectEntity = $this->saveAttributeMultiple($objectEntity, $attribute, $valueObject, $value);
        } else {
            $objectEntity = $this->saveAttributeType($objectEntity, $attribute, $valueObject, $value);
        }

        return $objectEntity;
    }

    /**
     * Saves a subObject using saveObject. Will also set the owner, uri, organization and application. And check for a Entity function.
     *
     * @param ObjectEntity $subObject
     * @param              $object
     *
     * @throws InvalidArgumentException
     *
     * @return ObjectEntity
     * @deprecated
     */
    private function saveSubObject(ObjectEntity $subObject, $object): ObjectEntity
    {
        $subObject = $this->saveObject($subObject, $object);
        $this->handleOwner($subObject); // Do this after all CheckAuthorization function calls

        // We need to set uri here in case we need it in $this->functionService->handleFunction later!
        $subObject->setUri($this->createUri($subObject));

        // todo remove if no longer needed, see value.php setValue() where we set owner, organization and application for subobjects
        // Set organization for this object
        if (count($subObject->getSubresourceOf()) > 0 && !empty($subObject->getSubresourceOf()->first()->getObjectEntity()->getOrganization())) {
            $subObject->setOrganization($subObject->getSubresourceOf()->first()->getObjectEntity()->getOrganization());
            $subObject->setApplication($subObject->getSubresourceOf()->first()->getObjectEntity()->getApplication());
        } else {
            $subObject->setOrganization($this->session->get('activeOrganization'));
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            $subObject->setApplication(!empty($application) ? $application : null);
        }

        return $this->functionService->handleFunction($subObject, $subObject->getEntity()->getFunction(), [
            'method'           => $this->request->getMethod(),
            'uri'              => $subObject->getUri(),
            'organizationType' => is_array($object) && array_key_exists('type', $object) ? $object['type'] : null,
            'userGroupName'    => is_array($object) && array_key_exists('name', $object) ? $object['name'] : null,
        ]);
    }

    /**
     * @TODO
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param Value        $valueObject
     * @param              $value
     *
     * @throws InvalidArgumentException
     *
     * @return ObjectEntity
     * @deprecated
     */
    private function saveAttributeMultiple(ObjectEntity $objectEntity, Attribute $attribute, Value $valueObject, $value): ObjectEntity
    {
        switch ($attribute->getType()) {
            case 'object':
                $subObjectIds = [];
                $saveSubObjects = new ArrayCollection(); // collection to store all new subobjects in before we actually connect them to the value
                foreach ($value as $key => $object) {
                    // If we are not cascading and value is a string, then value should be an id.
                    if (is_string($object)) {
                        if (Uuid::isValid($object) == false) {
                            // We should also allow commonground Uri's like: https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
                            // TODO: support /$attribute->getObject()->getEndpoint()/uuid?
                            if ($object == $attribute->getObject()->getSource()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($object)) {
                                $object = $this->commonGroundService->getUuidFromUrl($object);
                            } else {
//                                var_dump('The given value ('.$object.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getSource()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).');
                                continue;
                            }
                        }

                        // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
                        // todo make one sql query for finding an ObjectEntity by id or externalId
                        if (!$subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'id' => $object])) {
                            if (!$subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'externalId' => $object])) {
                                // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside the gateway for an existing object.
                                if (!$subObject) {
                                    // todo: throw error?
//                                    var_dump('Could not find an object with id '.$object.' of type '.$attribute->getObject()->getName());
                                    continue;
                                }
                            }
                        }
                        // object toevoegen
                        $saveSubObjects->add($subObject);
                        continue;
                    }

                    // If we are doing a PUT with a subObject that contains an id, find the object with this id and update it.
                    if (($this->request->getMethod() == 'PUT' || $this->request->getMethod() == 'PATCH') && array_key_exists('id', $object)) {
                        if (!is_string($object['id']) || Uuid::isValid($object['id']) == false) {
//                            var_dump('The given value ('.$object['id'].') is not a valid uuid.');
                            continue;
                        }
                        $subObject = $valueObject->getObjects()->filter(function (ObjectEntity $item) use ($object) {
                            return $item->getId() == $object['id'] || $item->getExternalId() == $object['id'];
                        });
                        if (count($subObject) == 0) {
                            // look outside the gateway

                            if (!$subObject) {
                                // todo: throw error?
//                                var_dump('Could not find an object with id '.$object['id'].' of type '.$attribute->getObject()->getName());
                                continue;
                            }

                            // object toevoegen
                            $saveSubObjects->add($subObject);
                            continue;
                        } elseif (count($subObject) > 1) {
//                            var_dump('Found more than 1 object with id '.$object['id'].' of type '.$attribute->getObject()->getName());
                            continue;
                        } else {
                            $subObject = $subObject->first();
                        }
                    } elseif (($this->request->getMethod() == 'PUT' || $this->request->getMethod() == 'PATCH')
                        && count($value) == 1
                        && count($valueObject->getObjects()) == 1) {
                        // If we are doing a PUT with a single subObject (and it contains no id) and the existing mainObject only has a single subObject, use the existing subObject and update that.
                        $subObject = $valueObject->getObjects()->first();
                        $object['id'] = $subObject->getExternalId();
                    } else {
                        //Lets do a cascade check here.
                        if (!$attribute->getCascade() && !is_string($object)) {
                            continue;
                        }

                        // Create a new subObject (ObjectEntity)
                        $subObject = new ObjectEntity();
                        $subObject->setEntity($attribute->getObject());
                        $subObject->addSubresourceOf($valueObject);
                    }

                    $subObject->setSubresourceIndex($key);

                    $subObject = $this->saveSubObject($subObject, $object);

                    // object toevoegen
                    $saveSubObjects->add($subObject);
                    $subObjectIds[] = $subObject->getId()->toString();
                }
                $valueObject->setArrayValue($subObjectIds);

                // If we are doing a put, we want to actually clear (or remove) objects connected to this valueObject we no longer need
                if ($this->request->getMethod() == 'PUT' || $this->request->getMethod() == 'PATCH') {
                    foreach ($valueObject->getObjects() as $object) {
                        // If we are not re-adding this object... allow delete on PUT
                        if (!$saveSubObjects->contains($object)) {
                            $object->removeSubresourceOf($valueObject);
                            if (count($object->getSubresourceOf()) == 0) {
                                $this->eavService->handleDelete($object);
                            }
                        }
                    }
                    $valueObject->getObjects()->clear();
                }
                // Actually add the objects to the valueObject
                foreach ($saveSubObjects as $saveSubObject) {
                    // Make sure we never connect the value of a multiple=false attribute to more than one object! Checks inversedBy
                    $this->disconnectNotMultipleObjects($objectEntity, $attribute, $saveSubObject);

                    $valueObject->addObject($saveSubObject);
                }
                break;
            case 'file':
                foreach ($value as $file) {
                    $objectEntity = $this->saveFile($objectEntity, $valueObject, $this->base64ToFileArray($file));
                }
                break;
            default:
                // do nothing
                break;
        }

        return $objectEntity;
    }

    /**
     * @TODO
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param Value        $valueObject
     * @param              $value
     *
     * @throws Exception
     *
     * @return ObjectEntity
     * @deprecated
     */
    private function saveAttributeType(ObjectEntity $objectEntity, Attribute $attribute, Value $valueObject, $value): ObjectEntity
    {
        switch ($attribute->getType()) {
            case 'object':
                // Check for cascading (should already be done by validatorService...
                if (!$attribute->getCascade() && !is_string($value)) {
                    break;
                }

                // If we are not cascading and value is a string, then value should be an id.
                if (is_string($value)) {
                    if (Uuid::isValid($value) == false) {
                        // We should also allow commonground Uri's like: https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
                        // TODO: support /$attribute->getObject()->getEndpoint()/uuid?
//                        if ($value == $attribute->getObject()->getSource()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($value)) {
//                            $value = $this->commonGroundService->getUuidFromUrl($value);
//                        } else {
                        ////                            var_dump('The given value ('.$value.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getSource()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).');
//                            break;
//                        }
                    }

                    // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
                    // todo make one sql query for finding an ObjectEntity by id or externalId
                    if (!$subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'id' => $value])) {
                        if (!$subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'externalId' => $value])) {
                            // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside the gateway for an existing object.
                            if (!$subObject) {
                                // todo: throw error?
//                                var_dump('Could not find an object with id '.$value.' of type '.$attribute->getObject()->getName());
                                break;
                            }
                        }
                    }

                    // Make sure we never connect the value of a multiple=false attribute to more than one object! Checks inversedBy.
                    $this->disconnectNotMultipleObjects($objectEntity, $attribute, $subObject);

                    // Object toevoegen
                    $valueObject->getObjects()->clear(); // We start with a default object
                    $valueObject->addObject($subObject);
                    break;
                }

                if (!$valueObject->getValue()) {
                    // Cascading...
                    $subObject = new ObjectEntity();
                    $subObject->setEntity($attribute->getObject());
                    $subObject->addSubresourceOf($valueObject);
                } else {
                    // Put...
                    $subObject = $valueObject->getValue();
                }

                $subObject = $this->saveSubObject($subObject, $value);

                $this->entityManager->persist($subObject);

                $valueObject->setValue($subObject);

                break;
            case 'file':
                $objectEntity = $this->saveFile($objectEntity, $valueObject, $this->base64ToFileArray($value));
                break;
            default:
                // do nothing
                break;
        }

        return $objectEntity;
    }

    /**
     * This function will check if an attribute has inversedBy, if so, get the inversedBy value and check if this value does not already have given $objectEntity as a relation.
     * If inversedBy value does not have the $objectEntity as relation and the attribute of this inversedBy value is multiple=false this inversedBy value should only contain one object.
     * So, if the inversedBy value has already one or more other objects connected to it, disconnect all these objects, so we can add $objectEntity as the only relation after using this function.
     *
     * @param ObjectEntity $objectEntity The 'parent' objectEntity of $attribute we might want to add as a inversedBy relation to $subObject.
     * @param Attribute    $attribute    The attribute we are going to check inversedBy on and get its value if it has a inversedBy attribute/value.
     * @param ObjectEntity $subObject    The 'child' objectEntity we want to add to the Value of the $attribute of $objectEntity.
     *
     * @return void
     */
    private function disconnectNotMultipleObjects(ObjectEntity $objectEntity, Attribute $attribute, ObjectEntity $subObject)
    {
        // Make sure we never connect the value of a multiple=false attribute to more than one object!
        if ($attribute->getInversedBy()) {
            // If we have inversedBy on this attribute
            $inversedByValue = $subObject->getValueObject($attribute->getInversedBy());
            if (!$inversedByValue->getObjects()->contains($objectEntity)) { // $valueObject->getObjectEntity() = $objectEntity
                // If inversedBy attribute is not multiple it should only have one object connected to it
                if (!$attribute->getInversedBy()->getMultiple() and count($inversedByValue->getObjects()) > 0) {
                    // Disconnect old objects
                    foreach ($inversedByValue->getObjects() as $object) {
                        // Clear any objects and there parent relations (subresourceOf) to make sure we only can have one object connected.
                        $object->removeSubresourceOf($inversedByValue);
                    }
                }
            }
        }
    }

    /**
     * @TODO
     *
     * @param ObjectEntity $objectEntity
     * @param Value        $valueObject
     * @param array        $fileArray
     *
     * @return ObjectEntity
     */
    private function saveFile(ObjectEntity $objectEntity, Value $valueObject, array $fileArray): ObjectEntity
    {
        if ($fileArray['name']) {
            // Find file by filename (this can be the uuid of the file object)
            $fileObject = $valueObject->getFiles()->filter(function (File $item) use ($fileArray) {
                return $item->getName() == $fileArray['name'];
            });
            if (count($fileObject) > 1) {
//                var_dump($attribute->getName().'.name More than 1 file found with this name: '.$fileArray['name']);
                // todo: throw error?
            }
        }

        if (isset($fileObject) && count($fileObject) == 1) {
            // Update existing file if we found one using the given file name
            $fileObject = $fileObject->first();
        } else {
            // Create a new file
            $fileObject = new File();
        }
        $this->entityManager->persist($fileObject); // For getting the id if no name is given
        $fileObject->setName($fileArray['name'] ?? $fileObject->getId());
        $fileObject->setExtension($fileArray['extension']);
        $fileObject->setMimeType($fileArray['mimeType']);
        $fileObject->setSize($fileArray['size']);
        $fileObject->setBase64($fileArray['base64']);

        $valueObject->addFile($fileObject);

        return $objectEntity;
    }

    /**
     * Converts a mime type to an extension (or find all mime_types with an extension).
     *
     * @param      $mime
     * @param null $ext
     *
     * @return array|false|string
     */
    private function mimeToExt($mime, $ext = null)
    {
        // todo: move this to a dedicated file and get it from there?
        $mime_map = [
            'video/3gpp2'                                                               => '3g2',
            'video/3gp'                                                                 => '3gp',
            'video/3gpp'                                                                => '3gp',
            'application/x-compressed'                                                  => '7zip',
            'audio/x-acc'                                                               => 'aac',
            'audio/ac3'                                                                 => 'ac3',
            'application/postscript'                                                    => 'ai',
            'audio/x-aiff'                                                              => 'aif',
            'audio/aiff'                                                                => 'aif',
            'audio/x-au'                                                                => 'au',
            'video/x-msvideo'                                                           => 'avi',
            'video/msvideo'                                                             => 'avi',
            'video/avi'                                                                 => 'avi',
            'application/x-troff-msvideo'                                               => 'avi',
            'application/macbinary'                                                     => 'bin',
            'application/mac-binary'                                                    => 'bin',
            'application/x-binary'                                                      => 'bin',
            'application/x-macbinary'                                                   => 'bin',
            'image/bmp'                                                                 => 'bmp',
            'image/x-bmp'                                                               => 'bmp',
            'image/x-bitmap'                                                            => 'bmp',
            'image/x-xbitmap'                                                           => 'bmp',
            'image/x-win-bitmap'                                                        => 'bmp',
            'image/x-windows-bmp'                                                       => 'bmp',
            'image/ms-bmp'                                                              => 'bmp',
            'image/x-ms-bmp'                                                            => 'bmp',
            'application/bmp'                                                           => 'bmp',
            'application/x-bmp'                                                         => 'bmp',
            'application/x-win-bitmap'                                                  => 'bmp',
            'application/cdr'                                                           => 'cdr',
            'application/coreldraw'                                                     => 'cdr',
            'application/x-cdr'                                                         => 'cdr',
            'application/x-coreldraw'                                                   => 'cdr',
            'image/cdr'                                                                 => 'cdr',
            'image/x-cdr'                                                               => 'cdr',
            'zz-application/zz-winassoc-cdr'                                            => 'cdr',
            'application/mac-compactpro'                                                => 'cpt',
            'application/pkix-crl'                                                      => 'crl',
            'application/pkcs-crl'                                                      => 'crl',
            'application/x-x509-ca-cert'                                                => 'crt',
            'application/pkix-cert'                                                     => 'crt',
            'text/css'                                                                  => 'css',
            'text/x-comma-separated-values'                                             => 'csv',
            'text/comma-separated-values'                                               => 'csv',
            'application/vnd.msexcel'                                                   => 'csv',
            'application/x-director'                                                    => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/x-dvi'                                                         => 'dvi',
            'message/rfc822'                                                            => 'eml',
            'application/x-msdownload'                                                  => 'exe',
            'video/x-f4v'                                                               => 'f4v',
            'audio/x-flac'                                                              => 'flac',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/gpg-keys'                                                      => 'gpg',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'application/mac-binhex40'                                                  => 'hqx',
            'application/mac-binhex'                                                    => 'hqx',
            'application/x-binhex40'                                                    => 'hqx',
            'application/x-mac-binhex40'                                                => 'hqx',
            'text/html'                                                                 => 'html',
            'image/x-icon'                                                              => 'ico',
            'image/x-ico'                                                               => 'ico',
            'image/vnd.microsoft.icon'                                                  => 'ico',
            'text/calendar'                                                             => 'ics',
            'application/java-archive'                                                  => 'jar',
            'application/x-java-application'                                            => 'jar',
            'application/x-jar'                                                         => 'jar',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'application/x-javascript'                                                  => 'js',
            'application/json'                                                          => 'json',
            'text/json'                                                                 => 'json',
            'application/vnd.google-earth.kml+xml'                                      => 'kml',
            'application/vnd.google-earth.kmz'                                          => 'kmz',
            'text/x-log'                                                                => 'log',
            'audio/x-m4a'                                                               => 'm4a',
            'audio/mp4'                                                                 => 'm4a',
            'application/vnd.mpegurl'                                                   => 'm4u',
            'audio/midi'                                                                => 'mid',
            'application/vnd.mif'                                                       => 'mif',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/oda'                                                           => 'oda',
            'audio/ogg'                                                                 => 'ogg',
            'video/ogg'                                                                 => 'ogg',
            'application/ogg'                                                           => 'ogg',
            'font/otf'                                                                  => 'otf',
            'application/x-pkcs10'                                                      => 'p10',
            'application/pkcs10'                                                        => 'p10',
            'application/x-pkcs12'                                                      => 'p12',
            'application/x-pkcs7-signature'                                             => 'p7a',
            'application/pkcs7-mime'                                                    => 'p7c',
            'application/x-pkcs7-mime'                                                  => 'p7c',
            'application/x-pkcs7-certreqresp'                                           => 'p7r',
            'application/pkcs7-signature'                                               => 'p7s',
            'application/pdf'                                                           => 'pdf',
            'application/octet-stream'                                                  => 'pdf',
            'application/x-x509-user-cert'                                              => 'pem',
            'application/x-pem-file'                                                    => 'pem',
            'application/pgp'                                                           => 'pgp',
            'application/x-httpd-php'                                                   => 'php',
            'application/php'                                                           => 'php',
            'application/x-php'                                                         => 'php',
            'text/php'                                                                  => 'php',
            'text/x-php'                                                                => 'php',
            'application/x-httpd-php-source'                                            => 'php',
            'image/png'                                                                 => 'png',
            'image/x-png'                                                               => 'png',
            'application/powerpoint'                                                    => 'ppt',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.ms-office'                                                 => 'ppt',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'audio/x-realaudio'                                                         => 'ra',
            'audio/x-pn-realaudio'                                                      => 'ram',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'audio/x-pn-realaudio-plugin'                                               => 'rpm',
            'application/x-pkcs7'                                                       => 'rsa',
            'text/rtf'                                                                  => 'rtf',
            'text/richtext'                                                             => 'rtx',
            'video/vnd.rn-realvideo'                                                    => 'rv',
            'application/x-stuffit'                                                     => 'sit',
            'application/smil'                                                          => 'smil',
            'text/srt'                                                                  => 'srt',
            'image/svg+xml'                                                             => 'svg',
            'application/x-shockwave-flash'                                             => 'swf',
            'application/x-tar'                                                         => 'tar',
            'application/x-gzip-compressed'                                             => 'tgz',
            'image/tiff'                                                                => 'tiff',
            'font/ttf'                                                                  => 'ttf',
            'text/plain'                                                                => 'txt',
            'text/x-vcard'                                                              => 'vcf',
            'application/videolan'                                                      => 'vlc',
            'text/vtt'                                                                  => 'vtt',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'application/wbxml'                                                         => 'wbxml',
            'video/webm'                                                                => 'webm',
            'image/webp'                                                                => 'webp',
            'audio/x-ms-wma'                                                            => 'wma',
            'application/wmlc'                                                          => 'wmlc',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'font/woff'                                                                 => 'woff',
            'font/woff2'                                                                => 'woff2',
            'application/xhtml+xml'                                                     => 'xhtml',
            'application/excel'                                                         => 'xl',
            'application/msexcel'                                                       => 'xls',
            'application/x-msexcel'                                                     => 'xls',
            'application/x-ms-excel'                                                    => 'xls',
            'application/x-excel'                                                       => 'xls',
            'application/x-dos_ms_excel'                                                => 'xls',
            'application/xls'                                                           => 'xls',
            'application/x-xls'                                                         => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-excel'                                                  => 'xlsx',
            'application/xml'                                                           => 'xml',
            'text/xml'                                                                  => 'xml',
            'text/xsl'                                                                  => 'xsl',
            'application/xspf+xml'                                                      => 'xspf',
            'application/x-compress'                                                    => 'z',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
            'text/x-scriptzsh'                                                          => 'zsh',
        ];

        if ($ext) {
            $mime_types = [];
            foreach ($mime_map as $mime_type => $extension) {
                if ($extension == $ext) {
                    $mime_types[] = $mime_type;
                }
            }

            return $mime_types;
        }

        return $mime_map[$mime] ?? false;
    }

    /**
     * Create a file array (matching the Entity File) from an array containing at least a base64 string and maybe a filename (not required).
     *
     * @param array $file
     *
     * @return array
     */
    private function base64ToFileArray(array $file): array
    {
        // Get mime_type from base64
        $explode_base64 = explode(',', $file['base64']);
        $imgdata = base64_decode(end($explode_base64));
        $f = finfo_open();
        $mime_type = finfo_buffer($f, $imgdata, FILEINFO_MIME_TYPE);
        finfo_close($f);

        // Create file data
        return [
            'name' => array_key_exists('filename', $file) ? $file['filename'] : null,
            // Get extension from filename, and else from the mime_type
            'extension' => array_key_exists('filename', $file) ? pathinfo($file['filename'], PATHINFO_EXTENSION) : $this->mimeToExt($mime_type),
            'mimeType'  => $mime_type,
            'size'      => $this->getBase64Size($file['base64']),
            'base64'    => $file['base64'],
        ];
    }

    /**
     * Gets the memory size of a base64 file.
     *
     * @param $base64
     *
     * @return Exception|float|int
     */
    private function getBase64Size($base64)
    { //return memory size in B, KB, MB
        try {
            $size_in_bytes = (int) (strlen(rtrim($base64, '=')) * 3 / 4);
            $size_in_kb = $size_in_bytes / 1024;
            $size_in_mb = $size_in_kb / 1024;

            return $size_in_bytes;
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Create a file array (matching the Entity File) from an UploadedFile object.
     *
     * @param UploadedFile $file
     * @param string|null  $key
     *
     * @return array
     */
    public function uploadedFileToFileArray(UploadedFile $file, string $key = null): array
    {
        return [
            'name'      => $file->getClientOriginalName() ?? null,
            'extension' => $file->getClientOriginalExtension() ?? $file->getClientMimeType() ? $this->mimeToExt($file->getClientMimeType()) : null,
            'mimeType'  => $file->getClientMimeType() ?? null,
            'size'      => $file->getSize() ?? null,
            'base64'    => $this->uploadToBase64($file),
            'key'       => $key, // Pass this through for showing correct error messages with multiple files
        ];
    }

    /**
     * Create a base64 string from an UploadedFile object.
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    private function uploadToBase64(UploadedFile $file): string
    {
        $content = base64_encode($file->openFile()->fread($file->getSize()));
        $mimeType = $file->getClientMimeType();

        return 'data:'.$mimeType.';base64,'.$content;
    }

    /**
     * @TODO
     *
     * @param ObjectEntity $objectEntity
     *
     * @return string
     * @deprecated
     */
    public function createUri(ObjectEntity $objectEntity): string
    {
        // We need to persist if this is a new ObjectEntity in order to set and getId to generate the uri...
        $this->entityManager->persist($objectEntity);
        if ($objectEntity->getEntity()->getSource() && $objectEntity->getEntity()->getSource()->getLocation() && $objectEntity->getExternalId()) {
            return $objectEntity->getEntity()->getSource()->getLocation().'/'.$objectEntity->getEntity()->getEndpoint().'/'.$objectEntity->getExternalId();
        }

        $uri = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' ? 'https://'.$_SERVER['HTTP_HOST'] : 'http://localhost';

        if ($objectEntity->getEntity()->getRoute()) {
            return $uri.'/api'.$objectEntity->getEntity()->getRoute().'/'.$objectEntity->getId();
        }

        return $uri.'/admin/object_entities/'.$objectEntity->getId();
    }

    /**
     * Returns the string used for {at sign}id or self->href for the given objectEntity. This function will use the ObjectEntity->Entity
     * to first look for the get item endpoint and else use the Entity route or name to generate the correct string.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return string
     * @deprecated
     */
    public function createSelf(ObjectEntity $objectEntity): string
    {
        // We need to persist if this is a new ObjectEntity in order to set and getId to generate the self...
        $this->entityManager->persist($objectEntity);
        $endpoints = $this->entityManager->getRepository('App:Endpoint')->findGetItemByEntity($objectEntity->getEntity());
        if (count($endpoints) > 0 && $endpoints[0] instanceof Endpoint) {
            $pathArray = $endpoints[0]->getPath();
            $foundId = in_array('{id}', $pathArray) ? $pathArray[array_search('{id}', $pathArray)] = $objectEntity->getId() :
                (in_array('{uuid}', $pathArray) ? $pathArray[array_search('{uuid}', $pathArray)] = $objectEntity->getId() : false);
            if ($foundId !== false) {
                $path = implode('/', $pathArray);

                return '/api/'.$path;
            }
        }

        return '/api'.($objectEntity->getEntity()->getRoute() ?? $objectEntity->getEntity()->getName()).'/'.$objectEntity->getId();
    }

    /**
     * Create a NRC notification for the given ObjectEntity.
     *
     * @param ObjectEntity $objectEntity
     * @param string       $method
     * @deprecated
     */
    public function notify(ObjectEntity $objectEntity, string $method)
    {
        if (!$this->commonGroundService->getComponent('nrc')) {
            return;
        }
        // TODO: move this function to a notificationService?
        $topic = $objectEntity->getEntity()->getName();
        switch ($method) {
            case 'POST':
                $action = 'Create';
                break;
            case 'PUT':
            case 'PATCH':
                $action = 'Update';
                break;
            case 'DELETE':
                $action = 'Delete';
                break;
        }
        if (isset($action)) {
            $notification = [
                'topic'    => $topic,
                'action'   => $action,
                'resource' => $objectEntity->getUri(),
                'id'       => $objectEntity->getExternalId(),
            ];
            if (!$objectEntity->getUri()) {
                //                var_dump('Couldn\'t notifiy for object, because it has no uri!');
                //                var_dump('Id: '.$objectEntity->getId());
                //                var_dump('ExternalId: '.$objectEntity->getExternalId() ?? null);
                //                var_dump($notification);
                return;
            }
            $this->commonGroundService->createResource($notification, ['component' => 'nrc', 'type' => 'notifications'], false, true, false);
        }
    }

    /**
     * When rendering a single attribute value for the post body of the api-call/promise to update an object in a source outside the gateway,
     * and when the type of this attribute is object and cascading on this attribute is not allowed,
     * try and render/use the entire object for all subresources of this attribute.
     *
     * @param Collection $objects
     * @param Attribute  $attribute
     *
     * @return array|mixed|null
     * @deprecated
     */
    public function renderSubObjects(Collection $objects, Attribute $attribute)
    {
        $results = [];
        foreach ($objects as $object) {
            // We allow cascading on promises, but only if the gateway of the parent entity and subresource match.
            $results[] =
                $object->getEntity()->getSource() == $attribute->getEntity()->getSource() ?
                    $this->renderPostBody($object) :
                    $object->getUri();
        }
        if (!$attribute->getMultiple()) {
            if (count($results) == 1) {
                return $results[0];
            } else {
                return null;
            }
        } else {
            return $results;
        }
    }

    /**
     * When rendering a single attribute value for the post body of the api-call/promise to update an object in a source outside the gateway,
     * and when the type of this attribute is object and cascading on this attribute is not allowed,
     * only render/use the uri for all subresources of this attribute.
     *
     * @param Collection $objects
     * @param Attribute  $attribute
     *
     * @return array|mixed|string|null
     * @deprecated
     */
    public function getSubObjectIris(Collection $objects, Attribute $attribute)
    {
        $results = [];
        foreach ($objects as $object) {
            $results[] =
                $object->getEntity()->getSource() == $attribute->getEntity()->getSource() ?
                    "/{$object->getEntity()->getEndpoint()}/{$object->getExternalId()}" :
                    $object->getUri();
        }
        if (!$attribute->getMultiple()) {
            if (count($results) == 1) {
                return $results[0];
            } else {
                return null;
            }
        } else {
            return $results;
        }
    }

    /**
     * Render a single attribute value for the post body of the api-call/promise to update an object in a source outside the gateway (before doing the api-call).
     *
     * @param Value     $value
     * @param Attribute $attribute
     *
     * @return File[]|Value[]|array|bool|Collection|float|int|mixed|string|void|null
     * @deprecated
     */
    public function renderValue(Value $value, Attribute $attribute)
    {
        $rendered = '';
        switch ($attribute->getType()) {
            case 'object':
                // We allow cascading on promises, but only if the gateway of the parent entity and subresource match.
                if ($attribute->getCascade()) {
                    $rendered = $this->renderSubObjects($value->getObjects(), $attribute);
                } else {
                    $rendered = $this->getSubObjectIris($value->getObjects(), $attribute);
                }
                break;
            default:
                $rendered = $value->getValue();
        }

        return $rendered;
    }

    /**
     * Render the post body, with all attributes to update/send with the api-call/promise to update an object in a source outside the gateway (before doing the api-call).
     *
     * @param ObjectEntity $objectEntity
     *
     * @return array
     * @deprecated
     */
    public function renderPostBody(ObjectEntity $objectEntity): array
    {
        $body = [];
        foreach ($objectEntity->getEntity()->getAttributes() as $attribute) {
            // todo: With this ===null check we can never set a value to null with a promise.
            // todo: Maybe we should add a new bool to attribute that determines it shouldn't be added if value===null?
            if (!$attribute->getPersistToGateway() || (!$attribute->getRequired() && $objectEntity->getValue($attribute) === null)) {
                continue;
            }
            $body[$attribute->getName()] = $this->renderValue($objectEntity->getValueObject($attribute), $attribute);
        }

        return $body;
    }

    /**
     * Encode body for the api-call/promise to update an object in a source outside the gateway, before doing the api-call.
     *
     * @param ObjectEntity $objectEntity
     * @param array        $body
     * @param array        $headers
     *
     * @throws Exception
     *
     * @return string
     * @deprecated
     */
    public function encodeBody(ObjectEntity $objectEntity, array $body, array &$headers): string
    {
        switch ($objectEntity->getEntity()->getSource()->getType()) {
            case 'json':
                $body = json_encode($body);
                break;
            case 'soap':
                $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'S:Envelope']);
                $body = $this->translationService->parse($xmlEncoder->encode($this->translationService->dotHydrator(
                    $objectEntity->getEntity()->getToSoap()->getRequest() ? $xmlEncoder->decode($objectEntity->getEntity()->getToSoap()->getRequest(), 'xml') : [],
                    $objectEntity->toArray(),
                    $objectEntity->getEntity()->getToSoap()->getRequestHydration()
                ), 'xml', ['xml_encoding' => 'utf-8', 'remove_empty_tags' => true]), false);
                $headers['Content-Type'] = 'application/xml;charset=UTF-8';
                break;
            default:
                throw new Exception('Encoding type not supported');
        }

        return $body;
    }

    /**
     * If there is special translation config for the api-calls/promises to update an object in a source outside the gateway, before doing the api-call.
     *
     * @param ObjectEntity $objectEntity
     * @param string       $method
     * @param array        $headers
     * @param array        $query
     * @param string       $url
     *
     * @return void
     */
    public function getTranslationConfig(ObjectEntity $objectEntity, string &$method, array &$headers, array &$query, string &$url): void
    {
        $oldMethod = $method;
        $config = $objectEntity->getEntity()->getTranslationConfig();
        if ($config && array_key_exists($method, $config)) {
            !array_key_exists('method', $config[$oldMethod]) ?: $method = $config[$oldMethod]['method'];
            !array_key_exists('headers', $config[$oldMethod]) ?: $headers = array_merge($headers, $config[$oldMethod]['headers']);
            !array_key_exists('query', $config[$oldMethod]) ?: $headers = array_merge($query, $config[$oldMethod]['headers']);
            !array_key_exists('endpoint', $config[$oldMethod]) ?: $url = $objectEntity->getEntity()->getSource()->getLocation().'/'.str_replace('{id}', $objectEntity->getExternalId(), $config[$oldMethod]['endpoint']);
        }
    }

    /**
     * Decide what method and url to use for a promise to update an object in a source outside the gateway.
     *
     * @param ObjectEntity $objectEntity
     * @param string       $url
     * @param string       $method
     *
     * @return void
     */
    public function decideMethodAndUrl(ObjectEntity $objectEntity, string &$url, string &$method): void
    {
        if ($method == 'POST' && $objectEntity->getUri() != $objectEntity->getEntity()->getSource()->getLocation().'/'.$objectEntity->getEntity()->getEndpoint().'/'.$objectEntity->getExternalId()) {
            $url = $objectEntity->getEntity()->getSource()->getLocation().'/'.$objectEntity->getEntity()->getEndpoint();
        } elseif ($objectEntity->getUri()) {
            $method = 'PUT';
            $url = $objectEntity->getUri();
        } elseif ($objectEntity->getExternalId()) {
            $method = 'PUT';
            $url = $objectEntity->getEntity()->getSource()->getLocation().'/'.$objectEntity->getEntity()->getEndpoint().'/'.$objectEntity->getExternalId();
        }
    }

    /**
     * Makes sure if an ObjectEntity has any subresources these wil also result in promises to update those objects in a source outside the gateway.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return void
     */
    private function settleSubPromises(ObjectEntity $objectEntity): void
    {
        foreach ($objectEntity->getSubresources() as $sub) {
            $promises = $sub->getPromises();
        }

        if (!empty($promises)) {
            Utils::settle($promises)->wait();
        }
    }

    /**
     * Decodes the response of a successful promise to update an object in a source outside the gateway.
     *
     * @param              $response
     * @param ObjectEntity $objectEntity
     *
     * @throws Exception
     *
     * @return array
     */
    private function decodeResponse($response, ObjectEntity $objectEntity): array
    {
        switch ($objectEntity->getEntity()->getSource()->getType()) {
            case 'json':
                $result = json_decode($response->getBody()->getContents(), true);
                break;
            case 'xml':
                $xmlEncoder = new XmlEncoder();
                $result = $xmlEncoder->decode($response->getBody()->getContents(), 'xml');
                break;
            case 'soap':
                $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'soap:Envelope']);
                $result = $response->getBody()->getContents();
                // $result = $this->translationService->parse($result);
                $result = $xmlEncoder->decode($result, 'xml');
                $result = $this->translationService->dotHydrator([], $result, $objectEntity->getEntity()->getToSoap()->getResponseHydration());
                break;
            default:
                throw new Exception('Unsupported type');
        }

        return $result;
    }

    /**
     * Set externalId of an ObjectEntity after a successful promise to update an object in a source outside the gateway.
     *
     * @param ObjectEntity $objectEntity
     * @param array        $result
     * @param string       $url
     * @param string       $method
     *
     * @return ObjectEntity
     */
    private function setExternalId(ObjectEntity $objectEntity, array $result, string $url, string $method): ObjectEntity
    {
        if (array_key_exists('id', $result) && !strpos($url, $result['id'])) {
            $objectEntity->setUri($url.'/'.$result['id']);
            $objectEntity->setExternalId($result['id']);
        } else {
            $objectEntity->setUri($url);
            $objectEntity->setExternalId($this->commonGroundService->getUuidFromUrl($url));
        }

//        var_dump('GetUri: '.$objectEntity->getUri());

        // Handle Function todo: what if @organization is used in the post body? than we shouldn't handle function organization here:
        return $this->functionService->handleFunction($objectEntity, $objectEntity->getEntity()->getFunction(), [
            'method' => $method,
            'uri'    => $objectEntity->getUri(),
        ]);
    }

    /**
     * Set externalResult of an ObjectEntity after a successful promise to update an object in a source outside the gateway.
     *
     * @param ObjectEntity $objectEntity
     * @param array        $result
     *
     * @return ObjectEntity
     */
    private function setExternalResult(ObjectEntity $objectEntity, array $result): ObjectEntity
    {
        if (!is_null($objectEntity->getEntity()->getAvailableProperties())) {
            $availableProperties = $objectEntity->getEntity()->getAvailableProperties();
            $result = array_filter($result, function ($key) use ($availableProperties) {
                return in_array($key, $availableProperties);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $objectEntity->setExternalResult($result);
    }

    /**
     * Handle successful/ok response of a promise to update an object in a source outside the gateway.
     * Includes updating the Gateway ObjectEntity, Gateway Cache and sending an async notification.
     *
     * @param              $response
     * @param ObjectEntity $objectEntity
     * @param string       $url
     * @param string       $method
     *
     * @throws InvalidArgumentException
     *
     * @return ObjectEntity
     */
    private function onFulfilled($response, ObjectEntity $objectEntity, string $url, string $method)
    {
        $result = $this->decodeResponse($response, $objectEntity);
        $objectEntity = $this->setExternalId($objectEntity, $result, $url, $method);

        // Lets reset cache
        $this->functionService->removeResultFromCache($objectEntity);
//        $this->responseService->renderResult($objectEntity, null); // pre-load/re-load cache

        // Create Notification
//        var_dump('NOTIFICATION: '.$objectEntity->getEntity()->getName().' - '.$objectEntity->getId()->toString().' - '.$objectEntity->getExternalId().' - '.$method);
        $this->notifications[] = ['id' => $objectEntity->getId(), 'method' => $method];

        // log
//        $responseLog = new Response(json_encode($result), 201, []);
//        $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 13, json_encode($result), null, 'out');

        return $this->setExternalResult($objectEntity, $result);
    }

    /**
     * Handle error response of a promise to update an object in a source outside the gateway.
     *
     * @param              $error
     * @param ObjectEntity $objectEntity
     *
     * @return void
     */
    private function onError($error, ObjectEntity $objectEntity)
    {
        /* @todo lelijke code */
        if ($error->getResponse()) {
            $errorBody = json_decode((string) $error->getResponse()->getBody(), true);
            if ($errorBody && array_key_exists('message', $errorBody)) {
                $error_message = $errorBody['message'];
            } elseif ($errorBody && array_key_exists('hydra:description', $errorBody)) {
                $error_message = $errorBody['hydra:description'];
            } else {
                $error_message = (string) $error->getResponse()->getBody();
            }
        } else {
            $error_message = $error->getMessage();
        }
//        var_dump($error_message);

//        // log
//        if ($error->getResponse() instanceof Response) {
//            $responseLog = $error->getResponse();
//        } else {
//            $responseLog = new Response($error_message, $error->getResponse()->getStatusCode(), []);
//        }
//        $log = $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 14, $error_message, null, 'out');
        /* @todo eigenlijk willen we links naar error reports al losse property mee geven op de json error message */
        $objectEntity->addError('gateway endpoint on '.$objectEntity->getEntity()->getName().' said', $error_message.'. (see /admin/logs/'./*$log->getId().*/ ') for a full error report');
    }

    /**
     * Creates a promise to update an object in a source outside the gateway.
     *
     * @param ObjectEntity $objectEntity
     * @param string       $method
     *
     * @throws Exception
     *
     * @return PromiseInterface
     */
    public function createPromise(ObjectEntity $objectEntity, string &$method): PromiseInterface
    {
        $component = $this->gatewayService->sourceToArray($objectEntity->getEntity()->getSource());
        $query = [];
        $headers = [];
        $url = '';
        $this->decideMethodAndUrl($objectEntity, $url, $method);

        $this->settleSubPromises($objectEntity);

        $body = $this->renderPostBody($objectEntity);
        $body = $this->encodeBody($objectEntity, $body, $headers);
        $this->getTranslationConfig($objectEntity, $method, $headers, $query, $url);

//        // log
//        $this->logService->saveLog($this->logService->makeRequest(), null, 12, $body, null, 'out');

//        var_dump('CallServiceUrl: '.$url);
//        var_dump($body);

        return $this->commonGroundService->callService($component, $url, $body, $query, $headers, true, $method)->then(
            function ($response) use ($objectEntity, $url, $method) {
//                var_dump('succes');
                $this->onFulfilled($response, $objectEntity, $url, $method);
            },
            function ($error) use ($objectEntity) {
//                var_dump('error');
                $this->onError($error, $objectEntity);
            }
        );
    }

    /**
     * Implodes a multidimensional array to a string.
     *
     * @param array  $array
     * @param string $separator
     * @param string $keyValueSeparator
     *
     * @return string
     */
    public function implodeMultiArray(array $array, string $separator = ', ', string $keyValueSeparator = '='): string
    {
        $str = '';

        foreach ($array as $key => $value) {
            $currentSeparator = $separator;
            if ($key === array_key_first($array)) {
                $currentSeparator = '';
            }
            if (is_array($value)) {
                $str .= "$currentSeparator\"$key\"{$keyValueSeparator}[{$this->implodeMultiArray($value, $separator, $keyValueSeparator)}]";
            } else {
                $str .= "$currentSeparator\"$key\"$keyValueSeparator\"$value\"";
            }
        }

        return $str;
    }
}
