<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use App\Entity\Handler;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Service\AuthorizationService;
use App\Service\ApplicationService;

class ObjectEntityService
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack,
        AuthorizationService $authorizationService,
        ApplicationService $applicationService,
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->request = $requestStack->getCurrentRequest();
        $this->authorizationService = $authorizationService;
        $this->applicationService = $applicationService;
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
     * A function to handle calls to eav 
     *
     * @param Handler $handler
     * @param array $data Data to be set into the eav
     *
     * @return array $data
     */
    public function handleObject(Handler $handler, array $data = null): array
    {
        // check application
        $this->applicationService->checkApplication();

        // check rights/auth (what to give as scopes?)
        $this->authorizationService->checkAuthorization();

        $entity = $handler->getEntity();
        $method = $this->request->getMethod();

        // Check ID
        array_key_exists('id', ($routeParameters = $this->request->attributes->get('_route_params'))) && $id = $routeParameters['id'];

        // throw error if get/put/patch/delete and no id
        // in_array($method, ['GET', 'PUT', 'PATCH', 'DELETE']) && !isset($id) && //throw error

        switch ($method) {
            case 'GET':
                // get object

                break;
            case 'POST':
                // validate

                // create object
                break;
            case 'PUT':
            case 'PATCH':
                // get object

                // validate

                // put object
                break;
            case 'DELETE':
                // get object

                // delete object
                break;
            default:
                // throw error
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
