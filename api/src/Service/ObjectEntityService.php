<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\File;
use App\Entity\Handler;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use App\Exception\GatewayException;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Promise\Utils;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Respect\Validation\Exceptions\ComponentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Stopwatch\Stopwatch;

class ObjectEntityService
{
    private TokenStorageInterface $tokenStorage;
    private ValidaterService $validaterService;
    private SessionInterface $session;
    private ?ValidationService $validationService;
    private ?EavService $eavService;
    private EntityManagerInterface $entityManager;
    private CommonGroundService $commonGroundService;
    private ResponseService $responseService;
    private Stopwatch $stopwatch;

    // todo: we need functionService & convertToGatewayService in this service for the saveObject function, add them somehow or move code to other services...
    public function __construct(
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack,
        AuthorizationService $authorizationService,
        ApplicationService $applicationService,
        ValidaterService $validaterService,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        CommonGroundService $commonGroundService,
        ResponseService $responseService,
        Stopwatch $stopwatch
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->request = $requestStack->getCurrentRequest();
        $this->authorizationService = $authorizationService;
        $this->applicationService = $applicationService;
        $this->validaterService = $validaterService;
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->commonGroundService = $commonGroundService;
        $this->responseService = $responseService;
        $this->stopwatch = $stopwatch;
    }

    /**
     * Add services for using the handleObject function todo: temp fix untill we no longer use these services here.
     *
     * @param ValidationService $validationService
     * @param EavService $eavService
     *
     * @return $this
     */
    public function addServices(ValidationService $validationService, EavService $eavService): ObjectEntityService
    {
        // ValidationService and EavService use the ObjectEntityService for the handleOwner and checkOwner function.
        // The only reason we need these 2 services in this ObjectEntityService is for the handleObject function,
        // because we use an old way to create, update and get ObjectEntities there.
        $this->validationService = $validationService;
        $this->eavService = $eavService;

        return $this;
    }

    /**
     * A function we want to call when doing a post or put, to set the owner of an ObjectEntity, if it hasn't one already.
     *
     * @param ObjectEntity $result
     * @param string|null  $owner
     *
     * @return ObjectEntity|array
     */
    public function handleOwner(ObjectEntity $result, ?string $owner = 'owner')
    {
        $user = $this->tokenStorage->getToken()->getUser();

        if (!is_string($user) && !$result->getOwner()) {
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
     * @TODO
     *
     * @param ObjectEntity $result
     *
     * @return bool
     */
    public function checkOwner(ObjectEntity $result): bool
    {
        // TODO: what if somehow the owner of this ObjectEntity is null? because of ConvertToGateway ObjectEntities for example?
        $user = $this->tokenStorage->getToken()->getUser();

        if (!is_string($user) && $result->getOwner() === $user->getUserIdentifier()) {
            return true;
        }

        return false;
    }

    /**
     * @TODO
     *
     * @param string $uri
     * @param array|null $fields
     *
     * @return array
     */
    public function getObjectByUri(string $uri, ?array $fields = null): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['uri' => $uri]);
        if ($object instanceof ObjectEntity) {
            return $this->responseService->renderResult($object, $fields, true);
        }

        return [];
    }

