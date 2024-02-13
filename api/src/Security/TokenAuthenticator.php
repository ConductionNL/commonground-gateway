<?php

namespace App\Security;

use App\Entity\Authentication;
use App\Entity\SecurityGroup;
use App\Exception\GatewayException;
use App\Security\User\AuthenticationUser;
use App\Service\ApplicationService;
use CommonGateway\CoreBundle\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;

class TokenAuthenticator extends AbstractAuthenticator
{
    /**
     * @var SessionInterface The current session.
     */
    private SessionInterface $session;

    /**
     * @param AuthenticationService $authenticationService The authentication service.
     * @param RequestStack $requestStack The current request stack.
     * @param ApplicationService $applicationService The application service.
     * @param EntityManagerInterface $entityManager The entity manager.
     */
    public function __construct(
        private readonly AuthenticationService  $authenticationService,
        RequestStack                            $requestStack,
        private readonly ApplicationService     $applicationService,
        private readonly EntityManagerInterface $entityManager
    ) {
        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }
    }

    /**
     * @inheritDoc
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization') &&
            str_starts_with($request->headers->get('Authorization'), 'Bearer') === true;
    }

    /**
     * Gets the public key for the application connected to the request, defaults to a general public key.
     *
     * @throws GatewayException
     *
     * @return string Public key for the application or the general public key
     */
    public function getPublicKey(string $token): string
    {
        $tokenArray = explode('.', $token);
        $header = json_decode(base64_decode(array_shift($tokenArray)), true);
        if (isset($header['alg']) === true) {
            $alg = $header['alg'];
        }

        $application = $this->applicationService->getApplication();
        if (isset($alg) === false || $alg === 'RS512') {
            return $application->getPublicKey();
        } elseif ($alg === 'HS256') {
            return $application->getSecret();
        } elseif ($alg === 'RS256') {
            $payload = json_decode(base64_decode(array_shift($tokenArray)), true);
            $issuer = str_replace('127.0.0.1', 'localhost', $payload['iss']);
            $authenticator = $this->entityManager->getRepository('App:Authentication')->findOneBy(['authenticateUrl' => $issuer.'/auth']);
            if ($authenticator instanceof Authentication) {
                $keyUrl = $authenticator->getKeysUrl();
                $client = new Client();
                $response = $client->get($keyUrl);
                $key = $response->getBody()->getContents();

                return $key;
            }
        }

        return '';
    }

    /**
     * Validates the JWT token and throws an error if it is not valid, or has expired.
     *
     * @param string $token The token provided by the user
     *
     * @return array The payload of the token
     * @throws GatewayException
     */
    public function validateToken(string $token): array
    {
        $publicKey = $this->getPublicKey($token);

        try {
            $payload = $this->authenticationService->verifyJWTToken($token, $publicKey);
        } catch (\Exception $exception) {
            throw new AuthenticationException('The provided token is not valid');
        }
        $now = new \DateTime();
        if (isset($payload['iat']) === true && isset($payload['exp']) === false) {
            $iat = new \DateTime();
            $iat->setTimestamp($payload['iat']);
            $exp = $iat->modify('+1 Hour');
            if ($exp->getTimestamp() < $now->getTimestamp()) {
                throw new AuthenticationException('The provided token has expired');
            }
        }
        if (isset($payload['exp']) && $payload['exp'] < $now->getTimestamp()) {
            throw new AuthenticationException('The provided token has expired');
        }

        return $payload;
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
     * @throws GatewayException
     *
     * @return Passport
     */
    public function authenticate(Request $request): Passport
    {
        $token = substr($request->headers->get('Authorization'), strlen('Bearer '));
        $payload = $this->validateToken($token);

        $application = $this->applicationService->getApplication();
        if (!isset($payload['client_id'])) {
            $user = $payload;
        } else {
            $user = $this->authenticationService->serializeUser($application->getUsers()[0], $this->session);
        }

        if (isset($user['roles']) === false && isset($user['groups']) === true) {
            $user['roles'] = [];
            foreach ($user['groups'] as $group) {
                $securityGroup = $this->entityManager->getRepository('App:SecurityGroup')->findOneBy(['name' => $group]);
                if ($securityGroup instanceof SecurityGroup === true) {
                    $user['roles'] = array_merge($securityGroup->getScopes(), $user['roles']);
                }
            }
        }

        if (isset($user['id']) === false && isset($user['email']) === true) {
            $user['id'] = $user['email'];
        }

        return new Passport(
            new UserBadge($user['user']['id'] ?? $user['userId'] ?? $user['id'], function ($userIdentifier) use ($user) {
                return new AuthenticationUser(
                    $userIdentifier,
                    $user['user']['id'] ?? $user['username'] ?? $user['email'],
                    '',
                    $user['user']['givenName'] ?? $user['username'] ?? '',
                    $user['user']['familyName'] ?? $user['username'] ?? '',
                    $user['username'] ?? $user['email'],
                    '',
                    $this->prefixRoles($user['roles']),
                    $user['username'] ?? $user['email'],
                    $user['locale'] ?? 'en',
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
