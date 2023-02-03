<?php

namespace App\Security;

use App\Entity\Application;
use App\Exception\GatewayException;
use App\Security\User\AuthenticationUser;
use App\Service\ApplicationService;
use App\Service\FunctionService;
use Conduction\CommonGroundBundle\Service\AuthenticationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;

class TokenAuthenticator extends \Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator
{
    private CommonGroundService $commonGroundService;
    private ParameterBagInterface $parameterBag;
    private AuthenticationService $authenticationService;
    private SessionInterface $session;
    private FunctionService $functionService;
    private ApplicationService $applicationService;

    public function __construct(
        CommonGroundService $commonGroundService,
        AuthenticationService $authenticationService,
        ParameterBagInterface $parameterBag,
        SessionInterface $session,
        FunctionService $functionService,
        ApplicationService $applicationService
    ) {
        $this->commonGroundService = $commonGroundService;
        $this->parameterBag = $parameterBag;
        $this->authenticationService = $authenticationService;
        $this->session = $session;
        $this->functionService = $functionService;
        $this->applicationService = $applicationService;
    }

    /**
     * @inheritDoc
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization') &&
            strpos($request->headers->get('Authorization'), 'Bearer') === 0;
    }

    /**
     * Gets the public key for the application connected to the request, defaults to a general public key.
     *
     * @throws GatewayException
     *
     * @return string Public key for the application or the general public key
     */
    public function getPublicKey(): string
    {
        $application = $this->applicationService->getApplication();
        $publicKey = $application->getPublicKey();
        //if (!$publicKey) {
        //    $publicKey = $this->parameterBag->get('app_x509_cert');
        //}

        return $publicKey;
    }

    /**
     * Validates the JWT token and throws an error if it is not valid, or has expired.
     *
     * @param string $token The token provided by the user
     *
     * @return array The payload of the token
     */
    public function validateToken(string $token): array
    {
        $publicKey = $this->getPublicKey();

        try {
            $payload = $this->authenticationService->verifyJWTToken($token, $publicKey);
        } catch (\Exception $exception) {
            throw new AuthenticationException('The provided token is not valid');
        }
        $now = new \DateTime();
        if (isset($payload['iat']) && !isset($payload['exp'])) {
            $iat = new \DateTime();
            $iat->setTimestamp($payload['iat']);
            $exp = $iat->modify('+1 Hour');
            if (!isset($payload['exp']) && isset($exp) && $exp->getTimestamp() < $now->getTimestamp()) {
                throw new AuthenticationException('The provided token has expired');
            }
        }
        if (isset($payload['exp']) && $payload['exp'] < $now->getTimestamp()) {
            throw new AuthenticationException('The provided token has expired');
        }

        return $payload;
    }

    /**
     * Get all the child organisations for an organisation.
     *
     * @param array               $organizations
     * @param string              $organization
     * @param CommonGroundService $commonGroundService
     * @param FunctionService     $functionService
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     *
     * @return array
     */
    private function getSubOrganizations(array $organizations, string $organization, CommonGroundService $commonGroundService, FunctionService $functionService): array
    {
        if ($organization = $functionService->getOrganizationFromCache($organization)) {
            if (!empty($organization['subOrganizations']) && count($organization['subOrganizations']) > 0) {
                foreach ($organization['subOrganizations'] as $subOrganization) {
                    if (!in_array($subOrganization['@id'], $organizations)) {
                        $organizations[] = $subOrganization['@id'];
                        $this->getSubOrganizations($organizations, $subOrganization['@id'], $commonGroundService, $functionService);
                    }
                }
            }
        }

        return $organizations;
    }

    /**
     * Get al the parent organizations for an organisation.
     *
     * @param array               $organizations
     * @param string              $organization
     * @param CommonGroundService $commonGroundService
     * @param FunctionService     $functionService
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     *
     * @return array
     */
    private function getParentOrganizations(array $organizations, string $organization, CommonGroundService $commonGroundService, FunctionService $functionService): array
    {
        if ($organization = $functionService->getOrganizationFromCache($organization)) {
            if (array_key_exists('parentOrganization', $organization) && $organization['parentOrganization'] != null
                && !in_array($organization['parentOrganization']['@id'], $organizations)) {
                $organizations[] = $organization['parentOrganization']['@id'];
                $organizations = $this->getParentOrganizations($organizations, $organization['parentOrganization']['@id'], $commonGroundService, $functionService);
            }
        }

        return $organizations;
    }

    /**
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws CacheException
     */
    private function setOrganizations(array $user): void
    {
        $organizations = $user['organizations'] ?? [];
        $parentOrganizations = [];
        foreach ($organizations as $organization) {
            if ($organization === null) {
                continue;
            }
            $organizations = $this->getSubOrganizations($organizations, $organization, $this->commonGroundService, $this->functionService);
            $parentOrganizations = $this->getParentOrganizations($parentOrganizations, $organization, $this->commonGroundService, $this->functionService);
        }
        $organizations[] = 'localhostOrganization';
        $parentOrganizations[] = 'localhostOrganization';
        $this->session->set('organizations', $organizations);
        $this->session->set('parentOrganizations', $parentOrganizations);
        $this->session->set('ActiveOrganization', $user['organization'] ?? $this->applicationService->getApplication()->getOrganization());
    }

    private function prefixRoles(array $roles): array
    {
        foreach ($roles as $key => $value) {
            $roles[$key] = "ROLE_$value";
        }

        return $roles;
    }

    /**
     * @inheritDoc
     *
     * @param Request $request
     *
     * @throws CacheException
     * @throws GatewayException
     * @throws InvalidArgumentException
     *
     * @return PassportInterface
     */
    public function authenticate(Request $request): PassportInterface
    {
        $token = substr($request->headers->get('Authorization'), strlen('Bearer '));
        $payload = $this->validateToken($token);
//        $this->setOrganizations($payload);

        $application = $this->applicationService->getApplication();
        if (!isset($payload['client_id'])) {
            $user = $payload;
        } else {
            $user = $this->commonGroundService->getResource($application->getResource(), [], false);
        }

        return new Passport(
            new UserBadge($user['user']['id'] ?? $user['userId'] ?? $user['id'], function ($userIdentifier) use ($user) {
                return new AuthenticationUser(
                    $userIdentifier,
                    $user['user']['id'] ?? $user['username'],
                    '',
                    $user['user']['givenName'] ?? $user['username'],
                    $user['user']['familyName'] ?? $user['username'],
                    $user['username'],
                    '',
                    $this->prefixRoles($user['roles']),
                    $user['username'],
                    $user['locale'],
                    $user['organization'] ?? null,
                    $user['person'] ?? null
                );
            }),
            new CustomCredentials(
                function (array $credentials, UserInterface $user) {
                    return isset($credentials['userId']) ? $user->getUserIdentifier() == $credentials['userId'] : $user->getUserIdentifier() == $credentials['id'];
                },
                $user
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

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
    }
}
