<?php

namespace App\Controller;

use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class PostalCodesController extends AbstractController
{
    /**
     * @Route("/eav/postal_codes", methods={"get"})
     */
    public function PostalCodesAction(ValidationService $validationService)
    {
        return $validationService->dutchPC4ToJson();
    }
}
