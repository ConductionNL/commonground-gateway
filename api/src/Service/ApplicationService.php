<?php

namespace App\Service;

use App\Exception\GatewayException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
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
     * A function that finds an application or creates one.
     *
     * @throws GatewayException
     */
    public function getApplication()
    {
        if ($application = $this->session->get('application')) {
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            if (!empty($application)) {
                return $application;
            }
        } elseif ($this->session->get('apiKeyApplication')) {
            // If an api-key is used for authentication we already know which application is used
            return $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('apiKeyApplication')]);
        }

        // get publickey
        $public = ($this->request->headers->get('public') ?? $this->request->query->get('public'));

        // get host/domain
//        $host = ($this->request->headers->get('host') ?? $this->request->query->get('host'));
        $host = 'api.buren.commonground.nu';
        ($application = $this->entityManager->getRepository('App:Application')->findOneBy(['public' => $public])) && !empty($application) && $this->session->set('application', $application->getId()->toString());

        if (!$application) {
            // @todo Create and use query in ApplicationRepository

            $criteria = new Criteria();

           // $application = $this->entityManager->getRepository('App:Application')->findAll()->
            $applications = $this->entityManager->getRepository('App:Application')->findAll();
//            foreach ($applications as $app) {
//                $app->getDomains() !== null && in_array($host, $app->getDomains()) && $application = $app;
//                if (isset($application)) {
//                    break;
//                }
//            }
            if(count($applications > 0)) {
                $application = $applications->first();
            }
        }

        if (!$application) {
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

        $this->session->set('application', $application->getId()->toString());

        return $application;
    }
}
