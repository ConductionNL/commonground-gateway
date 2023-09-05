<?php

namespace App\Security;

use App\Security\User\AuthenticationUser;
use App\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;

class OIDCAuthenticator extends AbstractAuthenticator
{
    private AuthenticationService $authenticationService;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;
    private ParameterBagInterface $parameterBag;

    /**
     * @var LoggerInterface The logger for this service.
     */
    private LoggerInterface $logger;

    /**
     * @param AuthenticationService $authenticationService
     * @param SessionInterface $session
     * @param EntityManagerInterface $entityManager
     * @param ParameterBagInterface $parameterBag
     * @param LoggerInterface $callLogger
     */
    public function __construct(
        AuthenticationService $authenticationService,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag,
        LoggerInterface $callLogger
    )
    {
        $this->authenticationService = $authenticationService;
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->parameterBag = $parameterBag;
        $this->logger = $callLogger;
    }

    public function supports(Request $request): ?bool
    {
        return 'app_user_authenticate' === $request->attributes->get('_route') &&
            $request->isMethod('GET') && $request->query->has('code');
    }

    private function prefixGroups(array $groups): array
    {
        $newGroups = [];
        foreach ($groups as $group) {
            $newGroups[] = 'ROLE_scope.'.$group;
        }

        return $newGroups;
    }

    public function authenticate(Request $request): PassportInterface
    {
        $code = $request->query->get('code');
        $method = $request->attributes->get('method');
        $identifier = $request->attributes->get('identifier');

        $accessToken = $this->authenticationService->authenticate($method, $identifier, $code);
        $result = json_decode(base64_decode(explode('.', $accessToken['access_token'])[1]), true);

        $this->logger->notice('Received result from OIDC connector', ['authResult' => $result]);

        // Make sure groups is always an array, even if there are no groups.
        if(is_array($result['groups']) === false && $result['groups'] !== null) {
            $result['groups'] = [$result['groups']]
        } else if (is_array($result['groups']) === false) {
            $result['groups'] = [];
        }

        // Set default organization in session for multitenancy (see how this is done in other Authenticators, this can be different for each one!)
        $defaultOrganization = $this->getDefaultOrganization();
        $organizations = [$defaultOrganization, 'localhostOrganization'];
        $parentOrganizations[] = 'localhostOrganization';
        $this->session->set('organizations', $organizations);
        $this->session->set('parentOrganizations', $parentOrganizations);
        $this->session->set('activeOrganization', $defaultOrganization);
        if (isset($accessToken['refresh_token'])) {
            $this->session->set('refresh_token', $accessToken['refresh_token']);
        }

        return new Passport(
            new UserBadge($result['email'], function ($userIdentifier) use ($result) {
                return new AuthenticationUser(
                    $userIdentifier,
                    $result['email'],
                    '',
                    $result['givenName'] ?? '',
                    $result['familyName'] ?? '',
                    $result['name'] ?? '',
                    null,
                    array_merge($this->prefixGroups($result['groups']) ?? [], ['ROLE_USER']),
                    $result['email']
                );
            }),
            new CustomCredentials(
                function ($credentials, $user) {
                    return true;
                },
                ['method' => $method, 'identifier' => $identifier, 'code' => $code, 'service' => $this->authenticationService]
            )
        );
    }

    private function getDefaultOrganization(): string
    {
        // Find application->organization
        if ($this->session->get('application')) {
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            if (!empty($application) && $application->getOrganization()) {
                return $application->getOrganization();
            }
        }
        // Else find and return 'the' default organization
        $organization = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => 'a1c8e0b6-2f78-480d-a9fb-9792142f4761']);
        if (!empty($organization) && $organization->getOrganization()) {
            return $organization->getOrganization();
        }

        return 'http://api/admin/organizations/a1c8e0b6-2f78-480d-a9fb-9792142f4761';
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->session->get('backUrl', $this->parameterBag->get('defaultBackUrl')) ?? $request->headers->get('referer') ?? $request->getSchemeAndHttpHost());
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([], Response::HTTP_UNAUTHORIZED);
    }
}
