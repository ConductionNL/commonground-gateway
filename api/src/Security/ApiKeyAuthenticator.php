<?php

namespace App\Security;

use App\Service\FunctionService;
use Conduction\CommonGroundBundle\Service\AuthenticationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\SamlBundle\Security\User\AuthenticationUser;
use Doctrine\ORM\EntityManagerInterface;
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

class ApiKeyAuthenticator extends \Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator
{
    private CommonGroundService $commonGroundService;
    private ParameterBagInterface $parameterBag;
    private AuthenticationService $authenticationService;
    private SessionInterface $session;
    private FunctionService $functionService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        CommonGroundService $commonGroundService,
        AuthenticationService $authenticationService,
        ParameterBagInterface $parameterBag,
        SessionInterface $session,
        FunctionService $functionService,
        EntityManagerInterface $entityManager
    ) {
        $this->commonGroundService = $commonGroundService;
        $this->parameterBag = $parameterBag;
        $this->authenticationService = $authenticationService;
        $this->session = $session;
        $this->functionService = $functionService;
        $this->entityManager = $entityManager;
    }

    /**
     * @inheritDoc
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization') &&
            strpos($request->headers->get('Authorization'), 'Bearer') === false;
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
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
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

    private function prefixRoles(array $roles): array
    {
        foreach ($roles as $key => $value) {
            $roles[$key] = "ROLE_$value";
        }

        return $roles;
    }

    private function getActiveOrganization(array $user, array $organizations): ?string
    {
        if (isset($user['organization'])) {
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

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): PassportInterface
    {
        $key = $request->headers->get('Authorization');
        $application = $this->entityManager->getRepository('App:Application')->findOneBy(['secret' => $key]);
        if (!$application || !$application->getResource()) {
            throw new AuthenticationException('Invalid ApiKey');
        }

        try {
            $user = $this->commonGroundService->getResource($application->getResource(), [], false);
        } catch (\Exception $exception) {
            throw new AuthenticationException('Invalid User Uri');
        }
        $this->session->set('apiKeyApplication', $application->getId()->toString());

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
            if (isset($userGroup['organization']) && !in_array($userGroup['organization'], $organizations)) {
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

        return new Passport(
            new UserBadge($user['id'], function ($userIdentifier) use ($user) {
                return new AuthenticationUser(
                    $userIdentifier,
                    $user['user']['id'] ?? $user['username'],
                    '',
                    $user['user']['givenName'] ?? $user['username'],
                    $user['user']['familyName'] ?? $user['username'],
                    $user['username'],
                    '',
                    $user['roles'],
                    $user['username'],
                    $user['locale'],
                    $user['organization'] ?? null,
                    $user['person'] ?? null
                );
            }),
            new CustomCredentials(
                function (array $credentials, UserInterface $user) {
                    return $user->getUserIdentifier() == $credentials['id'];
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
