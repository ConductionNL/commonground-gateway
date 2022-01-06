<?php

namespace App\Service;

use App\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

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
    public function getApplication(): array|Application
    {
        if ($application = $this->session->get('application')) {
            return $application;
        }

        // get publickey
        $public = ($this->request->headers->get('public') ?? $this->request->query->get('public'));

        // get host/domain
        $host = ($this->request->headers->get('host') ??  $this->request->query->get('host'));

        $application = $this->entityManager->getRepository('App:Application')->findOneBy(['public' => $public]) && $this->session->set('application', $application);
        if (!$application) {
            // @todo Create and use query in ApplicationRepository
            $applications = $this->entityManager->getRepository('App:Application')->findAll();
            $applications = array_values(array_filter($applications, function (Application $application) use ($host) {
                return in_array($host, $application->getDomains());
            }));
            count($applications) > 0 && $application = $applications[0];
        }

        if (!$application && $host == 'localhost') {
            $application = $this->createApplication('localhost', ['localhost'], Uuid::uuid4()->toString(), Uuid::uuid4()->toString());
        } else {
            $this->session->set('application', null);

            // Set message
            $public && $message = 'No application found with public ' . $public;
            $host && $message = 'No application found with host ' . $host;
            !$public && !$host && $message = 'No host or application given';

            // Set data
            $public && $data = ['public' => $public];
            $host && $data = ['host' => $host];

            $result = [
                'message' => $message,
                'type'    => 'Forbidden',
                'path'    => $public ?? $host ?? 'Header',
                'data'    => $data ?? null
            ];

            return $result;
        }

        $this->session->set('application', $application);
        return $application;
    }

    /**
     * A function that creates a application 
     * @todo expand with more arguments/attributes.
     * 
     * @return Application
     */
    public function createApplication(string $name, array $domains, string $public, string $secret): Application
    {
        $application = new Application();
        $application->setName($name);
        $application->setDescription($name . ' application');
        $application->setDomains($domains);
        $application->setPublic($public);
        $application->setSecret($secret);
        $application->setOrganization($name . 'Organization');
        $this->entityManager->persist($application);
        $this->entityManager->flush();

        return $application;
    }
}
