<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


class ApplicationService
{

    public function __construct(
        RequestStack $requestStack,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->entityManager = $entityManager;
        $this->session = $session;
    }

    /**
     * A function that finds a application or creates one.
     */
    public function checkApplication()
    {
        $host = $this->request->headers->get('host');

        $applications = $this->entityManager->getRepository('App:Application')->findAll();
        $applications = array_values(array_filter($applications, function (Application $application) use ($host) {
            return in_array($host, $application->getDomains());
        }));
        if (count($applications) > 0) {
            $this->session->set('application', $applications[0]);
        } else {
            //            var_dump('no application found');
            if ($host == 'localhost') {
                $localhostApplication = new Application();
                $localhostApplication->setName('localhost');
                $localhostApplication->setDescription('localhost application');
                $localhostApplication->setDomains(['localhost']);
                $localhostApplication->setPublic('');
                $localhostApplication->setSecret('');
                $localhostApplication->setOrganization('localhostOrganization');
                $this->entityManager->persist($localhostApplication);
                $this->entityManager->flush();
                $this->session->set('application', $localhostApplication);
                //                var_dump('Created Localhost Application');
            } else {
                $this->session->set('application', null);
                // $responseType = Response::HTTP_FORBIDDEN;
                // $result = [
                //     'message' => 'No application found with domain ' . $host,
                //     'type'    => 'Forbidden',
                //     'path'    => $host,
                //     'data'    => ['host' => $host],
                // ];

                // @todo throw error
            }
        }
    }
}
