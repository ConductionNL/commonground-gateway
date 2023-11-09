<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Entity\User;
use App\Security\User\AuthenticationUser;
use App\Service\AuthenticationService;
use App\Service\FunctionService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class LoginController.
 *
 * Authors: Gino Kok, Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 *
 * @Route("/")
 */
class UserController extends AbstractController
{
    private AuthenticationService $authenticationService;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;

    public function __construct(AuthenticationService $authenticationService, SessionInterface $session, EntityManagerInterface $entityManager)
    {
        $this->authenticationService = $authenticationService;
        $this->session = $session;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("login", methods={"POST"})
     * @Route("users/login", methods={"POST"})
     */
    public function LoginAction(Request $request, CommonGroundService $commonGroundService)
    {
    }

    /**
     * @Route("api/users/reset_token", methods={"GET"})
     */
    public function resetTokenAction(SerializerInterface $serializer, \CommonGateway\CoreBundle\Service\AuthenticationService $authenticationService, SessionInterface $session): Response
    {
        if ($session->has('refresh_token') === true && $session->has('authenticator') === true) {
            $accessToken = $this->authenticationService->refreshAccessToken($session->get('refresh_token'), $session->get('authenticator'));
            $user = $this->getUser();
            if ($user instanceof AuthenticationUser === false) {
                return new Response('User not found', 401);
            }

            $serializeUser = new User();
            $serializeUser->setJwtToken($accessToken['access_token']);
            $serializeUser->setName($user->getEmail());
            $serializeUser->setEmail($user->getEmail());
            $serializeUser->setPassword('');
            $session->set('refresh_token', $accessToken['refresh_token']);
            $this->entityManager->persist($serializeUser);

            return new Response($serializer->serialize($serializeUser, 'json'), 200, ['Content-type' => 'application/json']);
        }//end if

        // If the token is in the session because we are redirected, return the token here.
        if ($session->has('jwtToken') === true) {
            $serializeUser = new User();
            $serializeUser->setJwtToken($session->get('jwtToken'));
            $serializeUser->setPassword('');
            $serializeUser->setName('');
            $serializeUser->setEmail('');
            $session->remove('jwtToken');
            $this->entityManager->persist($serializeUser);

            return new Response($serializer->serialize($serializeUser, 'json'), 200, ['Content-type' => 'application/json']);
        }//end if
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $status = 200;
        $user = $this->getUser();
        if ($user instanceof AuthenticationUser === false) {
            return new Response('User not found', 401);
        }

        $user = $this->entityManager->getRepository('App:User')->find($user->getUserIdentifier());

        // Set organization id and user id in session
        $this->session->set('user', $user->getId()->toString());
        $this->session->set('organization', $user->getOrganization() !== null ? $user->getOrganization()->getId()->toString() : null);

        $user->setJwtToken($authenticationService->createJwtToken($user->getApplications()[0]->getPrivateKey(), $authenticationService->serializeUser($user, $this->session)));

        return new Response($serializer->serialize($user, 'json'), $status, ['Content-type' => 'application/json']);
    }

    /**
     * Create an authentication user from a entity user.
     *
     * @param User $user The user to log in.
     *
     * @return AuthenticationUser The resulting authentication user.
     */
    public function createAuthenticationUser(User $user): AuthenticationUser
    {
        $roleArray = [];
        foreach ($user->getSecurityGroups() as $securityGroup) {
            $roleArray['roles'][] = "Role_{$securityGroup->getName()}";
            $roleArray['roles'] = array_merge($roleArray['roles'], $securityGroup->getScopes());
        }

        if (in_array('ROLE_USER', $roleArray['roles']) === false) {
            $roleArray['roles'][] = 'ROLE_USER';
        }
        foreach ($roleArray['roles'] as $key => $role) {
            if (strpos($role, 'ROLE_') !== 0) {
                $roleArray['roles'][$key] = "ROLE_$role";
            }
        }

        $userArray = [
            'id'           => $user->getId()->toString(),
            'email'        => $user->getEmail(),
            'locale'       => $user->getLocale(),
            'organization' => $user->getOrganization()->getId()->toString(),
            'roles'        => $roleArray['roles'],
        ];

        return new AuthenticationUser(
            $userArray['id'],
            $userArray['email'],
            '',
            '',
            '',
            $userArray['email'],
            '',
            $userArray['roles'],
            $userArray['email'],
            $userArray['locale'],
            $userArray['organization'],
            null
        );
    }

    /**
     * Add the logged-in user to session.
     *
     * @param User $user                                The user to log in.
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher.
     * @param Request $request
     *
     * @return void
     */
    public function addUserToSession(User $user, EventDispatcherInterface $eventDispatcher, Request $request): void
    {
        $authUser = $this->createAuthenticationUser($user);
        $authToken = new UsernamePasswordToken($authUser, $user->getPassword(), 'public', $authUser->getRoles());
        $this->get('security.token_storage')->setToken($authToken);

        $event = new InteractiveLoginEvent($request, $authToken);
        $eventDispatcher->dispatch($event);
    }

    /**
     * @Route("api/users/login", methods={"POST"})
     */
    public function apiLoginAction(Request $request, UserPasswordHasherInterface $hasher, SerializerInterface $serializer, \CommonGateway\CoreBundle\Service\AuthenticationService $authenticationService, EventDispatcherInterface $eventDispatcher)
    {
        $status = 200;
        $data = json_decode($request->getContent(), true);

        $user = $this->getDoctrine()->getRepository('App:User')->findOneBy(['email' => $data['username']]);
        if ($user instanceof User === false || $hasher->isPasswordValid($user, $data['password']) === false) {
            $response = [
                'message' => 'Invalid credentials',
                'type'    => 'error',
                'path'    => 'users/login',
                'data'    => ['username'=>$data['username']],
            ];

            return new Response(json_encode($response), 401, ['Content-type' => 'application/json']);
        }

        // Set organization id and user id in session
        $this->session->set('user', $user->getId()->toString());
        $this->session->set('organization', $user->getOrganization() !== null ? $user->getOrganization()->getId()->toString() : null);

        $token = $authenticationService->createJwtToken($user->getApplications()[0]->getPrivateKey(), $authenticationService->serializeUser($user, $this->session));

        $user->setJwtToken($token);

        $this->addUserToSession($user, $eventDispatcher, $request);

        if (isset($data['redirectUrl']) === true) {
            $this->session->set('jwtToken', $token);

            return $this->redirect($data['redirectUrl']);
        } elseif ($request->query->has('redirectUrl') === true) {
            $this->session->set('jwtToken', $token);

            return $this->redirect($request->query->get('redirectUrl'));
        }

        $serializedUser = $serializer->serialize($user, 'json');
        $userArray = json_decode($serializedUser, true);
        $userArray = $this->cleanupLoginResponse($userArray);

        return new Response(json_encode($userArray), $status, ['Content-type' => 'application/json']);
    }

    /**
     * Removes some sensitive data from the login response.
     *
     * @param array $userArray The logged in User Object as array.
     *
     * @return array The updated user array.
     */
    private function cleanupLoginResponse(array $userArray): array
    {
        if (isset($userArray['organization']['users']) === true) {
            unset($userArray['organization']['users']);
        }
        if (isset($userArray['organization']['applications']) === true) {
            foreach ($userArray['organization']['applications'] as &$application) {
                unset($application['organization']);
            }
        }
        foreach ($userArray['applications'] as &$application) {
            unset($application['organization']);
        }
        foreach ($userArray['securityGroups'] as &$securityGroup) {
            unset($securityGroup['users']);
            unset($securityGroup['parent']);
            unset($securityGroup['children']);
        }

        return $userArray;
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

    /**
     * Get all the child organizations for an organization.
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
     * Get al the parent organizations for an organization.
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

    /**
     * @Route("users/request_password_reset", methods={"POST"})
     * @Route("api/users/request_password_reset", methods={"POST"})
     */
    public function requestResetAction(Request $request, CommonGroundService $commonGroundService)
    {
        $data = json_decode($request->getContent(), true);
        $users = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => urlencode($data['username'])])['hydra:member'];
        if (count($users) > 0) {
            $user = $users[0];
        } else {
            return new Response(json_encode(['username' =>$data['username']]), 200, ['Content-type' => 'application/json']);
        }
        $this->authenticationService->sendTokenMail($user, 'Je wachtwoord herstellen', $request->headers->get('Referer', $request->headers->get('referer')));

        return new Response(json_encode(['username' =>$data['username']]), 200, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("users/reset_password", methods={"POST"})
     * @Route("api/users/reset_password", methods={"POST"})
     */
    public function resetAction(Request $request, CommonGroundService $commonGroundService)
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['username']) || !isset($data['password']) || !isset($data['token'])) {
            $status = 400;
            $user = [
                'message' => 'Data missing',
                'type'    => 'error',
                'path'    => 'users/reset_password',
                'data'    => [],
            ];
            isset($data['username']) ?? $user['data']['username'] = null;
            isset($data['password']) ?? $user['data']['password'] = null;
            isset($data['token']) ?? $user['data']['token'] = null;

            return new Response(json_encode($user), $status, ['Content-type' => 'application/json']);
        }

