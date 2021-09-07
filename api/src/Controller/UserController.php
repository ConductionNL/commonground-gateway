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
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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

        $this->session->set('backUrl', $request->headers->get('referer'));
        $this->session->set('method', $method);
        $this->session->set('identifier', $identifier);

        $redirectUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('app_user_authenticate', ['method' => $method, 'identifier' => $identifier]);

        $this->session->set('redirectUrl', $redirectUrl);

        return $this->redirect($this->authenticationService->handleAuthenticationUrl($method, $identifier, $redirectUrl));
    }

    /**
     * @Route("logout")
     */
    public function LogoutAction(Request $request, CommonGroundService $commonGroundService)
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
    public function RedirectAction(Request $request, CommonGroundService $commonGroundService)
    {

        if (!empty($request->headers->get('referer')) && $request->headers->get('referer') !== null) {
            return $this->redirect($request->headers->get('referer'));

        } else {
            return $this->redirect($request->getSchemeAndHttpHost());
        }

    }

}
