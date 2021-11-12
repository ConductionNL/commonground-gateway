<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/soap")
 */
class SOAPController extends AbstractController
{

    /**
     * @Route("/", methods={"POST"})
     *
     * @param Request $request
     * @return Response
     */
    public function soapAction(Request $request): Response
    {
        var_dump($request->headers->get('SOAPAction'));
        var_dump($request->getContent());
        die;
    }

}
