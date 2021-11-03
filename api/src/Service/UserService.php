<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\SamlBundle\Security\User\AuthenticationUser;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserService
{
    private CommonGroundService $commonGroundService;
    private EavService $eavService;
    private EntityManagerInterface $entityManager;

    public function __construct(CommonGroundService $commonGroundService, EavService $eavService, EntityManagerInterface $entityManager)
    {
        $this->commonGroundService = $commonGroundService;
        $this->eavService = $eavService;
        $this->entityManager = $entityManager;
    }

    public function getObject(string $uri, EavService $eavService): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['uri' => $uri]);
        if ($object instanceof ObjectEntity) {
            return $eavService->renderResult($object, null);
        }

        return [];
    }

    private function getUserObjectEntity(string $username, EavService $eavService): array
    {
        // Because inversedBy wil not set the UC->user->person when creating a person with a user in the gateway.
        // We need to do this in order to find the person of this user:
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'users']);

        if ($entity == null) {
            return [];
        }

        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['username' => $username]);
        if (count($objects) == 1) {
            $user = $eavService->renderResult($objects[0], null);
            // This: will be false if a user has no rights to do get on a person object
            if (isset($user['person'])) {
                return $user['person'];
            }
        }

        return [];
    }

    public function getPersonForUser(UserInterface $user): array
    {
        if (!($user instanceof AuthenticationUser)) {
            var_dump(get_class($user));

            return [];
        }
        if ($user->getPerson() && $person = $this->getObject($user->getPerson(), $this->eavService)) {
            return $person;
        } elseif ($user->getPerson()) {
            try {
                $person = $this->commonGroundService->getResource($user->getPerson());
            } catch (ClientException $exception) {
                $person = $this->getUserObjectEntity($user->getUsername(), $this->eavService);
            }
        } else {
            $person = $this->getUserObjectEntity($user->getUsername(), $this->eavService);
        }

        return $person;
    }

    public function getOrganizationForUser(UserInterface $user): array
    {
        if (!($user instanceof AuthenticationUser)) {
            return [];
        }
        if (!$user->getOrganization()) {
            return [];
        } elseif (!($organization = $this->getObject($user->getOrganization(), $this->eavService))) {
            try {
                $organization = $this->commonGroundService->getResource($user->getOrganization());
            } catch (ClientException $exception) {
                return [];
            }
        }

        return $organization;
    }
}
