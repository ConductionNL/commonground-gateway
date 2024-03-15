<?php

// src/Controller/LoginController.php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class LoginController.
 *
 * Authors: Gino Kok, Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
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

    /**
     * @Route("/me")
     * @Route("api/users/me", methods={"get"})
     */
    public function MeAction(Request $request)
    {
        if ($this->getUser()) {
            $result = [
                'id'         => $this->getUser()->getUserIdentifier(),
                'username'   => $this->getUser()->getUsername(),
                'roles'      => $this->getUser()->getRoles(),
                'first_name' => $this->getUser()->getFirstName(),
                'last_name'  => $this->getUser()->getLastName(),
                'name'       => $this->getUser()->getName(),
                'email'      => $this->getUser()->getEmail(),
                'person'        => $this->getUser()->getPerson(), // Get person ObjectEntity (->Entity with function = person) by id
                'organization'  => $this->getUser()->getOrganization(), // Get organization ObjectEntity (->Entity with function = organization) by id
            ];
            $result = json_encode($result);
        } else {
            $result = json_encode([]);
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
    private function createPersonForUser(): array
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
