<?php

// src/Controller/LoginController.php

namespace App\Controller;

use App\Entity\ObjectEntity;
use App\Service\EavService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class LoginController.
 *
 *
 * @Route("/")
 */
class LoginController extends AbstractController
{
    private CacheInterface $cache;
    private EntityManagerInterface $entityManager;

    public function __construct(CacheInterface $cache, EntityManagerInterface $entityManager)
    {
        $this->cache = $cache;
        $this->entityManager = $entityManager;
    }

    public function getObject(string $uri, EavService $eavService): ?array
    {
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['uri' => $uri]);
        if ($object instanceof ObjectEntity) {
            return $eavService->renderResult($object, null);
        }

        return null;
    }

    private function getUserObjectEntity(string $username, EavService $eavService): ?array
    {
        // Because inversedBy wil not set the UC->user->person when creating a person with a user in the gateway.
        // We need to do this in order to find the person of this user:
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name'=>'users']);

        if ($entity == null) {
            return null;
        }

        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['username'=>$username]);
        if (count($objects) == 1) {
            $user = $eavService->renderResult($objects[0], null);
            // This: will be false if a user has no rights to do get on a person object
            if (isset($user['person'])) {
                return $user['person'];
            }
        }

        return null;
    }

    /**
     * @Route("/me")
     * @Route("api/users/me", methods={"get"})
     */
    public function MeAction(Request $request, CommonGroundService $commonGroundService, EavService $eavService)
    {
        if ($this->getUser()) {
            $result = [
                'username'   => $this->getUser()->getUsername(),
                'roles'      => $this->getUser()->getRoles(),
                'first_name' => $this->getUser()->getFirstName(),
                'last_name'  => $this->getUser()->getLastName(),
                'name'       => $this->getUser()->getName(),
                'email'      => $this->getUser()->getEmail(),
                // TODO: if we have no person connected to this user create one? with $this->createPersonForUser()
                'person'     => $this->getUser()->getPerson() ? $this->getObject($this->getUser()->getPerson(), $eavService) ?? $commonGroundService->getResource($this->getUser()->getPerson()) : $this->getUserObjectEntity($this->getUser()->getUsername(), $eavService),
            ];
            if ($this->getUser()->getOrganization()) {
                $result['organization'] = $this->getObject($this->getUser()->getOrganization(), $eavService) ?? $commonGroundService->getResource($this->getUser()->getOrganization());
            }
            $result = json_encode($result);
        } else {
            $result = null;
        }

        return new Response(
            $result,
            Response::HTTP_OK,
            ['content-type' => 'application/json']
        );
    }

    //TODO: ?
    /**
     * Creates a person for a user.
     *
     * @return array
     */
    private function createPersonForUser(CommonGroundService $commonGroundService): array
    {
        $person = [
            'givenName'     => $this->getUser()->getFirstName(),
            'familyName'    => $this->getUser()->getLastName(),
            'emails'        => [
                'name'  => 'email',
                'email' => $this->getUser()->getUsername(),
            ],
        ];

        //TODO: use commongroundService to create person?
        //TODO: update user object to connect person uri to the user?

        return $person;
    }
}
