<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Organization;
use App\Entity\Person;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\SamlBundle\Security\User\AuthenticationUser;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UserService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;
    private ResponseService $responseService;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, ResponseService $responseService)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
        $this->responseService = $responseService;
    }

    public function getObjectByUri(string $uri, ?array $fields = null): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['uri' => $uri]);
        if ($object instanceof ObjectEntity) {
            return $this->responseService->renderResult($object, $fields);
        }

        return [];
    }

    public function getObject(Entity $entity, string $id, ?array $fields = null): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'id' => $id]);
        if ($object instanceof ObjectEntity) {
            return $this->responseService->renderResult($object, $fields);
        }

        return [];
    }

    public function getPersonObject(string $id, ?array $fields = null): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['function'=>'person']);
        if ($entity instanceof Entity) {
            return $this->getObject($entity, $id, $fields);
        }

        return [];
    }

    public function getOrganizationObject(string $id, ?array $fields = null): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['function'=>'organization']);
        if ($entity instanceof Entity) {
            return $this->getObject($entity, $id, $fields);
        }

        return [];
    }

    private function getUserObjectEntity(string $username): array
    {
        // Because inversedBy wil not set the UC->user->person when creating a person with a user in the gateway.
        // We need to do this in order to find the person of this user:
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'users']);

        if ($entity == null) {
            return [];
        }

        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['username' => $username]);
        if (count($objects) == 1) {
            $user = $this->responseService->renderResult($objects[0], null);
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
        if ($user->getPerson() && $person = $this->getObjectByUri($user->getPerson())) {
            return $person;
        } elseif ($user->getPerson()) {
            try {
                $id = substr($user->getPerson(), strrpos($user->getPerson(), '/') + 1);
                $person = $this->getPersonObject($id);

                if (empty($person) && $this->commonGroundService->getComponent('cc')) {
                    $person = $this->commonGroundService->getResource($user->getPerson());
                } else {
                    throw new Exception();
                }
            } catch (Exception $exception) {
                $person = $this->getUserObjectEntity($user->getUsername());
            }
        } else {
            $person = $this->getUserObjectEntity($user->getUsername());
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
        } else {
            $organizationFields = [
                'name'               => true, 'type' => true, 'addresses' => true, 'emails' => true, 'telephones' => true,
                'parentOrganization' => [
                    'name' => true, 'type' => true, 'addresses' => true, 'emails' => true, 'telephones' => true,
                ],
            ];
            if (!($organization = $this->getObjectByUri($user->getOrganization(), $organizationFields))) {
                try {
                    $id = substr($user->getOrganization(), strrpos($user->getOrganization(), '/') + 1);
                    $organization = $this->getOrganizationObject($id);

                    if (empty($organization) && $this->commonGroundService->getComponent('cc')) {
                        $organization = $this->commonGroundService->getResource($user->getOrganization());
                    } else {
                        throw new Exception();
                    }
                } catch (Exception $exception) {
                    return [];
                }
            }
        }

        return $organization;
    }
}
