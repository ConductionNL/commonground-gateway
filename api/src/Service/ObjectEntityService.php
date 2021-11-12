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

    public function handleOwner(ObjectEntity $result): ObjectEntity
    {
        $user = $this->tokenStorage->getToken()->getUser();

        if (!is_string($user)) {
            $result->setOwner($user->getUserIdentifier());
        }

        return $result;
    }

    public function checkOwner(ObjectEntity $result): bool
    {
        $user = $this->tokenStorage->getToken()->getUser();

        if (!is_string($user) && $result->getOwner() === $user->getUserIdentifier()) {
            return true;
        }

        return false;
    }
}
