<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ObjectEntityService
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * A function we want to call when doing a post or put, to set the owner of an ObjectEntity, if it hasn't one already.
     *
     * @param ObjectEntity $result
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
