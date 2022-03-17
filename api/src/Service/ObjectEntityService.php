<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ObjectEntityService
{
    private TokenStorageInterface $tokenStorage;
    private CommonGroundService $commonGroundService;

    public function __construct(TokenStorageInterface $tokenStorage, CommonGroundService $commonGroundService)
    {
        $this->tokenStorage = $tokenStorage;
        $this->commonGroundService = $commonGroundService;
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
}
