<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
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
