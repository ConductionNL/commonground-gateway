<?php

// src/Security/TokenAuthenticator.php

/*
 * This authenticator authenticas agains the commonground user component
 *
 */

namespace App\Security;

use App\Service\FunctionService;
use Conduction\CommonGroundBundle\Service\AuthenticationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\SamlBundle\Security\User\AuthenticationUser;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class UserTokenAuthenticator extends AbstractGuardAuthenticator
{
    /**
     * @var FlashBagInterface
     */
    private ParameterBagInterface $parameterBag;
    private CommonGroundService $commonGroundService;
    private AuthenticationService $authenticationService;
    private SessionInterface $session;
    private EntityManagerInterface $em;
    private FunctionService $functionService;
    private EntityManagerInterface $entityManager;

    public function __construct(ParameterBagInterface $parameterBag, CommonGroundService $commonGroundService, SessionInterface $session, EntityManagerInterface $em, FunctionService $functionService, EntityManagerInterface $entityManager)
    {
        $this->parameterBag = $parameterBag;
        $this->commonGroundService = $commonGroundService;
        $this->authenticationService = new AuthenticationService($parameterBag);
        $this->session = $session;
        $this->em = $em;
        $this->functionService = $functionService;
        $this->entityManager = $entityManager;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
        // return $request->headers->has('Authorization') && strpos($request->headers->get('Authorization'), 'Bearer') !== false;
        return $request->headers->has('Authorization');
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     */
    public function getCredentials(Request $request)
    {
        if (strpos($request->headers->get('Authorization'), 'Bearer') !== false) {
            return [
                'token' => substr($request->headers->get('Authorization'), strlen('Bearer ')),
            ];
        } else {
            return [
                'apiKey' => $request->headers->get('Authorization'),
            ];
        }
    }

    /**
     * Get all the child organisations for an organisation.
     *
     * @param array               $organizations
     * @param string              $organization
     * @param CommonGroundService $commonGroundService
     * @param FunctionService     $functionService
     *
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     *
     * @return array
     */
    private function getSubOrganizations(array $organizations, string $organization, CommonGroundService $commonGroundService, FunctionService $functionService): array
    {
        if ($organization = $functionService->getOrganizationFromCache($organization)) {
            if (count($organization['subOrganizations']) > 0) {
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
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     *
     * @return array
     */
    private function getParentOrganizations(array $organizations, string $organization, CommonGroundService $commonGroundService, FunctionService $functionService): array
    {
        if ($organization = $functionService->getOrganizationFromCache($organization)) {
            if (array_key_exists('parentOrganization', $organization) && $organization['parentOrganization'] != null
                && !in_array($organization['parentOrganization']['@id'], $organizations)
                && array_key_exists('parentOrganization', $organization)) {
                $organizations[] = $organization['parentOrganization']['@id'];
                $organizations = $this->getParentOrganizations($organizations, $organization['parentOrganization']['@id'], $commonGroundService, $functionService);
            }
        }

        return $organizations;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if (array_key_exists('token', $credentials)) {
            $publicKey = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'public_key']);

            try {
                $payload = $this->authenticationService->verifyJWTToken($credentials['token'], $publicKey);
            } catch (\Exception $exception) {
                throw new AuthenticationException('The provided token is not valid');
            }

            $user = $this->commonGroundService->getResource(['component' => 'uc', 'type' => 'users', 'id' => $payload['userId']], [], false, false, true, false, false);
            $session = $this->commonGroundService->getResource(['component' => 'uc', 'type' => 'sessions', 'id' => $payload['session']], [], false, false, true, false, false);

            if (!$session || new DateTime($session['expiry']) < new DateTime('now') || !$session['valid']) {
                throw new AuthenticationException('The provided token refers to an invalid session');
            }
        } elseif (array_key_exists('apiKey', $credentials)) {
            $application = $this->em->getRepository('App:Application')->findOneBy(['secret' => $credentials['apiKey']]);

            if (!$application || !$application->getResource()) {
                throw new AuthenticationException('Invalid ApiKey');
            }

            try {
                $user = $this->commonGroundService->getResource($application->getResource());
            } catch (\Exception $exception) {
                throw new AuthenticationException('Invalid User Uri');
            }
            $this->session->set('apiKeyApplication', $application->getId->toString());
        }

        if (!$user) {
            throw new AuthenticationException('The provided token does not match the user it refers to');
        }

        if (!in_array('ROLE_USER', $user['roles'])) {
            $user['roles'][] = 'ROLE_USER';
        }
        foreach ($user['roles'] as $key => $role) {
            if (strpos($role, 'ROLE_') !== 0) {
                $user['roles'][$key] = "ROLE_$role";
            }
        }

        $organizations = [];
        if (isset($user['organization'])) {
            $organizations[] = $user['organization'];
        }
        foreach ($user['userGroups'] as $userGroup) {
            if (!in_array($userGroup['organization'], $organizations)) {
                $organizations[] = $userGroup['organization'];
            }
        }
        // Add all the sub organisations
        // Add all the parent organisations
        $parentOrganizations = [];
        foreach ($organizations as $organization) {
            $organizations = $this->getSubOrganizations($organizations, $organization, $this->commonGroundService, $this->functionService);
            $parentOrganizations = $this->getParentOrganizations($parentOrganizations, $organization, $this->commonGroundService, $this->functionService);
        }
        $organizations[] = 'localhostOrganization';
        $parentOrganizations[] = 'localhostOrganization';
        $this->session->set('organizations', $organizations);
        $this->session->set('parentOrganizations', $parentOrganizations);
        // If user has no organization, we default activeOrganization to an organization of a userGroup this user has and else the application organization;
        $this->session->set('activeOrganization', $this->getActiveOrganization($user, $organizations));

        return new AuthenticationUser($user['id'], $user['username'], '', $user['username'], $user['username'], $user['username'], '', $user['roles'], $user['username'], $user['locale'], isset($user['organization']) ? $user['organization'] : null, isset($user['person']) ? $user['person'] : null);
    }

    private function getActiveOrganization(array $user, array $organizations): ?string
    {
        if ($user['organization']) {
            return $user['organization'];
        }
        // If user has no organization, we default activeOrganization to an organization of a userGroup this user has
        if (count($organizations) > 0) {
            return $organizations[0];
        }
        // If we still have no organization, get the organization from the application
        if ($this->session->get('application')) {
            $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            if (!empty($application) && $application->getOrganization()) {
                return $application->getOrganization();
            }
        }

        return null;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // no adtional credential check is needed in this case so return true to cause authentication success
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = [
            // you might translate this message
            'message' => 'Authentication Required',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
