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
        isset($this->configuration['entities']['Birth']) && $birthEntity = $this->entityRepo->find($this->configuration['entities']['Birth']);

        if (!isset($birthEntity)) {
            throw new \Exception('Birth entity could not be found, check ZgwToVrijbrpAction config');
        }

        $birthArray = [
            'declarant' => [],
        ];

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            $childIndex = '';
            if (in_array(substr_replace($eigenschap['naam'], '', -1), ['voornamen', 'geboortedatum', 'geslachtsaanduiding'])) {
                $childIndex = substr($eigenschap['naam'], -1);
                $childIndexInt = intval($childIndex) - 1;
            }
            switch ($eigenschap['naam']) {
                case 'voornamen' . $childIndex:
                    $birthArray['children'][$childIndexInt]['firstname'] = $eigenschap['waarde'];
                    continue 2;
                case 'geboortedatum' . $childIndex:
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
                    $birthArray['children'][$childIndexInt]['birthDateTime'] = $dateTimeFormatted;
                    continue 2;
                case 'geslachtsaanduiding' . $childIndex:
                    in_array($eigenschap['waarde'], ['MAN', 'WOMAN', 'UNKNOWN']) && $birthArray['children'][$childIndexInt]['gender'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam':
                    $birthArray['nameSelection']['lastname'] = $eigenschap['waarde'];
                    continue 2;
            }
        }

        isset($birthArray['children']) && $birthArray['children'] = array_values($birthArray['children']);

        $birthArray['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];
        $birthArray['dossier']['dossierId'] = $zaakArray['id'];
        $birthArray['qualificationForDeclaringType'] = 'MOTHER';

        $dateTimeObject = new \DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $birthArray['dossier']['startDate'] = $dateTimeFormatted;

        $dateTimeObject = new \DateTime($zaakArray['registratiedatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
        $birthArray['dossier']['entryDateTime'] = $dateTimeFormatted;
        $birthArray['dossier']['status']['entryDateTime'] = $dateTimeFormatted;

        if (isset($zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn'])) {
            $birthArray['declarant']['bsn'] = $zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn'];
            $birthArray['mother']['bsn'] = $zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn'];

            // Save in gateway (only save when we have a declarant/mother)
            $birthObjectEntity = new ObjectEntity();
            $birthObjectEntity->setEntity($birthEntity);

            $birthObjectEntity->hydrate($birthArray);

            $this->entityManager->persist($birthObjectEntity);
            $this->entityManager->flush();

            $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $birthEntity->getId()->toString(), 'response' => $birthArray]);
        }

        return $this->data;
    }

    private function createCommitmentObject(array $zaakArray): array
    {
        isset($this->configuration['entities']['Commitment']) && $commitmentEntity = $this->entityRepo->find($this->configuration['entities']['Commitment']);

        if (!isset($commitmentEntity)) {
            throw new \Exception('Commitment entity could not be found, check ZgwToVrijbrpAction config');
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

        $commitmentArray['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];
        $commitmentArray['dossier']['dossierId'] = $zaakArray['id'];

        $dateTimeObject = new \DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $commitmentArray['dossier']['startDate'] = $dateTimeFormatted;

        $dateTimeObject = new \DateTime($zaakArray['registratiedatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
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
            switch ($meeverhuizende['rol']) {
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
                'bsn'             => $meeverhuizende['bsn'],
                'declarationType' => $declarationType,
            ];
        }

        return $relocators;
    }

    private function createRelocationObject(array $zaakArray): array
    {
        isset($this->configuration['entities']['InterRelocation']) && $interRelocationEntity = $this->entityRepo->find($this->configuration['entities']['InterRelocation']);
        isset($this->configuration['entities']['IntraRelocation']) && $intraRelocationEntity = $this->entityRepo->find($this->configuration['entities']['IntraRelocation']);

        if (!isset($interRelocationEntity)) {
            throw new \Exception('IntraRelocation entity could not be found, check ZgwToVrijbrpAction config');
        }
        if (!isset($intraRelocationEntity)) {
            throw new \Exception('InterRelocation entity could not be found, check ZgwToVrijbrpAction config');
        }

        $relocators = [];
        $relocator = [];

        $relocationArray = [];

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            if ($eigenschap['naam'] == 'meeverhuizende_gezinsleden') {
                $relocators = $this->mapRelocators($eigenschap);
                continue;
            }


            switch ($eigenschap['naam']) {
                case 'bsn':
                    continue 2;
                case 'verhuisdatum':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
                    $relocationArray['dossier']['entryDateTime'] = $dateTimeFormatted;
                    $relocationArray['dossier']['status']['entryDateTime'] = $dateTimeFormatted;
                    continue 2;
                case 'woonplaats_nieuw':
                    $relocationArray['newAddress']['residence'] = $eigenschap['waarde'];
                    continue 2;
                case 'telefoonnummer':
                    $relocator['telephoneNumber'] = $eigenschap['waarde'];
                    continue 2;
                case 'straatnaam_nieuw':
                    $relocationArray['newAddress']['street'] = $eigenschap['waarde'];
                    continue 2;
                case 'postcode_nieuw':
                    $relocationArray['newAddress']['postalCode'] = $eigenschap['waarde'];
                    continue 2;
                case 'huisnummer_nieuw':
                    $relocationArray['newAddress']['houseNumber'] = intval($eigenschap['waarde']);
                    continue 2;
                case 'huisnummertoevoeging_nieuw':
                    $relocationArray['newAddress']['houseNumberAddition'] = $eigenschap['waarde'];
                    continue 2;
                case 'gemeentecode':
                    $relocationArray['newAddress']['municipality']['code'] = $eigenschap['waarde'];
                    continue 2;
                case 'emailadres':
                    $relocator['email'] = $eigenschap['waarde'];
                    continue 2;
                case 'aantal_pers_nieuw_adres':
                    $relocationArray['newAddress']['numberOfResidents'] = intval($eigenschap['waarde']);
                    continue 2;
            }
        }

        if (isset($zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn']) && $bsn = $zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn']) {
            $relocationArray['declarant']['bsn'] = $bsn;
            $relocationArray['newAddress']['mainOccupant']['bsn'] = $bsn;
            $relocationArray['newAddress']['liveIn'] = [
                'liveInApplicable' => true,
                'consent'          => 'PENDING',
                'consenter'        => [
                    'bsn' => $bsn,
                ],
            ];
        }

        $relocationArray['newAddress']['liveIn']['consenter']['contactInformation'] = [
            'email'           => $relocator['email'] ?? null,
            'telephoneNumber' => $relocator['telephoneNumber'] ?? null,
        ];
        $relocationArray['newAddress']['mainOccupant']['contactInformation'] = [
            'email'           => $relocator['email'] ?? null,
            'telephoneNumber' => $relocator['telephoneNumber'] ?? null,
        ];
        $relocationArray['newAddress']['addressFunction'] = 'LIVING_ADDRESS';

        $relocationArray['relocators'] = $relocators;
        $relocationArray['relocators'][] = array_merge($relocationArray['newAddress']['mainOccupant'], ['declarationType' => 'ADULT_AUTHORIZED_REPRESENTATIVE']);

        $relocationArray['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];
        $relocationArray['dossier']['dossierId'] = $zaakArray['id'];

        $dateTimeObject = new \DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $relocationArray['dossier']['startDate'] = $dateTimeFormatted;

        $dateTimeObject = new \DateTime($zaakArray['registratiedatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
        $relocationArray['dossier']['entryDateTime'] = $dateTimeFormatted;
        $relocationArray['dossier']['status']['entryDateTime'] = $dateTimeFormatted;

        // Save in gateway
        $intraObjectEntity = new ObjectEntity();
        $intraObjectEntity->setEntity($intraRelocationEntity);

        $intraObjectEntity->hydrate($relocationArray);

        $this->entityManager->persist($intraObjectEntity);
        $this->entityManager->flush();


        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $intraRelocationEntity->getId()->toString(), 'response' => $relocationArray]);

        return $this->data;
    }



    private function createDeceasementObject(array $zaakArray): array
    {
        isset($this->configuration['entities']['Death']) && $deathEntity = $this->entityRepo->find($this->configuration['entities']['Death']);

        if (!isset($deathEntity)) {
            throw new \Exception('Death entity could not be found, check ZgwToVrijbrpAction config');
        }

        $deathArrayObject = [];

        $eigenschappenArray = [];

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            $eigenschappenArray[] = ['naam' => $eigenschap['naam'], 'waarde' => $eigenschap['waarde']];
            switch ($eigenschap['naam']) {
                case 'sub.emailadres':
                    $relocationArray['deceased']['contactInformation']['email'] = $eigenschap['waarde'];
                    continue 2;
                case 'inp.bsn':
                    $relocationArray['deceased']['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'voornamen':
                    $relocationArray['deceased']['firstname'] = $eigenschap['waarde'];
                    continue 2;
                case 'voorvoegselGeslachtsnaam':
                    $relocationArray['deceased']['prefix'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam':
                    $relocationArray['deceased']['lastname'] = $eigenschap['waarde'];
                    continue 2;
                case 'geboortedatum':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = (int) $dateTimeObject->format('ymd');
                    $deathArrayObject['deceased']['birthdate'] = $dateTimeFormatted;
                    continue 2;
                case 'natdood':
                    $relocationArray['deathByNaturalCauses'] = $eigenschap['waarde'] == 'True' ? true : false;
                    continue 2;
                case 'gemeentecode':
                    $relocationArray['municipality']['code'] = $eigenschap['waarde'];
                    continue 2;
                case 'datumoverlijden':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $relocationArray['dateOfDeath'] = $dateTimeFormatted;
                    continue 2;
                case 'tijdoverlijden':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
                    $relocationArray['dateOfDeath'] = $dateTimeFormatted;
                    continue 2;
            }
        }

        if ((isset($zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn']) && $bsn = $zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn'])
            || (isset($zaakArray['rollen'][0]['betrokkeneIdentificatie']['vestigingsNummer']) && $bsn = $zaakArray['rollen'][0]['betrokkeneIdentificatie']['vestigingsNummer'])
        ) {
            $deathArrayObject['declarant']['bsn'] = $bsn;
            $deathArrayObject['deceased']['bsn'] = $bsn;
        }

        $deathArrayObject['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];
        $deathArrayObject['dossier']['dossierId'] = $zaakArray['id'];

        $dateTimeObject = new \DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $deathArrayObject['dossier']['startDate'] = $dateTimeFormatted;

        $dateTimeObject = new \DateTime($zaakArray['registratiedatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
        $deathArrayObject['dossier']['entryDateTime'] = $dateTimeFormatted;
        $deathArrayObject['dossier']['status']['entryDateTime'] = $dateTimeFormatted;

        var_dump(json_encode($deathArrayObject));
        die;

        // Save in gateway
        $deathObjectEntity = new ObjectEntity();
        $deathObjectEntity->setEntity($deathEntity);

        $deathObjectEntity->hydrate($deathArrayObject);

        $this->entityManager->persist($deathObjectEntity);
        $this->entityManager->flush();

        $event = 'commongateway.vrijbrp.death.created' ?? 'commongateway.vrijbrp.foundbody.created';

        $this->objectEntityService->dispatchEvent($event, ['entity' => $deathEntity->getId()->toString(), 'response' => $deathArrayObject]);

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

        // var_dump(json_encode($data));

        $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['response']['zgwZaak']['id']);
        if (!$zaakObjectEntity instanceof ObjectEntity) {
            $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['response']['zgwZaak']['id']);
            if (!$zaakObjectEntity instanceof ObjectEntity) {
                throw new \Exception('Zaak not found with given ID for ZgwToVrijbrpHandler');
            }
        }

        $zaakArray = $zaakObjectEntity->toArray();

        switch ($zaakArray['zaaktype']['identificatie']) {
            case 'B0237':
                return $this->createBirthObject($zaakArray);
            case 'B0366':
                return $this->createRelocationObject($zaakArray);
            case 'B0337':
                return $this->createCommitmentObject($zaakArray);
            case 'B0360':
                return $this->createDeceasementObject($zaakArray);
            default:
                return $this->data;
        }
    }
}
