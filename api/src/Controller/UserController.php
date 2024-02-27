<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Entity\User;
use App\Security\User\AuthenticationUser;
use App\Service\AuthenticationService;
use App\Service\FunctionService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
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
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
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
    private EntityManagerInterface $entityManager;

    public function __construct(AuthenticationService $authenticationService, EntityManagerInterface $entityManager)
    {
        $this->authenticationService = $authenticationService;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("login", methods={"POST"})
     * @Route("users/login", methods={"POST"})
     */
    public function LoginAction(Request $request)
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
                return new Response(json_encode(["Message" => 'User not found.']), 401, ['Content-type' => 'application/json']);
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
            return new Response(json_encode(["Message" => 'User not found.']), 401, ['Content-type' => 'application/json']);
        }

        $user = $this->entityManager->getRepository(User::class)->find($user->getUserIdentifier());

        // Set organization id and user id in session
        $session->set('user', $user->getId()->toString());
        $session->set('organization', $user->getOrganization() !== null ? $user->getOrganization()->getId()->toString() : null);

        $response = $this->validateUserApp($user);
        if ($response !== null)
            return $response;

        // TODO: maybe do not just get the first Application here, but get application using ApplicationService->getApplication() and ...
        // todo... if this returns an application check if the user is part of this application or one of the organizations of this application?
        $user->setJwtToken($authenticationService->createJwtToken($user->getApplications()[0]->getPrivateKey(), $authenticationService->serializeUser($user, $this->session)));

        return new Response($serializer->serialize($user, 'json'), $status, ['Content-type' => 'application/json']);
    }

    /**
     * Create an authentication user from an entity user.
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
        $authToken = new UsernamePasswordToken($authUser, $user->getPassword(), ['public'], $authUser->getRoles());
        $this->container->get('security.token_storage')->setToken($authToken);

        $event = new InteractiveLoginEvent($request, $authToken);
        $eventDispatcher->dispatch($event);
    }

    /**
     * @Route("api/users/login", methods={"POST"})
     */
    public function apiLoginAction(Request $request, UserPasswordHasherInterface $hasher, SerializerInterface $serializer, \CommonGateway\CoreBundle\Service\AuthenticationService $authenticationService, EventDispatcherInterface $eventDispatcher, ManagerRegistry $doctrine)
    {
        $status = 200;
        $data = json_decode($request->getContent(), true);


        $user = $doctrine->getRepository(User::class)->findOneBy(['email' => $data['username']]);
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
        $request->getSession()->set('user', $user->getId()->toString());
        $request->getSession()->set('organization', $user->getOrganization() !== null ? $user->getOrganization()->getId()->toString() : null);

        $response = $this->validateUserApp($user);
        if ($response !== null)
            return $response;

        // TODO: maybe do not just get the first Application here, but get application using ApplicationService->getApplication() and ...
        // todo... if this returns an application check if the user is part of this application or one of the organizations of this application?
        $token = $authenticationService->createJwtToken($user->getApplications()[0]->getPrivateKey(), $authenticationService->serializeUser($user, $request->getSession()));

        $user->setJwtToken($token);

        $this->addUserToSession($user, $eventDispatcher, $request);

        if (isset($data['redirectUrl']) === true) {
            $request->getSession()->set('jwtToken', $token);

            return $this->redirect($data['redirectUrl']);
        } elseif ($request->query->has('redirectUrl') === true) {
            $request->getSession()->set('jwtToken', $token);

            return $this->redirect($request->query->get('redirectUrl'));
        }

        $serializedUser = $serializer->serialize($user, 'json', [AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]);
        $userArray = json_decode($serializedUser, true);

//        $userArray = $this->cleanupLoginResponse($userArray);

        return new Response(json_encode($userArray), $status, ['Content-type' => 'application/json']);
    }

    /**
     * Checks if $user has an application and if that application has a PrivateKey set. If not return error Response.
     *
     * @param User $user A user to check.
     *
     * @return Response|null Error Response or null.
     */
    private function validateUserApp(User $user): ?Response
    {
        if (empty($user->getApplications()) === true) {
            return new Response(
                json_encode(["Message" => 'This user is not yet connected to any application.']),
                409,
                ['Content-type' => 'application/json']
            );
        }

        if (empty($user->getApplications()[0]->getPrivateKey()) === true) {
            return new Response(
                json_encode(["Message" => "Can't create a token because application ({$user->getApplications()[0]->getId()->toString()}) doesn't have a PrivateKey."]),
                409,
                ['Content-type' => 'application/json']
            );
        }

        return null;
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

    /**
     * @Route("api/users/logout", methods={"POST", "GET"})
     */
    public function ApiLogoutAction(Request $request)
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

    public function ApiMeAction(Request $request)
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
     * @Route("login/{method}/{identifier}")
     */
    public function AuthenticateAction(Request $request, $method = null, $identifier = null)
    {
        if (!$method || !$identifier) {
            throw new BadRequestException('Missing authentication method or identifier');
        }

        $request->getSession()->set('backUrl', $request->query->get('redirecturl') ?? $request->headers->get('referer') ?? $request->getSchemeAndHttpHost());
        $request->getSession()->set('method', $method);
        $request->getSession()->set('identifier', $identifier);

        $redirectUrl = $request->getSchemeAndHttpHost().$this->generateUrl('app_user_authenticate', ['method' => $method, 'identifier' => $identifier]);

        if ($request->getSchemeAndHttpHost() !== 'http://localhost' && $request->getSchemeAndHttpHost() !== 'http://localhost') {
            $redirectUrl = str_replace('http://', 'https://', $redirectUrl);
        }

        $request->getSession()->set('redirectUrl', $redirectUrl);

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
