<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class MapRelocationService
{
    private EntityManagerInterface $entityManager;
    private TranslationService $translationService;
    private ObjectEntityService $objectEntityService;
    private EavService $eavService;
    private SynchronizationService $synchronizationService;
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
     * Creates a VrijRBP Relocation from a ZGW Zaak with the use of mapping.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @return array $this->data Data which we entered the function with
     */
    public function mapRelocationHandler(array $data, array $configuration): array
    {
        $this->data = $data['response'];
        $this->configuration = $configuration;

        if ($this->data['zaaktype']['omschrijving'] !== 'B0366') {
            return $this->data;
        }

        $interRelocationEntity = $this->entityRepo->find($configuration['entities']['InterRelocation']);
        $intraRelocationEntity = $this->entityRepo->find($configuration['entities']['IntraRelocation']);

        if (!isset($interRelocationEntity)) {
            throw new \Exception('IntraRelocation entity could not be found');
        }
        if (!isset($intraRelocationEntity)) {
            throw new \Exception('InterRelocation entity could not be found');
        }

        $relocators = [];
        foreach ($this->data['eigenschappen'] as $eigenschap) {


            if ($eigenschap['naam'] == 'MEEVERHUIZENDE_GEZINSLEDEN') {
                foreach (json_decode($eigenschap['waarde'], true)['MEEVERHUIZENDE_GEZINSLEDEN'] as $meeverhuizende) {
                    switch ($meeverhuizende['ROL']) {
                        case 'P':
                            $declarationType = 'PARTNER';
                            break;
                        case 'K':
                            $declarationType = 'ADULT_CHILD_LIVING_WITH_PARENTS';
                            break;
                        default:
                            $declarationType = 'ADULT_CHILD_LIVING_WITH_PARENTS';
                            break;
                    }
                    $relocators[] = [
                        'bsn' => $meeverhuizende['BSN'],
                        'declarationType' => $declarationType
                    ];
                }
                continue;
            }

            switch ($eigenschap['naam']) {
                case 'DATUM_VERZENDING':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $relocationArray['dossier']['startDate'] = $dateTimeFormatted;
                    continue 2;
                case 'VERHUISDATUM':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
                    $relocationArray['dossier']['entryDateTime'] = $dateTimeFormatted;
                    $relocationArray['dossier']['status']['entryDateTime'] = $dateTimeFormatted;
                    $relocationArray['dossier']['type']['code'] = $this->data['zaaktype']['omschrijving'];
                    continue 2;
                case 'WOONPLAATS_NIEUW':
                    $relocationArray['newAddress']['municipality']['description'] = $eigenschap['waarde'];
                    $relocationArray['newAddress']['residence'] = $eigenschap['waarde'];
                    continue 2;
                case 'TELEFOONNUMMER':
                    $relocator['telephoneNumber'] = $eigenschap['waarde'];
                    continue 2;
                case 'STRAATNAAM_NIEUW':
                    $relocationArray['newAddress']['street'] = $eigenschap['waarde'];
                    continue 2;
                case 'POSTCODE_NIEUW':
                    $relocationArray['newAddress']['postalCode'] = $eigenschap['waarde'];
                    continue 2;
                case 'HUISNUMMER_NIEUW':
                    $relocationArray['newAddress']['houseNumber'] = intval($eigenschap['waarde']);
                    continue 2;
                case 'HUISNUMMERTOEVOEGING_NIEUW':
                    $relocationArray['newAddress']['houseNumberAddition'] = $eigenschap['waarde'];
                    continue 2;
                case 'GEMEENTECODE':
                    $relocationArray['newAddress']['municipality']['code'] = $eigenschap['waarde'];
                    continue 2;
                case 'EMAILADRES':
                    $relocator['email'] = $eigenschap['waarde'];
                    continue 2;
                case 'BSN':
                    $relocationArray['declarant']['bsn'] = $eigenschap['waarde'];
                    $relocationArray['newAddress']['mainOccupant']['bsn'] = $eigenschap['waarde'];
                    $relocationArray['newAddress']['liveIn'] = [
                        'liveInApplicable' => false,
                        'consent' => 'NOT_APPLICABLE',
                        'consenter' => [
                            'bsn' => $eigenschap['waarde']
                        ]
                    ];
                    $relocationArray['newAddress']['addressFunction'] = 'LIVING_ADDRESS';
                    continue 2;
                case 'AANTAL_PERS_NIEUW_ADRES':
                    $relocationArray['newAddress']['numberOfResidents'] = intval($eigenschap['waarde']);
                    continue 2;
            }
        }

        $relocationArray['newAddress']['liveIn']['consenter']['contactInformation'] = [
            'email' => $relocator['email'] ?? null,
            'telephoneNumber' => $relocator['telephoneNumber'] ?? null
        ];
        $relocationArray['newAddress']['mainOccupant']['contactInformation'] = [
            'email' => $relocator['email'] ?? null,
            'telephoneNumber' => $relocator['telephoneNumber'] ?? null
        ];
        $relocationArray['relocators'] = $relocators;
        $relocationArray['relocators'][] = array_merge($relocationArray['newAddress']['mainOccupant'], ['declarationType' => 'ADULT_AUTHORIZED_REPRESENTATIVE']);

        // Save in gateway

        $intraObjectEntity = new ObjectEntity();
        $intraObjectEntity->setEntity($intraRelocationEntity);

        $intraObjectEntity->hydrate($relocationArray);

        $this->entityManager->persist($intraObjectEntity);
        $this->entityManager->flush();

        $intraObjectArray = $intraObjectEntity->toArray();

        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $intraRelocationEntity->getId()->toString(), 'response' => $intraObjectArray]);

        return $this->data;
    }
}
