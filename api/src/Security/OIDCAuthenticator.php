<?php

namespace App\Security;

use App\Security\User\AuthenticationUser;
use App\Service\AuthenticationService;
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

    public function __construct(AuthenticationService $authenticationService, SessionInterface $session)
    {
        $this->authenticationService = $authenticationService;
        $this->session = $session;
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

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->session->get('backUrl') ?? $request->headers->get('referer') ?? $request->getSchemeAndHttpHost());
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([], Response::HTTP_UNAUTHORIZED);
    }
}
