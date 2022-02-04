<?php

namespace App\Service;

use App\Entity\Handler;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ObjectEntityService
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack,
        AuthorizationService $authorizationService,
        ApplicationService $applicationService,
        ValidaterService $validaterService
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->request = $requestStack->getCurrentRequest();
        $this->authorizationService = $authorizationService;
        $this->applicationService = $applicationService;
        $this->validaterService = $validaterService;
    }

    /**
     * A function we want to call when doing a post or put, to set the owner of an ObjectEntity, if it hasn't one already.
     *
     * @param ObjectEntity $result
     *
     * @return ObjectEntity
     */
    public function handleOwner(ObjectEntity $result): ObjectEntity
    {
        $user = $this->tokenStorage->getToken()->getUser();

        if (!is_string($user) && !$result->getOwner()) {
            $result->setOwner($user->getUserIdentifier());
        }

        return $result;
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
            return $application;
        }

        $owner = $this->tokenStorage->getToken()->getUser();

        // @todo check rights/auth (what to give as scopes?)
        // $this->authorizationService->checkAuthorization();

        $entity = $handler->getEntity();

        // Check ID
        array_key_exists('id', ($routeParameters = $this->request->attributes->get('_route_params'))) && $id = $routeParameters['id'];

        // throw error if get/put/patch/delete and no id
        // in_array($method, ['GET', 'PUT', 'PATCH', 'DELETE']) && //throw error

        switch ($method) {
            case 'GET':
                // get object

                // if id get single object

                break;
            case 'POST':
                // validate
                if ($validationErrors = $this->validaterService->validateData($data, $entity)) {
                    break;
                }

                // create object

                // set owner and application

                // set @id
                break;
            case 'PUT':
            case 'PATCH':
                // get object

                // validate
                if ($validationErrors = $this->validaterService->validateData($data, $entity)) {
                    break;
                }
                // put object
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

    public function checkOwner(ObjectEntity $result): bool
    {
        // TODO: what if somehow the owner of this ObjectEntity is null? because of ConvertToGateway ObjectEntities for example?
        $user = $this->tokenStorage->getToken()->getUser();

        if (!is_string($user) && $result->getOwner() === $user->getUserIdentifier()) {
            return true;
        }

        return false;
    }
}
