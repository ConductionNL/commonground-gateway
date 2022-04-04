<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\Handler;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Promise\Utils;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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

    public function __construct(
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack,
        AuthorizationService $authorizationService,
        ApplicationService $applicationService,
        ValidaterService $validaterService,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        CommonGroundService $commonGroundService,
        ResponseService $responseService
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
    }

    /**
     * Add services for using the handleObject function todo: temp fix untill we no longer use these services here.
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

    public function checkOwner(ObjectEntity $result): bool
    {
        // TODO: what if somehow the owner of this ObjectEntity is null? because of ConvertToGateway ObjectEntities for example?
        $user = $this->tokenStorage->getToken()->getUser();

        if (!is_string($user) && $result->getOwner() === $user->getUserIdentifier()) {
            return true;
        }

        return false;
    }

    public function getObjectByUri(string $uri, ?array $fields = null): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['uri' => $uri]);
        if ($object instanceof ObjectEntity) {
            return $this->responseService->renderResult($object, $fields, true);
        }

        return [];
    }

    public function getObject(Entity $entity, string $id, ?array $fields = null): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'id' => $id]);
        if ($object instanceof ObjectEntity) {
            return $this->responseService->renderResult($object, $fields, true);
        }

        return [];
    }

    public function getPersonObject(string $id, ?array $fields = null): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['function' => 'person']);
        if ($entity instanceof Entity) {
            return $this->getObject($entity, $id, $fields);
        }

        return [];
    }

    public function getOrganizationObject(string $id, ?array $fields = null): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['function' => 'organization']);
        if ($entity instanceof Entity) {
            return $this->getObject($entity, $id, $fields);
        }

        return [];
    }

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
     * A function to handle calls to eav.
     *
     * @param Handler $handler
     * @param array   $data    Data to be set into the eav
     * @param string  $method  Method from request if there is a request
     *
     * @return array $data
     */
    public function handleObject(Handler $handler, array $data = null, string $method = null): array
    {
        // check application
        $application = $this->applicationService->getApplication();

        // If type is array application is a error
        if (gettype($application) === 'array') {
            // todo: maybe just throw a gatewayException? see getApplication() function^
            return $application;
        }

        $owner = $this->tokenStorage->getToken()->getUser();

        // @todo check rights/auth (what to give as scopes?)
        // $this->authorizationService->checkAuthorization();

        $entity = $handler->getEntity();
        $this->session->set('entity', $entity->getId()->toString());
        if ($entity->getGateway()) {
            $this->session->set('source', $entity->getGateway()->getId()->toString());
        }

        // Check ID
        array_key_exists('id', ($routeParameters = $this->request->attributes->get('_route_params'))) && $id = $routeParameters['id'];

        if (isset($id) || $method == 'POST') {
            // todo: re-used old code for getting an objectEntity
            $object = $this->eavService->getObject($this->request->attributes->get('id'), $method, $entity);
            if ($object instanceof ObjectEntity) {
                $this->session->set('object', $object->getId()->toString());
            }
        }

        // throw error if get/put/patch/delete and no id
        // in_array($method, ['GET', 'PUT', 'PATCH', 'DELETE']) && //throw error

        switch ($method) {
      case 'GET':
        // get object

        // if id get single object

        //todo: -start- old code...
        //TODO: old code for getting an ObjectEntity

        // Lets allow for filtering specific fields
        $fields = $this->eavService->getRequestFields($this->request);
        if (isset($object)) {
            if ($object instanceof ObjectEntity) {
                $data = $this->eavService->handleGet($object, $fields);
                if ($object->getHasErrors()) {
                    $data['validationServiceErrors']['Warning'] = 'There are errors, this ObjectEntity might contain corrupted data, you might want to delete it!';
                    $data['validationServiceErrors']['Errors'] = $object->getAllErrors();
                }
            } else {
                $data['error'] = $object;
            }
            //todo: -end- old code...
        } else {
            if ($parameters = $this->session->get('parameters')) {
                if (array_key_exists('path', $parameters)) {
                    foreach ($parameters['path'] as $key => $part) {
                        if ($key[0] === '{' && $key[strlen($key) - 1] === '}' && $part !== null) {
                            $key = substr($key, 1, -1);
                            $filters[$key] = $part;
                        } else {
                            //todo
                        }
                    }
                }
            }
            //                    var_dump($filters);
            $data = $this->eavService->handleSearch($entity->getName(), $this->request, $fields, false, $filters ?? []);

            if ($this->session->get('endpoint')) {
                $endpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['id' => $this->session->get('endpoint')]);
                if ($endpoint->getOperationType() === 'item' && array_key_exists('results', $data) && count($data['results']) == 1) { // todo: $data['total'] == 1
                    $data = $data['results'][0];
                    if (isset($data['id']) && Uuid::isValid($data['id'])) {
                        $this->session->set('object', $data['id']);
                    }
                } elseif ($endpoint->getOperationType() === 'item') {
                    throw new GatewayException('No object found with these filters', null, null, ['data' => $filters ?? null, 'path' => $entity->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
                }
            }
        }

        break;
      case 'POST':
        // validate
        if ($validationErrors = $this->validaterService->validateData($data, $entity, $method)) {
            break;
        }

        // create object

        // set owner and application

        // set @id

        // @todo: -start- old code...
        // @TODO: old code for creating or updating an ObjectEntity

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
        $this->entityManager->persist($object);
        $this->entityManager->flush();
        $data['id'] = $object->getId()->toString();
        if ($object->getHasErrors()) {
            $data['validationServiceErrors']['Warning'] = 'There are errors, an ObjectEntity with corrupted data was added, you might want to delete it!';
            $data['validationServiceErrors']['Errors'] = $object->getAllErrors();
        }

        //todo: -end- old code...

        break;
      case 'PUT':
      case 'PATCH':
        // get object

        // validate
        if ($validationErrors = $this->validaterService->validateData($data, $entity, $method)) {
            break;
        }
        // put object

        // @todo: -start- old code...
        // @TODO: old code for creating or updating an ObjectEntity

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
        $this->entityManager->persist($object);
        $this->entityManager->flush();
        $data['id'] = $object->getId()->toString();
        if ($object->getHasErrors()) {
            $data['validationServiceErrors']['Warning'] = 'There are errors, an ObjectEntity with corrupted data was added, you might want to delete it!';
            $data['validationServiceErrors']['Errors'] = $object->getAllErrors();
        }

        //todo: -end- old code...

        break;
      case 'DELETE':
        // get object

        // delete object
        break;
      default:
        // throw error
    }

        if (isset($validationErrors)) {
            throw new GatewayException('Validation errors', null, null, ['data' => $validationErrors, 'path' => $entity->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        // use events

        return $data;
    }
}
