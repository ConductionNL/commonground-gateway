<?php

namespace App\Security;

use App\Entity\Application;
use App\Security\User\AuthenticationUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;

class CertificateAuthenticator extends AbstractAuthenticator
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    /**
     * @inheritDoc
     */
    public function supports(Request $request): ?bool
    {
        return ($request->server->get('SSL_CLIENT_VERIFY') !== null
            && $request->server->get('SSL_CLIENT_CERT') !== null
            && $request->server->get('SSL_CLIENT_S_DN') !== null)
            || ($request->headers->has('SSL_CLIENT_VERIFY') !== false
            && $request->headers->has('SSL_CLIENT_CERT') !== false
            && $request->headers->has('SSL_CLIENT_SUBJECT_DN') !== false
            && $request->headers->get('SSL_CLIENT_VERIFY') !== ''
            && $request->headers->get('SSL_CLIENT_CERT') !== ''
            && $request->headers->get('SSL_CLIENT_SUBJECT_DN') !== '');
    }//end supports()

    /**
     * Searches an application in the database that contains the certificate.
     *
     *
     * @param string $certificate The certificate to look for.
     *
     * @throws \Doctrine\ORM\NonUniqueResultException Thrown when more then 1 application contains the certificate.
     *
     * @return Application|null The resulting application.
     */
    private function findApplicationByCertificate(string $certificate): ?Application
    {
        $certificate = urldecode($certificate);
        $certificate = str_replace(["\n", "\r", "\t"], '', $certificate, $count);

        $qb = $this->entityManager->getRepository('App:Application')->createQueryBuilder('a');
        $qb->select('a')
            ->where($qb->expr()->like('a.certificates', $qb->expr()->literal("%$certificate%")));

        $application = $qb->getQuery()->disableResultCache()->getOneOrNullResult();

        if ($application instanceof Application === true) {
            return $application;
        }

        throw new AuthenticationException('No application found for certificate');
    }//end findApplicationByCertificate()

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): PassportInterface
    {
        if ($request->server->has('SSL_CLIENT_CERT')) {
            $certificate = $request->server->get('SSL_CLIENT_CERT');
        } elseif ($request->headers->has('SSL_CLIENT_CERT')) {
            $certificate = $request->headers->get('SSL_CLIENT_CERT');
        }

        if (!isset($certificate)) {
            throw new AuthenticationException('No certificate passed.');
        }

        $application = $this->findApplicationByCertificate($certificate);

        $roles = [];
        foreach ($application->getUsers()[0]->getSecurityGroups() as $role) {
            if (strpos($role, 'ROLE_') !== 0) {
                $roles[] = "ROLE_$role";
            }
        }

        $user = [
            'organization' => $application->getOrganization()->getId()->toString(),
            'roles'        => $roles,
        ];

        return new Passport(
            new UserBadge($request->server->has('SSL_CLIENT_S_DN') ? $request->server->get('SSL_CLIENT_S_DN') : $request->headers->get('SSL_CLIENT_SUBJECT_DN'), function ($userIdentifier) use ($user) {
                return new AuthenticationUser(
                    $userIdentifier,
                    $userIdentifier,
                    '',
                    '',
                    '',
                    $userIdentifier,
                    '',
                    $user['roles'],
                    '',
                    'en',
                    $user['organization'],
                    null
                );
            }),
            new CustomCredentials(
                function (array $credentials, UserInterface $user) {
                    return $user->getUserIdentifier() == $credentials['id'];
                },
                ['id' => $request->server->has('SSL_CLIENT_S_DN') ? $request->server->get('SSL_CLIENT_S_DN') : $request->headers->get('SSL_CLIENT_SUBJECT_DN')]
            )
        );
    }//end authenticate()

    /**
     * @inheritDoc
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }//end onAuthenticationSuccess

    /**
     * @inheritDoc
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message'   => strtr($exception->getMessageKey(), $exception->getMessageData()),
            'exception' => $exception->getMessage(),

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }//end onAuthenticationFailure()
}//end class
