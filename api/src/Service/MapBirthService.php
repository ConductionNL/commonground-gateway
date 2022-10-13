<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class MapBirthService
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private array $configuration;
    private array $data;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        TranslationService $translationService,
        ObjectEntityService $objectEntityService,
        EavService $eavService,
        SynchronizationService $synchronizationService
    ) {
        $this->entityManager = $entityManager;
        $this->translationService = $translationService;
        $this->objectEntityService = $objectEntityService;
        $this->eavService = $eavService;
        $this->synchronizationService = $synchronizationService;

        $this->objectEntityRepo = $this->entityManager->getRepository(ObjectEntity::class);
        $this->entityRepo = $this->entityManager->getRepository(Entity::class);

        $this->mappingIn = [];

        $this->skeletonIn = [];
    }

    /**
     * Creates a VrijRBP Birth from a ZGW Zaak with the use of mapping.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @return array $this->data Data which we entered the function with
     */
    public function mapBirthHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if (!isset($data['id']) && !isset($data['response']['id'])) {
            throw new \Exception('Zaak ID not given for MapBirthHandler');
        }

        $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['id']);
        if (!$zaakObjectEntity instanceof ObjectEntity) {
            $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['response']['id']);
            if (!$zaakObjectEntity instanceof ObjectEntity) {
                throw new \Exception('Zaak not found with given ID for MapBirthHandler');
            }
        }

        $zaakArray = $zaakObjectEntity->toArray();

        if ($zaakArray['zaaktype']['identificatie'] !== 'B0366') {
            return $this->data;
        }

        $birthEntity = $this->entityRepo->find($configuration['entities']['Birth']);

        if (!isset($birthEntity)) {
            throw new \Exception('Birth entity could not be found');
        }

        $zaakArray['eigenschappen'] = [
            [
                "eigenschap" => "string",
                "naam" => "FORMULIERID",
                "waarde" => "000000100000"
            ]
        ];

        $birthArray = [];

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            switch ($eigenschap['naam']) {
                case 'DATUM_VERZENDING':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $birthArray['dossier']['startDate'] = $dateTimeFormatted;
                    continue 2;
                case 'VERHUISDATUM':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
                    $birthArray['dossier']['entryDateTime'] = $dateTimeFormatted;
                    $birthArray['dossier']['status']['entryDateTime'] = $dateTimeFormatted;
                    $birthArray['dossier']['type']['code'] = $zaakArray['zaaktype']['omschrijving'];
                    continue 2;
                case 'WOONPLAATS_NIEUW':
                    $birthArray['newAddress']['municipality']['description'] = $eigenschap['waarde'];
                    $birthArray['newAddress']['residence'] = $eigenschap['waarde'];
                    continue 2;
                case 'TELEFOONNUMMER':
                    $relocator['telephoneNumber'] = $eigenschap['waarde'];
                    continue 2;
                case 'STRAATNAAM_NIEUW':
                    $birthArray['newAddress']['street'] = $eigenschap['waarde'];
                    continue 2;
                case 'POSTCODE_NIEUW':
                    $birthArray['newAddress']['postalCode'] = $eigenschap['waarde'];
                    continue 2;
                case 'HUISNUMMER_NIEUW':
                    $birthArray['newAddress']['houseNumber'] = intval($eigenschap['waarde']);
                    continue 2;
                case 'HUISNUMMERTOEVOEGING_NIEUW':
                    $birthArray['newAddress']['houseNumberAddition'] = $eigenschap['waarde'];
                    continue 2;
                case 'GEMEENTECODE':
                    $birthArray['newAddress']['municipality']['code'] = $eigenschap['waarde'];
                    continue 2;
                case 'EMAILADRES':
                    $relocator['email'] = $eigenschap['waarde'];
                    continue 2;
                case 'BSN':
                    $birthArray['declarant']['bsn'] = $eigenschap['waarde'];
                    $birthArray['newAddress']['mainOccupant']['bsn'] = $eigenschap['waarde'];
                    $birthArray['newAddress']['liveIn'] = [
                        'liveInApplicable' => true,
                        'consent' => 'PENDING',
                        'consenter' => [
                            'bsn' => $eigenschap['waarde']
                        ]
                    ];
                    $birthArray['newAddress']['addressFunction'] = 'LIVING_ADDRESS';
                    continue 2;
                case 'AANTAL_PERS_NIEUW_ADRES':
                    $birthArray['newAddress']['numberOfResidents'] = intval($eigenschap['waarde']);
                    continue 2;
            }
        }

        // Save in gateway

        $birthObjectEntity = new ObjectEntity();
        $birthObjectEntity->setEntity($birthEntity);

        $birthObjectEntity->hydrate($birthArray);

        $this->entityManager->persist($birthObjectEntity);
        $this->entityManager->flush();

        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $birthEntity->getId()->toString(), 'response' => $birthArray]);

        return $this->data;
    }
}
