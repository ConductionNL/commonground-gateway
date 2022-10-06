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

        $this->mappingIn = [
            'identificatie'                   => 'embedded.instance.embedded.legacy.zaaktype_id|string',
            'onderwerp'                       => 'embedded.instance.title',
            'indicatieInternOfExtern'         => 'embedded.instance.trigger',
            'doorlooptijd'                    => 'embedded.instance.embedded.properties.lead_time_legal.weken',
            'servicenorm'                     => 'embedded.instance.embedded.properties.lead_time_service.weken',
            'vertrouwelijkheidaanduiding'     => 'embedded.instance.embedded.properties.designation_of_confidentiality',
            'verlengingMogelijk'              => 'embedded.instance.embedded.properties.extension',
            'trefwoorden'                     => 'embedded.instance.subject_types',
            'publicatieIndicatie'             => 'embedded.instance.embedded.properties.publication|bool',
            'verantwoordingsrelatie'          => 'embedded.instance.embedded.properties.supervisor_relation|array',
            'omschrijving'                    => 'embedded.instance.title',
            'opschortingEnAanhoudingMogelijk' => 'embedded.instance.embedded.properties.suspension|bool',
        ];

        $this->skeletonIn = [
            'handelingInitiator'   => 'indienen',
            'beginGeldigheid'      => '1970-01-01',
            'versieDatum'          => '1970-01-01',
            'doel'                 => 'Overzicht hebben van de bezoekers die aanwezig zijn',
            'versiedatum'          => '1970-01-01',
            'handelingBehandelaar' => 'Hoofd beveiliging',
            'aanleiding'           => 'Er is een afspraak gemaakt met een (niet) natuurlijk persoon',
        ];
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


            // if ($eigenschap['naam'] == 'MEEVERHUIZENDE_GEZINSLEDEN') {
            //     foreach (json_decode($eigenschap['waarde'], true) as $meeverhuizende) {
            //         var_dump(json_decode($eigenschap['waarde'], true));
            //         switch ($meeverhuizende['ROL']) {
            //             case 'P':
            //                 $declarationType = 'PARTNER';
            //                 break;
            //             case 'K':
            //                 $declarationType = 'ADULT_CHILD_LIVING_WITH_PARENTS';
            //                 break;
            //             default:
            //                 $declarationType = 'ADULT_CHILD_LIVING_WITH_PARENTS';
            //                 break;
            //         }
            //         $relocators[] = [
            //             'bsn' => $meeverhuizende['BSN'],
            //             'declarationType' => $declarationType
            //         ];
            //     }
            //     continue;
            // }

            switch ($eigenschap['naam']) {
                case 'DATUM_VERZENDING':
                    $relocationArray['dossier']['startDate'] = $eigenschap['waarde'];
                    continue;
                case 'VERHUISDATUM':
                    $relocationArray['dossier']['entryDateTime'] = $eigenschap['waarde'];
                    $relocationArray['dossier']['status']['entryDateTime'] = $eigenschap['waarde'];
                    continue;
                case 'WOONPLAATS_NIEUW':
                    $relocationArray['newAddress']['municipality']['description'] = $eigenschap['waarde'];
                    continue;
                case 'WIJZE_BEWONING':
                    $relocationArray['newAddress']['residence'] = $eigenschap['waarde'];
                    continue;
                case 'TELEFOONNUMMER':
                    $relocator['telephoneNumber'] = $eigenschap['waarde'];
                    continue;
                case 'STRAATNAAM_NIEUW':
                    $relocationArray['newAddress']['street'] = $eigenschap['waarde'];
                    continue;
                case 'POSTCODE_NIEUW':
                    $relocationArray['newAddress']['postalCode'] = $eigenschap['waarde'];
                    continue;
                case 'HUISNUMMER_NIEUW':
                    $relocationArray['newAddress']['houseNumber'] = intval($eigenschap['waarde']);
                    continue;
                case 'HUISNUMMERTOEVOEGING_NIEUW':
                    $relocationArray['newAddress']['houseNumberAddittion'] = $eigenschap['waarde'];
                    continue;
                case 'GEMEENTECODE':
                    $relocationArray['newAddress']['municipality']['code'] = $eigenschap['waarde'];
                    continue;
                case 'EMAILADRES':
                    $relocator['email'] = $eigenschap['waarde'];
                    continue;
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
                    continue;
                case 'AANTAL_PERS_NIEUW_ADRES':
                    $relocationArray['newAddress']['numberOfResidents'] = intval($eigenschap['waarde']);
                    continue;
            }
        }

        $relocationArray['newAddress']['liveIn']['consenter']['contactInformation'] = [
            'email' => $relocator['email'],
            'telephoneNumber' => $relocator['telephoneNumber']
        ];
        $relocationArray['newAddress']['mainOccupant']['contactInformation'] = [
            'email' => $relocator['email'],
            'telephoneNumber' => $relocator['telephoneNumber']
        ];
        // $relocationArray['relocators'] = $relocators;

        // Save in gateway

        $intraObjectEntity = new ObjectEntity();
        $intraObjectEntity->setEntity($intraRelocationEntity);

        $intraObjectEntity->hydrate($relocationArray);

        $this->entityManager->persist($intraObjectEntity);
        $this->entityManager->flush();

        var_dump($intraRelocationEntity->getId()->toString());
        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $intraRelocationEntity->getId()->toString(), 'response' => $intraObjectEntity->toArray()]);

        return $this->data;
    }
}
