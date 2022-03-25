<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\Log;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ProcessingLogService
{
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;
    private EavService $eavService;
    private ValidationService $validationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        EavService $eavService,
        ValidationService $validationService
    ) {
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->eavService = $eavService;
        $this->validationService = $validationService;
    }

    /**
     * Checks if there exists an entity with the function processingLog and returns it.
     *
     * @return Entity|null
     */
    private function checkProcessingLog(): ?Entity
    {
        // Look for an entity with function processingLog
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['function' => 'processingLog']);
        if (!empty($entity)) {
            return $entity;
        }

        return null;
    }

    /**
     * Creates an ObjectEntity processingLog if an Entity exists with the function processingLog.
     *
     * @return ObjectEntity|null
     */
    public function saveProcessingLog(): ?ObjectEntity
    {
//        var_dump('saveProcessingLog');
        if (!$processingLogEntity = $this->checkProcessingLog()) {
//            var_dump('no processingLog entity found');
            return null;
        }

        if ($this->session->get('entity')) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $this->session->get('entity')]);
        }
        if (empty($entity) || $processingLogEntity === $entity) {
//            var_dump('$processingLogEntity === $entity');
//            var_dump(empty($entity));
            return null;
        }

        $processingLog = [
            "actieNaam" => "placeholder",
            "handelingNaam" => "placeholder",
            "verwerkingNaam" => "placeholder",
            "verwerkingId" => "placeholder",
            "verwerkingsactiviteitId" => "placeholder",
            "verwerkingsactiviteitUrl" => "placeholder",
            "vertrouwelijkheid" => "normaal",
            "bewaartermijn" => "P10Y",
            "uitvoerder" => "placeholder",
            "systeem" => "placeholder",
            "gebruiker" => "placeholder",
            "gegevensbron" => "placeholder",
            "soortAfnemerId" => "placeholder",
            "afnemerId" => "placeholder",
            "verwerkingsactiviteitIdAfnemer" => "placeholder",
            "verwerkingsactiviteitUrlAfnemer" => "placeholder",
            "verwerkingIdAfnemer" => "placeholder",
            "tijdstip" => "2024-04-05T14:35:42+01:00",
            "verwerkteObjecten" => [
                [
                    "objecttype" => "placeholder",
                    "soortObjectId" => "placeholder",
                    "objectId" => "placeholder",
                    "betrokkenheid" => "placeholder",
                ]
            ],
        ];

//        var_dump($processingLogEntity->getName());
//        var_dump($entity->getName());

        $mockRequest = new Request();
        $mockRequest->setMethod('POST');
        $this->validationService->setRequest($mockRequest);
        $this->validationService->setIgnoreErrors(true);

        $processingLogObject = $this->eavService->getObject(null, 'POST', $processingLogEntity);
        $processingLogObject = $this->validationService->validateEntity($processingLogObject, $processingLog);
//        var_dump($processingLogObject->getAllErrors()); // TODO: REMOVE VAR DUMP!!!

        $this->entityManager->persist($processingLogObject);
        $this->entityManager->flush();

        return $processingLogObject;
    }
}
