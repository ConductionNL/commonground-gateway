<?php

namespace App\Service;

use App\Entity\Authentication;
use App\Entity\Gateway;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthenticationService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
    }

    public function handleAuthenticationUrl(string $method, string $identifier, string $redirectUrl): string
    {
        $authentication = $this->retrieveAuthentication($identifier);

        return $this->buildRedirectUrl($method, $redirectUrl, $authentication);

    }

    public function buildRedirectUrl(string $method, string $redirectUrl, Authentication $authentication): string
    {
        switch ($method) {
            case 'adfs':
                break;
            case 'digid':
                break;
            default:
                throw new BadRequestException('Authentication method not supported');
        }
    }

    public function handleAdfsRedirectUrl(string $redirectUrl, Authentication $authentication): string
    {
        return $authentication->getAuthenticateUrl() . '?clientId=' . $authentication->getClientId();
    }

    public function retrieveAuthentication(string $identifier): Authentication
    {
        $authentications = $this->entityManager->getRepository('App\Entity\Authentication')->findBy(['name' => $identifier]);

        if (count($authentications) == 0 || !$authentications[0] instanceof Authentication) {
            throw new NotFoundHttpException('Unable to find Authentication');
        }

        return $authentications[0];
    }

}
