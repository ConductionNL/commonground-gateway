<?php

namespace App\Service;

use App\Entity\Application;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
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
    public function getApplication()
    {
        if ($application = $this->session->get('application')) {
            var_dump(1);
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            if (!empty($application)) {
                var_dump(2); 
                return $application;
            }
        } elseif ($this->session->get('apiKeyApplication')) {
            var_dump(3);
            // If an api-key is used for authentication we already know which application is used
            return $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('apiKeyApplication')]);
        }

        // get publickey
        $public = ($this->request->headers->get('public') ?? $this->request->query->get('public'));
        var_dump($public);

        // get host/domain
        $host = ($this->request->headers->get('host') ?? $this->request->query->get('host'));
        var_dump($host);

        $application = $this->entityManager->getRepository('App:Application')->findOneBy(['public' => $public]) && $this->session->set('application', $application->getId()->toString());
        if (!isset($application)) {
            // @todo Create and use query in ApplicationRepository
            $applications = $this->entityManager->getRepository('App:Application')->findAll();
            foreach ($applications as $app) {
                var_dump($app->getName());
                var_dump($app->getDomains());
                $app->getDomains() !== null && in_array($host, $app->getDomains()) && $application = $app;
                if (isset($application)) {
                    var_dump(35);
                    break;
                }
            }
        }
        var_dump(4);
        if (!$application) {
            if (str_contains($host, 'localhost')) {
                var_dump(5);
                $application = $this->createApplication('localhost', [$host], Uuid::uuid4()->toString(), Uuid::uuid4()->toString());
            } else {
                var_dump(6);
                $this->session->set('application', null);

                // Set message
                $public && $message = 'No application found with public '.$public;
                $host && $message = 'No application found with host '.$host;
                !$public && !$host && $message = 'No host or application given';

                // Set data
                $public && $data = ['public' => $public];
                $host && $data = ['host' => $host];

                $result = [
                    'message' => $message,
                    'type'    => 'Forbidden',
                    'path'    => $public ?? $host ?? 'Header',
                    'data'    => $data ?? null,
                ];
                // todo: maybe just throw a gatewayException?
//            throw new GatewayException('No application found with domain '.$host, null, null, ['data' => ['host' => $host], 'path' => $host, 'responseType' => Response::HTTP_FORBIDDEN]);

                return $result;
            }
        }
        var_dump(7);

        $this->session->set('application', $application->getId()->toString());

        return $application;
    }

    /**
     * A function that creates a application.
     *
     * @todo expand with more arguments/attributes.
     *
     * @return Application
     */
    public function createApplication(string $name, array $domains, string $public, string $secret): Application
    {
        $application = new Application();
        $application->setName($name);
        $application->setDescription($name.' application');
        $application->setDomains($domains);
        $application->setPublic($public);
        $application->setSecret($secret);
        $application->setOrganization($name.'Organization');
        $this->entityManager->persist($application);
        $this->entityManager->flush();

        $this->session->set('application', $application->getId()->toString());

        return $application;
    }
}
