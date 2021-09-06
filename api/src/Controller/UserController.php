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
    public function AuthenticateAction(Request $request, $method, $identifier)
    {

        if ($this->session->get('method')) {
            $method = $this->session->get('method');
        }

        if ($this->session->get('identifier')) {
            $identifier = $this->session->get('identifier');
        }

        $this->session->set('backUrl', $request->headers->get('referer'));
        $this->session->set('method', $method);
        $this->session->set('identifier', $identifier);


        $responseUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('app_user_authenticate');

        return $this->redirect($redirect);
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
