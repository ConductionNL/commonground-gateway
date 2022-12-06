<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

// todo: maybe move all of this to the FunctionService?
class ProcessingLogService
{
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;
    private EavService $eavService;
    private ValidationService $validationService;
    private Security $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        EavService $eavService,
        ValidationService $validationService,
        Security $security
    ) {
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->eavService = $eavService;
        $this->validationService = $validationService;
        $this->security = $security;
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

        if ($this->session->get('entitySource')) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $this->session->get('entitySource')['entity']]);
        }
        if (empty($entity) || $processingLogEntity === $entity) {
//            var_dump('$processingLogEntity === $entity');
//            var_dump(empty($entity));
            return null;
        }
        if ($this->session->get('object')) {
            $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $this->session->get('object')]);
//            var_dump($object->getId()->toString());
        }

        $user = $this->security->getUser();

        $processingLog = [
            'actieNaam'                       => 'placeholder',
            'handelingNaam'                   => 'placeholder',
            'verwerkingNaam'                  => 'placeholder',
            'verwerkingId'                    => 'placeholder',
            'verwerkingsactiviteitId'         => 'placeholder',
            'verwerkingsactiviteitUrl'        => 'placeholder',
            'vertrouwelijkheid'               => 'normaal',
            'bewaartermijn'                   => 'P10Y',
            'uitvoerder'                      => $user->getUserIdentifier(),
            'systeem'                         => 'placeholder',
            'gebruiker'                       => isset($object) && $object->getOwner() ? $object->getOwner() : null,
            'gegevensbron'                    => isset($object) && $object->getEntity()->getSource() ? $object->getEntity()->getSource()->getName() : null,
            'soortAfnemerId'                  => 'placeholder',
            'afnemerId'                       => 'placeholder',
            'verwerkingsactiviteitIdAfnemer'  => 'placeholder',
            'verwerkingsactiviteitUrlAfnemer' => 'placeholder',
            'verwerkingIdAfnemer'             => 'placeholder',
            'tijdstip'                        => isset($object) && $object->getDateCreated() ? $object->getDateCreated()->format('Y-m-dTH:i:s') : null,
            'verwerkteObjecten'               => [
                [
                    'objecttype'    => isset($object) && $object->getEntity() ? $object->getEntity()->getName() : null,
                    'soortObjectId' => isset($object) && $object->getEntity() ? $object->getEntity()->getId() : null,
                    'objectId'      => isset($object) ? $object->getId()->toString() : null,
                    'betrokkenheid' => 'placeholder',
                ],
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
