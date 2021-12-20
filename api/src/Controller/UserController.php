<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\AuthenticationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use GuzzleHttp\Exception\ClientException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class LoginController.
 *
 *
 * @Route("/")
 */
class UserController extends AbstractController
{
    private AuthenticationService $authenticationService;
    private SessionInterface $session;

    public function __construct(AuthenticationService $authenticationService, SessionInterface $session)
    {
        $this->authenticationService = $authenticationService;
        $this->session = $session;
    }

    /**
     * @Route("login", methods={"POST"})
     * @Route("users/login", methods={"POST"})
     */
    public function LoginAction(Request $request, CommonGroundService $commonGroundService)
    {
    }

    /**
     * @Route("api/users/login", methods={"POST"})
     */
    public function apiLoginAction(Request $request, CommonGroundService $commonGroundService)
    {
        $status = 200;
        $data = json_decode($request->getContent(), true);
        $userLogin = $commonGroundService->createResource(['username' => $data['username'], 'password' => $data['password']], ['component' => 'uc', 'type' => 'login'], false, false, false, false);

        if (!$userLogin) {
            $userLogin = [
                'message' => 'Invalid credentials',
                'type'    => 'error',
                'path'    => 'users/login',
                'data'    => ['username'=>$data['username']],
            ];

            return new Response(json_encode($userLogin), 403, ['Content-type' => 'application/json']);
        }

        // Set orgs in session for multitenancy
        // Get user object with userGroups (login only returns a user with userGroups as: /groups/uuid)
        $user = $commonGroundService->getResource(['component' => 'uc', 'type' => 'users', 'id' => $userLogin['id']], [], false);
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
        $parentOrganizations = [];
        foreach ($organizations as $organization) {
            $organizations = $this->getSubOrganizations($organizations, $organization, $commonGroundService);
            $parentOrganizations = $this->getParentOrganizations($parentOrganizations, $organization, $commonGroundService);
        }
        // Add all the parent organisations

        $organizations[] = 'localhostOrganization';
        $parentOrganizations[] = 'localhostOrganization';
        $this->session->set('organizations', $organizations);
        $this->session->set('parentOrganizations', $parentOrganizations);
        // If user has no organization, we default activeOrganization to an organization of a userGroup this user has and else the application organization;
        $this->session->set('activeOrganization', $this->getActiveOrganization($user, $organizations));

        return new Response(json_encode($userLogin), $status, ['Content-type' => 'application/json']);
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
        if ($this->session->get('application') && $this->session->get('application')->getOrganization()) {
            return $this->session->get('application')->getOrganization();
        }

        return null;
    }

    /**
     * Get all the child organisations for an organisation
     *
     * @param array               $organizations
     * @param string              $organization
     * @param CommonGroundService $commonGroundService
     *
     * @return array
     */
    private function getSubOrganizations(array $organizations, string $organization, CommonGroundService $commonGroundService): array
    {
        if ($organization = $this->isResource($organization, $commonGroundService)) {
            if (count($organization['subOrganizations']) > 0) {
                foreach ($organization['subOrganizations'] as $subOrganization) {
                    if (!in_array($subOrganization['@id'], $organizations)) {
                        $organizations[] = $subOrganization['@id'];
                        $organizations =$this->getSubOrganizations($organizations, $subOrganization['@id'], $commonGroundService);
                    }
                }
            }
        }

        return $organizations;
    }


    /**
     * Get al the parent organizations for an organisation
     *
     * @param array $organizations
     * @param string $organization
     * @param CommonGroundService $commonGroundService
     * @return array
     */
    private function getParentOrganizations(array $organizations, string $organization, CommonGroundService $commonGroundService): array
    {
        if ($organization = $this->isResource($organization, $commonGroundService)) {
            if (!in_array($organization['parentOrganization']['@id'], $organizations) && array_key_exists('parentOrganization', $organization)) {
                $organizations[] = $organization['parentOrganization']['@id'];
                $organizations = $this->getParentOrganizations($organizations, $organization['parentOrganization']['@id'], $commonGroundService);
            }
        }

        return $organizations;
    }

    public function isResource($url, CommonGroundService $commonGroundService)
    {
        try {
            return $commonGroundService->getResource($url, [], false);
        } catch (\Throwable $e) {
            return false;
        }
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
        if ($request->headers->has('Authorization')) {
            $token = substr($request->headers->get('Authorization'), strlen('Bearer '));
            $user = $commonGroundService->createResource(['jwtToken' => $token], ['component' => 'uc', 'type' => 'logout'], false, false, false, false);
        }
        $request->getSession()->invalidate();
        $response = new Response(
            json_encode(['status' => 'logout successful']),
            200,
            [
                'Content-type' => 'application/json',
            ]
        );
        $response->headers->clearCookie('PHPSESSID');

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

        $this->session->set('backUrl', $request->headers->get('referer') ?? $request->getSchemeAndHttpHost());
        $this->session->set('method', $method);
        $this->session->set('identifier', $identifier);

        $redirectUrl = $request->getSchemeAndHttpHost().$this->generateUrl('app_user_authenticate', ['method' => $method, 'identifier' => $identifier]);

        $this->session->set('redirectUrl', $redirectUrl);

        return $this->redirect($this->authenticationService->handleAuthenticationUrl($method, $identifier, $redirectUrl));
    }

    /**
     * @Route("logout")
     */
    public function LogoutAction(Request $request)
    {
        if (!empty($request->headers->get('referer')) && $request->headers->get('referer') !== null) {
            return $this->redirect($request->headers->get('referer'));
        } else {
            return $this->redirect($request->getSchemeAndHttpHost());
        }
    }

    /**
     * @Route("redirect")
     */
    public function RedirectAction(Request $request)
    {
        if (!empty($request->headers->get('referer')) && $request->headers->get('referer') !== null) {
            return $this->redirect($request->headers->get('referer'));
        } else {
            return $this->redirect($request->getSchemeAndHttpHost());
        }
    }
}
