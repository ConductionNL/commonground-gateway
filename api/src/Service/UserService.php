<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use App\Entity\Organization;
use App\Entity\Person;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\SamlBundle\Security\User\AuthenticationUser;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UserService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;
    private ResponseService $responseService;
    private SerializerInterface $serializer;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, ResponseService $responseService, SerializerInterface $serializer)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
        $this->responseService = $responseService;
        $this->serializer = $serializer;
    }

    public function getObject(string $uri, $fields = null): array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['uri' => $uri]);
        if ($object instanceof ObjectEntity) {
            return $this->responseService->renderResult($object, $fields);
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
        if ($user->getPerson() && $person = $this->getObject($user->getPerson())) {
            return $person;
        } elseif ($user->getPerson()) {
            try {
                if ($this->commonGroundService->getComponent('cc')) {
                    $person = $this->commonGroundService->getResource($user->getPerson());
                } else {
                    $id = substr($user->getPerson(), strrpos($user->getPerson(), '/') + 1);
                    $person = $this->entityManager->getRepository("App:Person")->find($id);
                    if (empty($person) || !$person instanceof Person ) {
                        throw new Exception();
                    } else {
                        $serialized = $this->serializer->serialize($person, 'jsonld');
                        $person = json_decode($serialized, true);
                    }
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
            if (!($organization = $this->getObject($user->getOrganization(), $organizationFields))) {
                try {
                    if ($this->commonGroundService->getComponent('cc')) {
                        $organization = $this->commonGroundService->getResource($user->getOrganization());
                    } else {
                        $id = substr($user->getOrganization(), strrpos($user->getOrganization(), '/') + 1);
                        $organization = $this->entityManager->getRepository("App:Organization")->find($id);
                        if (empty($organization) || !$organization instanceof Organization ) {
                            throw new Exception();
                        } else {
                            $serialized = $this->serializer->serialize($organization, 'jsonld');
                            $organization = json_decode($serialized, true);
                        }
                    }
                } catch (Exception $exception) {
                    return [];
                }
            }
        }

        return $organization;
    }
}
