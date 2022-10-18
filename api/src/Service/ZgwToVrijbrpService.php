<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class ZgwToVrijbrpService
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

    private function createBirthObject(array $zaakArray)
    {
        $birthEntity = $this->entityRepo->find($this->configuration['entities']['Birth']);

        if (!isset($birthEntity)) {
            throw new \Exception('Birth entity could not be found');
        }

        $zaakArray['eigenschappen'] = [
            [
                'eigenschap' => 'string',
                'naam'       => 'FORMULIERID',
                'waarde'     => '000000100000',
            ],
        ];

        $birthArray = [];

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            switch ($eigenschap['naam']) {
                case 'identificatie':
                    $commitmentArray['dossier']['dossierId'] = $eigenschap['waarde'];
                    continue 2;
                case 'omschrijving':
                    $commitmentArray['dossier']['description'] = $eigenschap['waarde'];
                    $commitmentArray['dossier']['type']['description'] = $eigenschap['waarde'];
                    continue 2;
                case 'startdatum':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $commitmentArray['dossier']['startDate'] = $dateTimeFormatted;
                    continue 2;
                case 'registratiedatum':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d|TH:i:s');
                    $commitmentArray['dossier']['entryDateTime'] = $eigenschap['waarde'];
                    $commitmentArray['dossier']['status']['entryDateTime'] = $eigenschap['waarde'];
                    continue 2;
                case 'inp.bsn':
                    $commitmentArray['declarant']['bsn'] = $eigenschap['waarde'];
                    $commitmentArray['mother']['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam':
                    $commitmentArray['nameSelection']['lastName'] = $eigenschap['waarde'];
                    continue 2;
            }
        }

        $commitmentArray['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];
        $commitmentArray['qualificationForDeclaringType'] = 'UNDERTAKER';

        // Save in gateway

        $birthObjectEntity = new ObjectEntity();
        $birthObjectEntity->setEntity($birthEntity);

        $birthObjectEntity->hydrate($birthArray);

        $this->entityManager->persist($birthObjectEntity);
        $this->entityManager->flush();

        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $birthEntity->getId()->toString(), 'response' => $birthArray]);

        return $this->data;
    }

    private function createCommitmentObject(array $zaakArray): array
    {
        $commitmentEntity = $this->entityRepo->find($this->configuration['entities']['Commitment']);

        if (!isset($commitmentEntity)) {
            throw new \Exception('Commitment entity could not be found');
        }

        $commitmentArray = [
            'partner1' => [
                'nameAfterCommitment' => [],
            ],
            'partner2' => [
                'nameAfterCommitment' => [],
            ],
            'dossier' => [
                'type'   => [],
                'status' => [],
            ],
        ];
        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            switch ($eigenschap['naam']) {
                case 'identificatie':
                    $commitmentArray['dossier']['dossierId'] = $eigenschap['waarde'];
                    continue 2;
                case 'omschrijving':
                    $commitmentArray['dossier']['description'] = $eigenschap['waarde'];
                    $commitmentArray['dossier']['type']['description'] = $eigenschap['waarde'];
                    continue 2;
                case 'bsn1':
                    $commitmentArray['partner1']['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam1':
                    $commitmentArray['partner1']['nameAfterCommitment']['lastname'] = $eigenschap['waarde'];
                    continue 2;
                case 'geselecteerdNaamgebruik':
                    $commitmentArray['partner1']['nameAfterCommitment']['nameUseType'] = $eigenschap['waarde'];
                    $commitmentArray['partner2']['nameAfterCommitment']['nameUseType'] = $eigenschap['waarde'];
                    continue 2;
                case 'bsn2':
                    $commitmentArray['partner2']['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam2':
                    $commitmentArray['partner2']['nameAfterCommitment']['lastname'] = $eigenschap['waarde'];
                case 'verbintenisType':
                    in_array($eigenschap['waarde'], ['MARRIAGE', 'GPS']) && $commitmentArray['planning']['commitmentType'] = $eigenschap['waarde'];
                    continue 2;
                case 'verbintenisDatum':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
                    $commitmentArray['planning']['commitmentDateTime'] = $dateTimeFormatted;
                    continue 2;
                case 'gor.openbareRuimteNaam':
                    $commitmentArray['location']['name'] = $eigenschap['waarde'];
                    continue 2;
            }
        }

        $commitmentArray['dossier']['type']['code'] = $zaakArray['zaaktype']['code'];
        $commitmentArray['dossier']['dossierId'] = $zaakArray['id'];

        $dateTimeObject = new \DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $commitmentArray['dossier']['startDate'] = $dateTimeFormatted;

        $dateTimeObject = new \DateTime($zaakArray['registratiedatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
        $dateTimeObject = new \DateTime($eigenschap['waarde']);
        $commitmentArray['dossier']['entryDateTime'] = $dateTimeFormatted;
        $commitmentArray['dossier']['status']['entryDateTime'] = $dateTimeFormatted;

        // Save in gateway

        $commitmentObjectEntity = new ObjectEntity();
        $commitmentObjectEntity->setEntity($commitmentEntity);

        $commitmentObjectEntity->hydrate($commitmentArray);

        $this->entityManager->persist($commitmentObjectEntity);
        $this->entityManager->flush();

        $commitmentObjectArray = $commitmentObjectEntity->toArray();

        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $commitmentEntity->getId()->toString(), 'response' => $commitmentObjectArray]);

        return $this->data;
    }

    /**
     * Maps relocators to their VrijBRP layout.
     *
     * @param array $eigenschap The relocators-element in the SimXML message
     *
     * @return array The resulting relocators array
     */
    private function mapRelocators(array $eigenschap): array
    {
        foreach (json_decode($eigenschap['waarde'], true) as $meeverhuizende) {
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
                'bsn'             => $meeverhuizende['BSN'],
                'declarationType' => $declarationType,
            ];
        }

        return $relocators;
    }

    private function createRelocationObject(array $zaakArray, ObjectEntity $zaakObjectEntity)
    {
        $interRelocationEntity = $this->entityRepo->find($this->configuration['entities']['InterRelocation']);
        $intraRelocationEntity = $this->entityRepo->find($this->configuration['entities']['IntraRelocation']);

        if (!isset($interRelocationEntity)) {
            throw new \Exception('IntraRelocation entity could not be found');
        }
        if (!isset($intraRelocationEntity)) {
            throw new \Exception('InterRelocation entity could not be found');
        }

        $relocators = [];
        $relocator = [];

        $zaakArray['eigenschappen'] = [
            [
                'eigenschap' => 'string',
                'naam'       => 'FORMULIERID',
                'waarde'     => '000000100000',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'DATUMVERZENDING',
                'waarde'     => '2021-12-01T23:41:18',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'ZAAKTYPE',
                'waarde'     => 'B0366',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'CODE_KANAAL',
                'waarde'     => 'web',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'INDIENER',
                'waarde'     => '999995923',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'INDIENERSOORT',
                'waarde'     => 'burger',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'BETROUWBAARHEID_IDENTIFICATIE',
                'waarde'     => '10',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'BETROUWBAARHEID_NAW',
                'waarde'     => '1',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'BSN',
                'waarde'     => '999995923',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'VOORLETTERS',
                'waarde'     => 'W.J.',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'ACHTERNAAM',
                'waarde'     => 'Zech',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'GESLACHT',
                'waarde'     => 'm',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'STRAATNAAM',
                'waarde'     => 'Amazonestroom',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'HUISNUMMER',
                'waarde'     => '96',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'POSTCODE',
                'waarde'     => '2721EP',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'WOONPLAATS',
                'waarde'     => 'Zoetermeer',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'TELEFOONNUMMER',
                'waarde'     => '0650606977',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'EMAILADRES',
                'waarde'     => 'wim@zech.nl',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'GEMEENTECODE',
                'waarde'     => '0268',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'STRAATNAAM_NIEUW',
                'waarde'     => 'Binderskampweg',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'HUISNUMMER_NIEUW',
                'waarde'     => '29',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'HUISLETTER_NIEUW',
                'waarde'     => 'U',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'HUISNUMMERTOEVOEGING_NIEUW',
                'waarde'     => '39',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'POSTCODE_NIEUW',
                'waarde'     => '6545CA',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'WOONPLAATS_NIEUW',
                'waarde'     => 'Nijmegen',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'VERHUISDATUM',
                'waarde'     => '2022-01-09',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'AANTAL_PERS_NIEUW_ADRES',
                'waarde'     => '3',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'WIJZE_BEWONING',
                'waarde'     => 'nieuwbouw',
            ],
            [
                'eigenschap' => 'string',
                'naam'       => 'MEEVERHUIZENDE_GEZINSLEDEN',
                'waarde'     => '[{"BSN":"999995959","ROL":"P","VOORLETTERS":"S.C.A.","VOORNAAM":"Simone Clarina Antoinette","ACHTERNAAM":"Meelis","GESLACHTSAANDUIDING":"v","GEBOORTEDATUM":"19680822"},{"BSN":"999995996","ROL":"K","VOORLETTERS":"K.","VOORNAAM":"Kim","ACHTERNAAM":"Zech","GESLACHTSAANDUIDING":"v","GEBOORTEDATUM":"19980227"}]',
            ],
        ];

        $relocationArray = [];

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            if ($eigenschap['naam'] == 'MEEVERHUIZENDE_GEZINSLEDEN') {
                $relocators = $this->mapRelocators($eigenschap);
            } else {
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
                        $relocationArray['dossier']['type']['code'] = $zaakArray['zaaktype']['omschrijving'];
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
                            'liveInApplicable' => true,
                            'consent'          => 'PENDING',
                            'consenter'        => [
                                'bsn' => $eigenschap['waarde'],
                            ],
                        ];
                        $relocationArray['newAddress']['addressFunction'] = 'LIVING_ADDRESS';
                        continue 2;
                    case 'AANTAL_PERS_NIEUW_ADRES':
                        $relocationArray['newAddress']['numberOfResidents'] = intval($eigenschap['waarde']);
                        continue 2;
                }
            }
        }

        $relocationArray['newAddress']['liveIn']['consenter']['contactInformation'] = [
            'email'           => $relocator['email'] ?? null,
            'telephoneNumber' => $relocator['telephoneNumber'] ?? null,
        ];
        $relocationArray['newAddress']['mainOccupant']['contactInformation'] = [
            'email'           => $relocator['email'] ?? null,
            'telephoneNumber' => $relocator['telephoneNumber'] ?? null,
        ];
        $relocationArray['relocators'] = $relocators;
        $relocationArray['relocators'][] = array_merge($relocationArray['newAddress']['mainOccupant'], ['declarationType' => 'ADULT_AUTHORIZED_REPRESENTATIVE']);

        $relocationArray['dossier']['dossierId'] = $zaakArray['id'];
        !isset($relocationArray['dossier']['startDate']) && $relocationArray['dossier']['startDate'] = $relocationArray['dossier']['entryDateTime'];

        // Save in gateway

        $intraObjectEntity = new ObjectEntity();
        $intraObjectEntity->setEntity($intraRelocationEntity);

        $intraObjectEntity->hydrate($relocationArray);

        $this->entityManager->persist($intraObjectEntity);
        $this->entityManager->flush();

        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $intraRelocationEntity->getId()->toString(), 'response' => $relocationArray]);

        return $this->data;
    }

    /**
     * Creates a VrijRBP Birth from a ZGW Zaak with the use of mapping.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @return array $this->data Data which we entered the function with
     */
    public function zgwToVrijbrpHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if (!isset($data['response']['id'])) {
            throw new \Exception('Zaak ID not given for ZgwToVrijbrpHandler');
        }

        $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['response']['zgwZaak']['id']);
        if (!$zaakObjectEntity instanceof ObjectEntity) {
            $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['response']['zgwZaak']['id']);
            if (!$zaakObjectEntity instanceof ObjectEntity) {
                throw new \Exception('Zaak not found with given ID for ZgwToVrijbrpHandler');
            }
        }

        $zaakArray = $zaakObjectEntity->toArray();

        switch ($zaakArray['zaaktype']['code']) {
            case 'B0237':
                return $this->createBirthObject($zaakArray);
            case 'B0366':
                return $this->createRelocationObject($zaakArray, $zaakObjectEntity);
            case 'B0337':
                return $this->createCommitmentObject($zaakArray);
            default:
                return $this->data;
        }
    }
}