    /**
     * @TODO
     *
     * @param Entity $entity
     * @param string $id
     * @param array|null $fields
     *
     * @return array
     */
    public function getObject(Entity $entity, string $id, ?array $fields = null): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'id' => $id]);
        if ($object instanceof ObjectEntity) {
            return $this->responseService->renderResult($object, $fields, true);
        }

        return [];
    }

    /**
     * @TODO
     *
     * @param string $id
     * @param array|null $fields
     *
     * @return array
     */
    public function getPersonObject(string $id, ?array $fields = null): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['function' => 'person']);
        if ($entity instanceof Entity) {
            return $this->getObject($entity, $id, $fields);
        }

        return [];
    }

    /**
     * @TODO
     *
     * @param string $id
     * @param array|null $fields
     *
     * @return array
     */
    public function getOrganizationObject(string $id, ?array $fields = null): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['function' => 'organization']);
        if ($entity instanceof Entity) {
            return $this->getObject($entity, $id, $fields);
        }

        return [];
    }

    /**
     * @TODO
     *
     * @param string $username
     * @param array|null $fields
     *
     * @return array
     */
    public function getUserObjectEntity(string $username, ?array $fields = null): array
    {
        // Because inversedBy wil not set the UC->user->person when creating a person with a user in the gateway.
        // We need to do this in order to find the person of this user:
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'users']);

        if ($entity == null) {
            return [];
        }

        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['username' => $username]);
        if (count($objects) == 1) {
            $user = $this->responseService->renderResult($objects[0], $fields, true);
            // This: will be false if a user has no rights to do get on a person object
            if (isset($user['person'])) {
                return $user['person'];
            }
        }

        return [];
    }

    /**
     * @TODO
     *
     * @return array
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
     * A function to handle calls to eav.
     *
     * @param Handler $handler
     * @param array|null $data Data to be set into the eav
     * @param string|null $method Method from request if there is a request
     * @param string|null $operationType
     *
     * @return array $data
     *
     * @throws GatewayException|CacheException|InvalidArgumentException|ComponentException|Exception
     */
    public function handleObject(Handler $handler, array $data = null, string $method = null, ?string $operationType = null): array
    {
        // check application
        $this->stopwatch->start('getApplication', 'handleObject');
        $application = $this->applicationService->getApplication();
        $this->stopwatch->stop('getApplication');

        // If type is array application is an error
        if (gettype($application) === 'array') {
            // todo: maybe just throw a gatewayException? see getApplication() function^
            return $application;
        }

        // Get Entity
        $this->stopwatch->start('getEntity', 'handleObject');
        $entity = $handler->getEntity();
        $this->stopwatch->stop('getEntity');

        $this->stopwatch->start('saveEntity+SourceInSession', 'handleObject');
        $sessionInfo= [
            'entity' => $entity->getId()->toString(),
            'source' => $entity->getGateway() ? $entity->getGateway()->getId()->toString() : null
        ];
        $this->session->set('entitySource', $sessionInfo);
        $this->stopwatch->stop('saveEntity+SourceInSession');

        // Check ID
        $this->stopwatch->start('getFilterFromParameters', 'handleObject');
        $filters = $this->getFilterFromParameters();
        $this->stopwatch->stop('getFilterFromParameters');
        array_key_exists('id', ($filters)) && $id = $filters['id'];

        // todo throw error if get/put/patch/delete and no id ?
        // Get Object if needed
        if (isset($id) || $method == 'POST') {
            // todo: re-used old code for getting an objectEntity
            $this->stopwatch->start('getObject', 'handleObject');
            $object = $this->eavService->getObject($method == 'POST' ? null : $id, $method, $entity);
            $this->stopwatch->stop('getObject');
            if (array_key_exists('type', $object) && $object['type'] == 'Bad Request') {
                throw new GatewayException($object['message'], null, null, ['data' => $object['data'], 'path' => $object['path'], 'responseType' => Response::HTTP_BAD_REQUEST]);
            } // Lets check if the user is allowed to view/edit this resource.
            $this->stopwatch->start('checkOwner+organization', 'handleObject');
            if (!$this->checkOwner($object)) {
                // TODO: do we want to throw a different error if there are nog organizations in the session? (because of logging out for example)
                if ($object->getOrganization() && !in_array($object->getOrganization(), $this->session->get('organizations') ?? [])) {
                    throw new GatewayException('You are forbidden to view or edit this resource.', null, null, ['data' => ['id' => $id ?? null], 'path' => $entity->getName(), 'responseType' => Response::HTTP_FORBIDDEN]);
                }
            }
            $this->stopwatch->stop('checkOwner+organization');
            if ($object instanceof ObjectEntity) {
                $this->session->set('object', $object->getId()->toString());
            }
        }

        // Check for scopes, if forbidden to view/edit this, throw forbidden error
        $this->stopwatch->start('checkAuthorization', 'handleObject');
        if ((!isset($object) || !$object->getUri()) || !$this->checkOwner($object)) {
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
        $this->stopwatch->stop('checkAuthorization');

        // Check if @owner is present in the body and if so unset it.
        // note: $owner is allowed to be null!
        $owner = 'owner';
        if (array_key_exists('@owner', $data)) {
            $owner = $data['@owner'];
            unset($data['@owner']);
        }

        switch ($method) {
            case 'GET':
                //todo: -start- old code...
                //TODO: old code for getting an ObjectEntity

                // Lets allow for filtering specific fields
                $this->stopwatch->start('getRequestFields', 'handleObject');
                $fields = $this->eavService->getRequestFields($this->request);
                $this->stopwatch->stop('getRequestFields');
                if (isset($object)) {
                    if ($object instanceof ObjectEntity) {
                        $this->stopwatch->start('handleGet', 'handleObject');
                        $data = $this->eavService->handleGet($object, $fields);
                        $this->stopwatch->stop('handleGet');
                        if ($object->getHasErrors()) {
                            $data['validationServiceErrors']['Warning'] = 'There are errors, this ObjectEntity might contain corrupted data, you might want to delete it!';
                            $data['validationServiceErrors']['Errors'] = $object->getAllErrors();
                        }
                    } else {
                        $data['error'] = $object;
                    }
                } else {
                    $this->stopwatch->start('handleSearch', 'handleObject');
                    $data = $this->eavService->handleSearch($entity->getName(), $this->request, $fields, false, $filters ?? []);
                    $this->stopwatch->stop('handleSearch');
                    //todo: -end- old code...

                    if ($this->session->get('endpoint')) {
                        $this->stopwatch->start('getEndpointFromDB', 'handleObject');
                        $endpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['id' => $this->session->get('endpoint')]);
                        $this->stopwatch->stop('getEndpointFromDB');
                        if (((isset($operationType) && $operationType === 'item') || $endpoint->getOperationType() === 'item') && array_key_exists('results', $data) && count($data['results']) == 1) { // todo: $data['total'] == 1
                            $data = $data['results'][0];
                            if (isset($data['id']) && Uuid::isValid($data['id'])) {
                                $this->session->set('object', $data['id']);
                            }
                        } elseif ((isset($operationType) && $operationType === 'item') || $endpoint->getOperationType() === 'item') {
                            throw new GatewayException('No object found with these filters', null, null, ['data' => $filters ?? null, 'path' => $entity->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
                        }
                    }
                }

                break;
            case 'POST':
            case 'PUT':
            case 'PATCH':
                // validate
                $this->stopwatch->start('validateData', 'handleObject');
                if ($validationErrors = $this->validaterService->validateData($data, $entity, $method)) {
                    break;
                }
                $this->stopwatch->stop('validateData');

//                $this->stopwatch->start('saveObject', 'handleObject');
//                $object = $this->saveObject($object, $data);
//                $this->handleOwner($object, $owner); // note: $owner is allowed to be null!
//                $this->entityManager->persist($object);
//                $this->entityManager->flush();
//                $data['id'] = $object->getId()->toString();
//                $this->stopwatch->stop('saveObject');

                // @todo: -start- old code...
                // @TODO: old code for creating or updating an ObjectEntity

                $this->stopwatch->start('validateEntity', 'handleObject');
                $this->validationService->setRequest($this->request);
                //                $this->validationService->createdObjects = $this->request->getMethod() == 'POST' ? [$object] : [];
                //                $this->validationService->removeObjectsNotMultiple = []; // to be sure
                //                $this->validationService->removeObjectsOnPut = []; // to be sure
                $object = $this->validationService->validateEntity($object, $data);
                if (!empty($this->validationService->promises)) {
                    Utils::settle($this->validationService->promises)->wait();

                    foreach ($this->validationService->promises as $promise) {
                        echo $promise->wait();
                    }
                }
                $this->handleOwner($object, $owner); // note: $owner is allowed to be null!
                $this->entityManager->persist($object);
                $this->entityManager->flush();
                $data['id'] = $object->getId()->toString();
                if ($object->getHasErrors()) {
                    $data['validationServiceErrors']['Warning'] = 'There are errors, an ObjectEntity with corrupted data was added, you might want to delete it!';
                    $data['validationServiceErrors']['Errors'] = $object->getAllErrors();
                }
                $this->stopwatch->stop('validateEntity');

                //todo: -end- old code...

                break;
            case 'DELETE':
                // delete object

                //todo: -start- old code...
                //TODO: old code for deleting an ObjectEntity

                $this->stopwatch->start('handleDelete', 'handleObject');
                $data = $this->eavService->handleDelete($object);
                if (array_key_exists('type', $data) && $data['type'] == 'Forbidden') {
                    throw new GatewayException($data['message'], null, null, ['data' => $data['data'], 'path' => $data['path'], 'responseType' => Response::HTTP_FORBIDDEN]);
                }
                $this->stopwatch->stop('handleDelete');

                //todo: -end- old code...

                break;
            default:
                throw new GatewayException('This method is not allowed', null, null, ['data' => ['method' => $method], 'path' => $entity->getName(), 'responseType' => Response::HTTP_FORBIDDEN]);
        }

        if (isset($validationErrors)) {
            throw new GatewayException('Validation errors', null, null, ['data' => $validationErrors, 'path' => $entity->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        // use events

        return $data;
    }

    /**
     * @TODO
     *
     * @param ObjectEntity $objectEntity
     * @param array $post
     * @return ObjectEntity
     *
     * @throws Exception
     */
    public function saveObject(ObjectEntity $objectEntity, array $post): ObjectEntity
    {
        $entity = $objectEntity->getEntity();

        foreach ($entity->getAttributes() as $attribute) {
            // todo make sure we never have key+value in $post for readOnly, immutable & unsetable attributes, through the validaterService

            // Check if we have a value ( a value is given in the post body for this attribute, can be null)
            // If no value is present in the post body for this attribute check for defaultValue and nullable.
            if (key_exists($attribute->getName(), $post)) {
                $objectEntity = $this->saveAttribute($objectEntity, $attribute, $post[$attribute->getName()]);
            } elseif ($attribute->getDefaultValue()) {
                // todo: defaultValue should maybe be a Value object, so that defaultValue can be something else than a string
                // DefaultValue can be a uuid string to connect an object...
                $objectEntity = $this->saveAttribute($objectEntity, $attribute, $attribute->getDefaultValue());
            } elseif ($attribute->getNullable() === true) {
                $objectEntity->getValueByAttribute($attribute)->setValue(null);
            } elseif ($this->request->getMethod() == 'POST') {
                // If no value is given when creating a new object, make sure we set a value to null for this attribute.
                $objectEntity->getValueByAttribute($attribute)->setValue(null);
            }
        }

        // todo? promises (these should move to subscribers!)
        // todo: think about what to do with notifications here? this saveObject function will always notify once at the end
//        // Dit is de plek waarop we weten of er een api call moet worden gemaakt
//        if ($objectEntity->getEntity()->getGateway()) {
//            // We notify the notification component here in the createPromise function:
//            $promise = $this->createPromise($objectEntity, $post);
//            $this->promises[] = $promise; //TODO: use ObjectEntity->promises instead!
//            $objectEntity->addPromise($promise);
//        } else {
//            if (!$objectEntity->getUri()) {
//                // Lets make sure we always set the uri
//                $this->em->persist($objectEntity); // So the object has an id to set with createUri...
//                $objectEntity->setUri($this->createUri($objectEntity));
//            }
//        }

        if (!$objectEntity->getUri()) {
            // Lets make sure we always set the uri
            $this->entityManager->persist($objectEntity); // So the object has an id to set with createUri...
            $objectEntity->setUri($this->createUri($objectEntity));
        }

        if (array_key_exists('@organization', $post) && $objectEntity->getOrganization() != $post['@organization']) {
            $objectEntity->setOrganization($post['@organization']);
        }

        // Notify notification component
        $this->notify($objectEntity, $this->request->getMethod());

        return $objectEntity;
    }

    /**
     * @TODO
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute $attribute
     * @param $value
     *
     * @return ObjectEntity
     *
     * @throws Exception
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

        $valueObject = $objectEntity->getValueByAttribute($attribute);

        // If the value given by the user is empty...
        if (empty($value)) {
            if ($attribute->getMultiple() && $value === []) {
                if ($attribute->getType() == 'object') {
                    // todo: remove objects on put
//                    foreach ($valueObject->getObjects() as $object) {
//                        // If we are not re-adding this object...
//                        $this->removeObjectsOnPut[] = [
//                            'valueObject' => $valueObject,
//                            'object'      => $object,
//                        ];
//                    }
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
     * @TODO
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute $attribute
     * @param Value $valueObject
     * @param $value
     *
     * @return ObjectEntity
     */
    private function saveAttributeMultiple(ObjectEntity $objectEntity, Attribute $attribute, Value $valueObject, $value): ObjectEntity
    {
        switch ($attribute->getType()) {
            case 'object':
                $saveSubObjects = new ArrayCollection(); // collection to store all new subobjects in before we actually connect them to the value
                foreach ($value as $key => $object) {
                    // If we are not cascading and value is a string, then value should be an id.
                    if (is_string($value)) {
                        if (Uuid::isValid($value) == false) {
                            // We should also allow commonground Uri's like: https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
                            // TODO: support /$attribute->getObject()->getEndpoint()/uuid?
                            if ($value == $attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($value)) {
                                $value = $this->commonGroundService->getUuidFromUrl($value);
                            } else {
//                                var_dump('The given value ('.$object.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).');
                                continue;
                            }
                        }

                        // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
                        // todo make one sql query for finding an ObjectEntity by id or externalId
                        if (!$subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'id' => $value])) {
                            if (!$subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'externalId' => $value])) {
                                // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside the gateway for an existing object.
                                // todo: add convertToGatewayService somehow
//                                $subObject = $this->convertToGatewayService->convertToGatewayObject($attribute->getObject(), null, $value, $valueObject, $objectEntity);
                                if (!$subObject) {
                                    // todo: throw error?
//                                    var_dump('Could not find an object with id '.$value.' of type '.$attribute->getObject()->getName());
                                    continue;
                                }
                            }
                        }

                        // object toevoegen
                        $saveSubObjects->add($subObject);
                        continue;
                    }

                    // If we are doing a PUT with a subObject that contains an id, find the object with this id and update it.
                    if ($this->request->getMethod() == 'PUT' && array_key_exists('id', $object)) {
                        if (!is_string($object['id']) || Uuid::isValid($object['id']) == false) {
//                            var_dump('The given value ('.$object['id'].') is not a valid uuid.');
                            continue;
                        }
                        $subObject = $valueObject->getObjects()->filter(function (ObjectEntity $item) use ($object) {
                            return $item->getId() == $object['id'] || $item->getExternalId() == $object['id'];
                        });
                        if (count($subObject) == 0) {
                            // look outside the gateway
                            // todo: add convertToGatewayService somehow
//                            $subObject = $this->convertToGatewayService->convertToGatewayObject($attribute->getObject(), null, $object['id'], $valueObject, $objectEntity);

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
                    } elseif ($this->request->getMethod() == 'PUT' && count($value) == 1 && count($valueObject->getObjects()) == 1) {
                        // If we are doing a PUT with a single subObject (and it contains no id) and the existing mainObject only has a single subObject, use the existing subObject and update that.
                        $subObject = $valueObject->getObjects()->first();
                        $object['id'] = $subObject->getExternalId();
                    } else {
                        //Lets do a cascade check here.
                        if (!$attribute->getCascade() && !is_string($value)) {
                            continue;
                        }

                        // Create a new subObject (ObjectEntity)
                        $subObject = new ObjectEntity();
                        $subObject->setEntity($attribute->getObject());
                        $subObject->addSubresourceOf($valueObject);
                    }

                    $subObject->setSubresourceIndex($key);
                    $subObject = $this->saveObject($subObject, $object);
                    $this->handleOwner($subObject); // Do this after all CheckAuthorization function calls

                    // We need to persist if this is a new ObjectEntity in order to set and getId to generate the uri...
                    $this->entityManager->persist($subObject);
                    $subObject->setUri($this->createUri($subObject));
                    // Set organization for this object
                    if (count($subObject->getSubresourceOf()) > 0 && !empty($subObject->getSubresourceOf()->first()->getObjectEntity()->getOrganization())) {
                        $subObject->setOrganization($subObject->getSubresourceOf()->first()->getObjectEntity()->getOrganization());
                        $subObject->setApplication($subObject->getSubresourceOf()->first()->getObjectEntity()->getApplication());
                    } else {
                        $subObject->setOrganization($this->session->get('activeOrganization'));
                        $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
                        $subObject->setApplication(!empty($application) ? $application : null);
                    }
                    // todo: add functionService somehow
//                    $subObject = $this->functionService->handleFunction($subObject, $subObject->getEntity()->getFunction(), [
//                        'method'           => $this->request->getMethod(),
//                        'uri'              => $subObject->getUri(),
//                        'organizationType' => array_key_exists('type', $object) ? $object['type'] : null,
//                        'userGroupName'    => array_key_exists('name', $object) ? $object['name'] : null,
//                    ]);

                    // object toevoegen
                    $saveSubObjects->add($subObject);
                }

                // todo: remove objects on put
//                // If we are doing a put, we want to actually clear (or remove) objects connected to this valueObject we no longer need
//                if ($this->request->getMethod() == 'PUT' && !$objectEntity->getHasErrors()) {
//                    foreach ($valueObject->getObjects() as $object) {
//                        // If we are not re-adding this object...
//                        if (!$saveSubObjects->contains($object)) {
//                            $this->removeObjectsOnPut[] = [
//                                'valueObject' => $valueObject,
//                                'object'      => $object,
//                            ];
//                        }
//                    }
//                    $valueObject->getObjects()->clear();
//                }
                // Actually add the objects to the valueObject
                foreach ($saveSubObjects as $saveSubObject) {
                    // todo: remove objects so not multiple attributes won't ever have multiple objects
//                    // If we have inversedBy on this attribute
//                    if ($attribute->getInversedBy()) {
//                        $inversedByValue = $saveSubObject->getValueByAttribute($attribute->getInversedBy());
//                        if (!$inversedByValue->getObjects()->contains($objectEntity)) { // $valueObject->getObjectEntity() = $objectEntity
//                            // If inversedBy attribute is not multiple it should only have one object connected to it
//                            if (!$attribute->getInversedBy()->getMultiple() and count($inversedByValue->getObjects()) > 0) {
//                                // Remove old objects
//                                foreach ($inversedByValue->getObjects() as $object) {
//                                    // Clear any objects and there parent relations (subresourceOf) to make sure we only can have one object connected.
//                                    $this->removeObjectsNotMultiple[] = [
//                                        'valueObject' => $inversedByValue,
//                                        'object'      => $object,
//                                    ];
//                                }
//                            }
//                        }
//                    }
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
     * @param Attribute $attribute
     * @param Value $valueObject
     * @param $value
     *
     * @return ObjectEntity
     * @throws Exception
     */
    private function saveAttributeType(ObjectEntity $objectEntity, Attribute $attribute, Value $valueObject, $value): ObjectEntity
    {
        switch ($attribute->getType()) {
            case 'object':
                // Check for cascading (should already be done by validaterService...
                if (!$attribute->getCascade() && !is_string($value)) {
                    break;
                }

                // If we are not cascading and value is a string, then value should be an id.
                if (is_string($value)) {
                    if (Uuid::isValid($value) == false) {
                        // We should also allow commonground Uri's like: https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
                        // TODO: support /$attribute->getObject()->getEndpoint()/uuid?
                        if ($value == $attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($value)) {
                            $value = $this->commonGroundService->getUuidFromUrl($value);
                        } else {
//                            var_dump('The given value ('.$value.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).');
                            break;
                        }
                    }

                    // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
                    // todo make one sql query for finding an ObjectEntity by id or externalId
                    if (!$subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'id' => $value])) {
                        if (!$subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'externalId' => $value])) {
                            // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside the gateway for an existing object.
                            // todo: add convertToGatewayService somehow
//                            $subObject = $this->convertToGatewayService->convertToGatewayObject($attribute->getObject(), null, $value, $valueObject, $objectEntity);
                            if (!$subObject) {
                                // todo: throw error?
//                                var_dump('Could not find an object with id '.$value.' of type '.$attribute->getObject()->getName());
                                break;
                            }
                        }
                    }

                    // todo: remove objects so not multiple attributes won't ever have multiple objects
//                    // If we have inversedBy on this attribute
//                    if ($attribute->getInversedBy()) {
//                        $inversedByValue = $subObject->getValueByAttribute($attribute->getInversedBy());
//                        if (!$inversedByValue->getObjects()->contains($objectEntity)) { // $valueObject->getObjectEntity() = $objectEntity
//                            // If inversedBy attribute is not multiple it should only have one object connected to it
//                            if (!$attribute->getInversedBy()->getMultiple() and count($inversedByValue->getObjects()) > 0) {
//                                // Remove old objects
//                                foreach ($inversedByValue->getObjects() as $object) {
//                                    // Clear any objects and there parent relations (subresourceOf) to make sure we only can have one object connected.
//                                    $this->removeObjectsNotMultiple[] = [
//                                        'valueObject' => $inversedByValue,
//                                        'object'      => $object,
//                                    ];
//                                }
//                            }
//                        }
//                    }

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
                    if ($attribute->getObject()->getFunction() === 'organization') {
                        $this->entityManager->persist($subObject);
                        // todo: add functionService somehow
//                        $subObject = $this->functionService->createOrganization($subObject, $this->createUri($subObject), array_key_exists('type', $value) ? $value['type'] : $subObject->getValueByAttribute($subObject->getEntity()->getAttributeByName('type'))->getValue());
                    } else {
                        $subObject->setOrganization($this->session->get('activeOrganization'));
                    }
                    $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
                    $subObject->setApplication(!empty($application) ? $application : null);
                } else {
                    // Put...
                    $subObject = $valueObject->getValue();
                }
                $subObject = $this->saveObject($subObject, $value);
                $this->handleOwner($subObject); // Do this after all CheckAuthorization function calls
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
     * @TODO
     *
     * @param ObjectEntity $objectEntity
     * @param Value $valueObject
     * @param array $fileArray
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
     * @param $mime
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
     * @param array       $file
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
            'name'      => array_key_exists('filename', $file) ? $file['filename'] : null,
            // Get extension from filename, and else from the mime_type
            'extension' => array_key_exists('filename', $file) ? pathinfo($file['filename'], PATHINFO_EXTENSION) : $this->mimeToExt($mime_type),
            'mimeType'  => $mime_type,
            'size'      => $this->getBase64Size($file['base64']),
            'base64'    => $file['base64']
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
     * @param string|null $key
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
     */
    public function createUri(ObjectEntity $objectEntity): string
    {
        if ($objectEntity->getEntity()->getGateway() && $objectEntity->getEntity()->getGateway()->getLocation() && $objectEntity->getExternalId()) {
            return $objectEntity->getEntity()->getGateway()->getLocation().'/'.$objectEntity->getEntity()->getEndpoint().'/'.$objectEntity->getExternalId();
        }

        $uri = $_SERVER['HTTP_HOST'] === 'localhost' ? 'http://localhost' : 'https://'.$_SERVER['HTTP_HOST'];

        if ($objectEntity->getEntity()->getRoute()) {
            return $uri.$objectEntity->getEntity()->getRoute().'/'.$objectEntity->getId();
        }

        return $uri.'/admin/object_entities/'.$objectEntity->getId();
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param string       $method
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
}
