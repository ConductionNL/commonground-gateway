<?php

namespace App\Service;

use App\Entity\Application;
use App\Exception\GatewayException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @Author Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ApplicationService
{
    /**
     * @var Request|null The current request.
     */
    private ?Request $request;

    /**
     * @var SessionInterface The current session.
     */
    private SessionInterface $session;

    /**
     * @param RequestStack $requestStack
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        RequestStack                            $requestStack,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->session = $requestStack->getSession();
    }

    /**
     * A function that finds an application.
     *
     * @throws GatewayException
     */
    public function getApplication(): Application
    {
        // If application is already in the session
        if ($this->session->has('application')) {
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            if ($application !== null) {
                return $application;
            }
        }

        // If an api-key is used for authentication we already know which application is used
        if ($this->session->has('apiKeyApplication')) {
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('apiKeyApplication')]);
            if ($application !== null) {
                $this->session->set('application', $application->getId()->toString());
                return $application;
            }
        }

        // Find application using the publicKey
        $public = ($this->request->headers->get('public') ?? $this->request->query->get('public'));
        if (empty($public) === false) {
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['public' => $public]);
            if ($application !== null) {
                $this->session->set('application', $application->getId()->toString());
                return $application;
            }
        }

        // Find application using the host/domain
        $host = ($this->request->headers->get('host') ?? $this->request->query->get('host'));
        if (empty($host) === false) {
            $applications = $this->entityManager->getRepository('App:Application')->findByDomain($host);
            if (count($applications) > 0) {
                $this->session->set('application', $applications[0]->getId()->toString());

                return $applications[0];
            }
        }

        // No application was found
        $this->session->set('application', null);

        // Set message
        $public && $message = 'No application found with public '.$public;
        $host && $message = 'No application found with host '.$host;
        !$public && !$host && $message = 'No host or application given';

        // Set data
        $public && $data = ['public' => $public];
        $host && $data = ['host' => $host];

        throw new GatewayException($message ?? null, null, null, [
            'data' => $data ?? null, 'path' => $public ?? $host ?? 'Header', 'responseType' => Response::HTTP_FORBIDDEN,
        ]);
    }
}
