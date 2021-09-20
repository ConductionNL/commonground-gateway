<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Service\AuthenticationService;
use Conduction\CommonGroundBundle\Service\ApplicationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

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
        $user = $commonGroundService->createResource(['username' => $data['username'],'password' => $data['password']],['component' => 'uc', 'type' => 'login'], false, false,false, false);

        if(!$user) {
            $status = 403;
            $user = [
                "message" => "Invalid credentials",
                "type" => "error",
                "path" => 'users/login',
                "data" => ["username"=>$data['username']],
            ];
        }

        return new Response(json_encode($user), $status, ['Content-type' => 'application/json']);

    }

    /**
     * @Route("users/request_password_reset", methods={"POST"})
     * @Route("api/users/request_password_reset", methods={"POST"})
     */
    public function requestResetAction(Request $request, CommonGroundService $commonGroundService)
    {
        $data = json_decode($request->getContent(), true);
        $users = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $data['username']])['hydra:member'];
        if(count($users) > 0){
            $user = $users[0];
        } else {
            return new Response(json_encode(['username' =>$data['username']]), 200, ['Content-type' => 'application/json']);
        }
        $this->authenticationService->sendTokenMail($user, 'Password reset token');
        return new Response(json_encode(['username' =>$data['username']]), 200, ['Content-type' => 'application/json']);
    }


    /**
     * @Route("users/reset_password", methods={"POST"})
     * @Route("api/users/reset_password", methods={"POST"})
     */
    public function resetAction(Request $request, CommonGroundService $commonGroundService)
    {
        $data = json_decode($request->getContent(), true);
        $user = $commonGroundService->createResource(['username' => $data['username'],'password' => $data['password'],'token' => $data['token'] ],['component' => 'uc', 'type' => 'users/token'], false, false,false,false);

        return new Response(json_encode($user), 200, ['Content-type' => 'application/json']);
    }


    /**
     * @Route("login/digispoof")
     */
    public function DigispoofAction(Request $request, CommonGroundService $commonGroundService)
    {

        $redirect = $commonGroundService->cleanUrl(['component' => 'ds']);

        $responseUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('app_user_digispoof');

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

        $redirectUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('app_user_authenticate', ['method' => $method, 'identifier' => $identifier]);

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