        try {
            $user = $commonGroundService->createResource(['username' => $data['username'], 'password' => $data['password'], 'token' => $data['token']], ['component' => 'uc', 'type' => 'users/token']);
            $status = 200;
            $user['username'] = $data['username'];
        } catch (ClientException $exception) {
            $status = 400;
            $user = [
                'message' => 'Invalid token, username or password',
                'type'    => 'error',
                'path'    => 'users/reset_password',
                'data'    => ['username' => $data['username'], 'password' => $data['password'], 'token'=>$data['token']],
            ];
        }

        return new Response(json_encode($user), $status, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("api/users/logout", methods={"POST", "GET"})
     */
    public function ApiLogoutAction(Request $request, CommonGroundService $commonGroundService)
    {
        $request->getSession()->clear();
        $request->getSession()->invalidate();

        $response = new Response(
            json_encode(['status' => 'logout successful']),
            200,
            [
                'Content-type' => 'application/json',
            ]
        );

        $response->headers->remove('Set-Cookie');
        $response->headers->clearCookie('PHPSESSID', '/', null, true, true, $this->getParameter('samesite'));

        if ($request->query->has('redirectUrl') === true) {
            return $this->redirect($request->query->get('redirectUrl'));
        }

        return $response;
    }

    public function ApiMeAction(Request $request, CommonGroundService $commonGroundService)
    {
        $token = substr($request->headers->get('Authorization'), strlen('Bearer '));
        if (!$token) {
            $status = 403;
            $user = [
                'message' => 'Invalid token',
                'type'    => 'error',
                'path'    => 'users/me',
                'data'    => $token,
            ];

            return new Response(json_encode($user), $status, ['Content-type' => 'application/json']);
        }

        // split the jwt
        $tokenParts = explode('.', $token);
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signature_provided = $tokenParts[2];

        if (!$payload = json_decode($payload, true)) {
            $status = 403;
            $user = [
                'message' => 'Invalid token',
                'type'    => 'error',
                'path'    => 'users/login',
                'data'    => ['jwtToken'=>$token],
            ];
        } else {
            /* @todo hier willen we de user inclusief de organisatie terug geven vanuit de gateway */
            $status = 200;
            //var_dump($payload);
            //die;
            $user = $payload; //$commonGroundService->getResource(['component' => 'uc', 'type' => 'user','id'=>$payload['userId']]);
        }

        return new Response(json_encode($user), $status, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("login/digispoof")
     */
    public function DigispoofAction(Request $request, CommonGroundService $commonGroundService)
    {
        $redirect = $commonGroundService->cleanUrl(['component' => 'ds']);

        $responseUrl = $request->getSchemeAndHttpHost().$this->generateUrl('app_user_digispoof');

        return $this->redirect($redirect.'?responseUrl='.$responseUrl.'&backUrl='.$request->headers->get('referer'));
    }

    /**
     * @Route("login/{method}/{identifier}")
     */
    public function AuthenticateAction(Request $request, $method = null, $identifier = null)
    {
        if (!$method || !$identifier) {
            throw new BadRequestException('Missing authentication method or identifier');
        }

        $this->session->set('backUrl', $request->query->get('redirecturl') ?? $request->headers->get('referer') ?? $request->getSchemeAndHttpHost());
        $this->session->set('method', $method);
        $this->session->set('identifier', $identifier);

        $redirectUrl = $request->getSchemeAndHttpHost().$this->generateUrl('app_user_authenticate', ['method' => $method, 'identifier' => $identifier]);

        if ($request->getSchemeAndHttpHost() !== 'http://localhost' && $request->getSchemeAndHttpHost() !== 'http://localhost') {
            $redirectUrl = str_replace('http://', 'https://', $redirectUrl);
        }

        $this->session->set('redirectUrl', $redirectUrl);

        return $this->redirect($this->authenticationService->handleAuthenticationUrl($method, $identifier, $redirectUrl));
    }

    /**
     * @Route("logout")
     */
    public function LogoutAction(Request $request)
    {
        if (!empty($request->headers->get('referer')) && $request->headers->get('referer') !== null) {
            return $this->redirect(filter_var($request->headers->get('referer'), FILTER_SANITIZE_URL));
        } else {
            return $this->redirect($request->getSchemeAndHttpHost());
        }
    }

    /**
     * @Route("redirect")
     */
    public function RedirectAction(Request $request)
    {
        $url = parse_url($request->headers->get('referer'));
        parse_str($url['query'], $query);
        if (isset($query['redirectUrl'])) {
            return $this->redirect(filter_var($query['redirectUrl'], FILTER_SANITIZE_URL));
        } else {
            return $this->redirect($request->getSchemeAndHttpHost());
        }
    }
}
