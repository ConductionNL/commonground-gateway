<?php

namespace App\Service;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use App\Entity\Document;
use App\Entity\File;
use App\Entity\Endpoint;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EndpointService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager, Request $request)
    {
        $this->entityManager = $entityManager;
        $this->request = $request;
    }

    /**
     * Get the data for a document and send it to the document creation service.
     */
    // public function handleEndpoint(Endpoint $enpoint): Response
    // {
        /* @todo endpoint toevoegen aan sessie */

        // @tod creat logicdata, generalvaribales uit de translationservice

        // foreach($enpoint->getHandlers() as $handler){
            // Check the JSON logic (voorbeeld van json logic in de validatie service)
            // if(){
                // return $this->handleHandler($handler);
            // }
        // }

        // @todo we should end here so lets throw an error
    // }


    /**
     * Get the data for a document and send it to the document creation service.
     */
    // public function handleHandler(Handler $handler): Response
    // {
        /* @todo handler toevoegen aan sessie */

        // Onderstaande zouden natuurlijk losse functies moeten zijn

        // voorbeelden voor al deze dingen vind je in de soap service

        // do mapping

        // do translations (is nu nog een vaste array)

        // all eav (if we have an entity) -> EAV service aantikken. 

        // do translations

        // do mapping

        // create response (jatten de eav service)
    // }
}
