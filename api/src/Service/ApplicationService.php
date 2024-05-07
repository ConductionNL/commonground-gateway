<?php

namespace App\Service;

use App\Entity\Application;
use App\Exception\GatewayException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
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
     * A function that finds an application.
     *
     * @throws GatewayException
     */
    public function getApplication(): Application
    {
        // If application is already in the session
        if (empty($this->session) === false && $this->session->has('application') === true) {
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            if ($application !== null) {
                return $application;
            }
        }

        // Find application using the publicKey
        $public = $this->getHeaderOrQuery('public');
        if (empty($public) === false) {
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['public' => $public]);
            if ($application !== null) {
                $this->session->set('application', $application->getId()->toString());
                return $application;
            }
        }

        // Find application using the host/domain
        $host = $this->getHeaderOrQuery('host');
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


    /**
     * Tries to get a given key from the headers of the current request, else from the query params of the current request.
     *
     * @param string $key The key to get.
     *
     * @return bool|float|int|string|InputBag|null The value of the header or query or null if none was found.
     */
    private function getHeaderOrQuery(string $key)
    {
        if (empty($this->request) === true) {
            return null;
        }

        if (empty($this->request->headers) === false && $this->request->headers->has($key) === true) {
            return $this->request->headers->get($key);
        }

        if (empty($this->request->query) === false && $this->request->query->has($key) === true) {
            return $this->request->query->get($key);
        }

        return null;
    }
}
