<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use phpDocumentor\Reflection\Types\This;

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
            throw new Exception('Birth entity could not be found, check ZgwToVrijbrpAction config');
        }

        $birthArray = [
            'declarant' => [],
        ];

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            $childIndex = '';
            $date = $time = new DateTime();
            if (
                in_array(substr_replace($eigenschap['naam'], '', -1), ['voornamen', 'geboortedatum', 'geslachtsaanduiding', 'geboortetijd']) &&
                $eigenschap['naam'] != 'voornamen' && $eigenschap['naam'] != 'geboortedatum' && $eigenschap['naam'] != 'geslachtsaanduiding'
            ) {
                $childIndex = substr($eigenschap['naam'], -1);
                $childIndexInt = intval($childIndex) - 1;
            } elseif ($eigenschap['naam'] == 'voornamen' || $eigenschap['naam'] == 'geboortedatum' || $eigenschap['naam'] == 'geslachtsaanduiding' || $eigenschap == 'geboortedatum') {
                continue;
            }
            switch ($eigenschap['naam']) {
                case 'voornamen'.$childIndex:
                    $birthArray['children'][$childIndexInt]['firstname'] = $eigenschap['waarde'];
                    continue 2;
                case 'geboortedatum'.$childIndex:
                    $date = new \DateTime($eigenschap['waarde']);
                    if (isset($birthArray['children'][$childIndexInt]['birthDateTime'])) {
                        $time = new \DateTime($birthArray['children'][$childIndexInt]['birthDateTime']);
                        $dateTime = new \DateTime($date->format('Y-m-d\T').$time->format('H:i:s'));
                    } else {
                        $dateTime = $date;
                    }
                    $birthArray['children'][$childIndexInt]['birthDateTime'] = $dateTime->format('Y-m-d\TH:i:s');
                    continue 2;
                case 'geboortetijd'.$childIndex:
                    $time = new \DateTime($eigenschap['waarde']);
                    if (isset($birthArray['children'][$childIndexInt]['birthDateTime'])) {
                        $date = new \DateTime($birthArray['children'][$childIndexInt]['birthDateTime']);
                        $dateTime = new \DateTime($date->format('Y-m-d\T').$time->format('H:i:s'));
                    } else {
                        $dateTime = $time;
                    }
                    $birthArray['children'][$childIndexInt]['birthDateTime'] = $dateTime->format('Y-m-d\TH:i:s');
                    continue 2;
                case 'geslachtsaanduiding'.$childIndex:
                    in_array($eigenschap['waarde'], ['MAN', 'WOMAN', 'UNKNOWN']) && $birthArray['children'][$childIndexInt]['gender'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam':
                    $birthArray['nameSelection']['lastname'] = $eigenschap['waarde'];
                    continue 2;
                case 'inp.bsn':
                    $birthArray['mother']['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'relatie':
                    $birthArray['qualificationForDeclaringType'] = $eigenschap['waarde'];
                    continue 2;
            }
        }

        isset($birthArray['children']) && $birthArray['children'] = array_values($birthArray['children']);

        $birthArray['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];
        $birthArray['dossier']['dossierId'] = $zaakArray['identificatie'] ?? $zaakArray['id'];

        $dateTimeObject = new DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $birthArray['dossier']['startDate'] = $dateTimeFormatted;

        $dateTimeObject = new DateTime($zaakArray['registratiedatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
        $birthArray['dossier']['entryDateTime'] = $dateTimeFormatted;
        $birthArray['dossier']['status']['entryDateTime'] = $dateTimeFormatted;

        if (isset($zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn'])) {
            $birthArray['declarant']['bsn'] = $zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn'];
            isset($birthArray['mother']['bsn']) ?: $birthArray['mother']['bsn'] = $zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn'];

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

    private function getInitiatorBsn(array $zaakArray): ?string
    {
        foreach ($zaakArray['rollen'] as $rol) {
            if ($rol['omschrijvingGeneriek'] == 'initiator' && $rol['betrokkeneType'] == 'natuurlijk_persoon') {
                return $rol['betrokkeneIdentificatie']['inpBsn'];
            }
        }

        return null;
    }

    private function createCommitmentObject(array $zaakArray): array
    {
        isset($this->configuration['entities']['Commitment']) && $commitmentEntity = $this->entityRepo->find($this->configuration['entities']['Commitment']);

        if (!isset($commitmentEntity)) {
            throw new Exception('Commitment entity could not be found, check ZgwToVrijbrpAction config');
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

        $date = $time = new DateTime();
        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            switch ($eigenschap['naam']) {
                case 'omschrijving':
                    $commitmentArray['dossier']['description'] = $eigenschap['waarde'];
                    $commitmentArray['dossier']['type']['description'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam1':
                    $commitmentArray['partner1']['nameAfterCommitment']['lastname'] = $eigenschap['waarde'];
                    continue 2;
                case 'inp.bsn':
                    if (isset($commitmentArray['partner1']['bsn'])) {
                        $commitmentArray['partner2']['bsn'] = $eigenschap['waarde'];
                    } else {
                        $commitmentArray['partner1']['bsn'] = $eigenschap['waarde'];
                    }
                    continue 2;
                case 'geselecteerdNaamgebruik':
                    if (isset($commitmentArray['partner1']['nameAfterCommitment']['nameUseType'])) {
                        $commitmentArray['partner2']['nameAfterCommitment']['nameUseType'] = $eigenschap['waarde'];
                    } else {
                        $commitmentArray['partner1']['nameAfterCommitment']['nameUseType'] = $eigenschap['waarde'];
                    }
                    continue 2;
                case 'bsn1':
                    $commitmentArray['witnesses'][1]['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'bsn2':
                    $commitmentArray['witnesses'][1]['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'bsn3':
                    $commitmentArray['witnesses'][1]['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'bsn4':
                    $commitmentArray['witnesses'][1]['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam2':
                    $commitmentArray['partner2']['nameAfterCommitment']['lastname'] = $eigenschap['waarde'];
                    continue 2;
                case 'verbintenisType':
                    in_array($eigenschap['waarde'], ['MARRIAGE', 'GPS']) && $commitmentArray['planning']['commitmentType'] = $eigenschap['waarde'];
                    continue 2;
                case 'verbintenisDatum':
                    $date = new DateTime($eigenschap['waarde']);
                    continue 2;
                case 'verbintenisTijd':
                    $time = new DateTime($eigenschap['waarde']);
                    continue 2;
                case 'naam':
                    $commitmentArray['location']['name'] = $eigenschap['waarde'];
                    continue 2;
                case 'naam1':
                    $commitmentArray['officials']['preferences'][0]['name'] = $eigenschap['waarde'];
                    continue 2;
                case 'naam2':
                    $commitmentArray['officials']['preferences'][1]['name'] = $eigenschap['waarde'];
                    continue 2;
                case 'verzorgdgem':
                    $commitmentArray['witnesses']['numberOfMunicipalWitnesses'] = intval($eigenschap['waarde']);
            }
        }

        $dateTime = new DateTime($date->format('Y-m-d\T').$time->format('H:i:s'));
        $commitmentArray['planning']['commitmentDateTime'] = $dateTime->format('Y-m-d\TH:i:s');

        if (!isset($commitmentArray['partner2']['nameAfterCommitment']['nameUseType'])) {
            $commitmentArray['partner2']['nameAfterCommitment']['nameUseType'] = 'N';
        }
        if (!isset($commitmentArray['partner1']['nameAfterCommitment']['nameUseType'])) {
            $commitmentArray['partner1']['nameAfterCommitment']['nameUseType'] = 'N';
        }

        if (!isset($commitmentArray['partner2']['bsn'])) {
            $commitmentArray['partner2']['bsn'] = $this->getInitiatorBsn($zaakArray);
        }

        isset($commitmentArray['witnesses']['numberOfMunicipalWitnesses']) ?: $commitmentArray['witnesses']['numberOfMunicipalWitnesses'] = 0;

        $commitmentArray['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];
        $commitmentArray['dossier']['dossierId'] = $zaakArray['identificatie'] ?? $zaakArray['id'];

        $dateTimeObject = new DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $commitmentArray['dossier']['startDate'] = $dateTimeFormatted;

        $dateTimeObject = new DateTime($zaakArray['registratiedatum']);
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
        $relocators = [];
        foreach (json_decode($eigenschap['waarde'], true) as $meeverhuizende) {
            switch ($meeverhuizende['rol']) {
                case 'P':
                    $declarationType = 'PARTNER';
                    break;
                case 'K':
                    $declarationType = 'ADULT_CHILD_LIVING_WITH_PARENTS';
                    break;
                case 'I':
                    $declarationType = 'REGISTERED';
                    break;
                default:
                    continue 2;
            }
            $relocators[] = [
                'bsn'             => $meeverhuizende['bsn'],
                'declarationType' => $declarationType,
            ];
        }

        return $relocators;
    }

    /**
     * Adds the objects for living in approval if applicable.
     *
     * @param array       $relocationArray The relocation array to edit
     * @param string      $bsn             The BSN of the initiator
     * @param string|null $bsnHoofdbewoner The BSN of the main occupant
     * @param string|null $wijzeBewoning   The type of habitation
     *
     * @return array
     */
    private function createLiveInObject(array $relocationArray, string $bsn, ?string $bsnHoofdbewoner = null, ?string $wijzeBewoning = null): array
    {
        if (
            $wijzeBewoning &&
            isset($this->configuration['relocationConsentRequired']) &&
            in_array($wijzeBewoning, $this->configuration['relocationConsentRequired'])
        ) {
            $relocationArray['newAddress']['liveIn'] = $bsnHoofdbewoner ?
                [
                    'liveInApplicable' => true,
                    'consent'          => 'PENDING',
                    'consenter'        => [
                        'bsn' => $bsnHoofdbewoner,
                    ],
                ] :
                [
                    'liveInApplicable'  => true,
                    'consent'           => 'APPROVED',
                    'consenter'         => [
                        'bsn' => $bsn,
                    ],
                ];
            $relocationArray['newAddress']['mainOccupant']['bsn'] = $bsnHoofdbewoner ?: $bsn;
        } else {
            $relocationArray['newAddress']['liveIn'] = [
                'liveInApplicable' => false,
            ];
        }

        return $relocationArray;
    }

    private function createRelocationObject(array $zaakArray): array
    {
        isset($this->configuration['entities']['InterRelocation']) && $interRelocationEntity = $this->entityRepo->find($this->configuration['entities']['InterRelocation']);
        isset($this->configuration['entities']['IntraRelocation']) && $intraRelocationEntity = $this->entityRepo->find($this->configuration['entities']['IntraRelocation']);

        if (!isset($interRelocationEntity)) {
            throw new \Exception('IntraRelocation entity could not be found, check the ZgwToVrijbrpAction config');
        }
        if (!isset($intraRelocationEntity)) {
            throw new \Exception('InterRelocation entity could not be found, check the ZgwToVrijbrpAction config');
        }
        if (!isset($this->configuration['gemeentecode'])) {
            throw new \Exception('Municipality code could not be found, check the ZgwToVrijbrpAction config');
        }

        $relocators = [];
        $relocator = [];

        $relocationArray = [];

        $isInterRelocation = false;

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            if ($eigenschap['naam'] == 'meeverhuizende_gezinsleden') {
                $relocators = $this->mapRelocators($eigenschap);
                continue;
            }

            switch ($eigenschap['naam']) {
                case 'bsn':
                    continue 2;
                case 'verhuisdatum':
                    $dateTimeObject = new DateTime($eigenschap['waarde']);
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
                case 'huisletter_nieuw':
                    $relocationArray['newAddress']['houseLetter'] = $eigenschap['waarde'];
                    continue 2;
                case 'emailadres':
                    $relocator['email'] = $eigenschap['waarde'];
                    continue 2;
                case 'aantal_pers_nieuw_adres':
                    $relocationArray['newAddress']['numberOfResidents'] = intval($eigenschap['waarde']);
                    continue 2;
                case 'gemeentecode':
                    $relocationArray['previousMunicipality']['code'] = $eigenschap['waarde'];
                    if ($eigenschap['waarde'] !== $this->configuration['gemeentecode']) {
                        $relocationArray['newAddress']['municipality']['code'] = $this->configuration['gemeentecode'];
                        $isInterRelocation = true;
                    }
                    continue 2;
                case 'wijze_bewoning':
                    $wijzeBewoning = $eigenschap['waarde'];
                    continue 2;
                case 'bsn_hoofdbewoner':
                    $bsnHoofdbewoner = $eigenschap['waarde'];
                    continue 2;
                case 'emailadres':
                    $email = $eigenschap['waarde'];
                    continue 2;
                case 'telefoonnummer':
                    $telephone = $eigenschap['waarde'];
                    continue 2;

            }
        }

        if (isset($zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn']) && $bsn = $zaakArray['rollen'][0]['betrokkeneIdentificatie']['inpBsn']) {
            $relocationArray['declarant']['bsn'] = $bsn;
            $relocationArray['declarant']['contactInformation']['email'] = $email ?? null;
            $relocationArray['declarant']['contactInformation']['telephoneNumber'] = $telephone ?? null;
            $relocationArray['newAddress']['mainOccupant']['bsn'] = $bsn;
            $relocationArray = $this->createLiveInObject($relocationArray, $bsn, $bsnHoofdbewoner ?? null, $wijzeBewoning ?? null);
        }
        $relocationArray['newAddress']['addressFunction'] = 'LIVING_ADDRESS';

        $relocationArray['relocators'] = $relocators;
        $relocationArray['relocators'][] = array_merge($relocationArray['newAddress']['mainOccupant'], ['declarationType' => 'REGISTERED']);

        $relocationArray['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];
        $relocationArray['dossier']['dossierId'] = $zaakArray['identificatie'] ?? $zaakArray['id'];

        $dateTimeObject = new DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $relocationArray['dossier']['startDate'] = $dateTimeFormatted;

        // Save in gateway
        $relocationObjectEntity = new ObjectEntity();
        $relocationObjectEntity->setEntity($isInterRelocation ? $interRelocationEntity : $intraRelocationEntity);

        $relocationObjectEntity->hydrate($relocationArray);

        $this->entityManager->persist($relocationObjectEntity);
        $this->entityManager->flush();

        $event = 'commongateway.vrijbrp.intrarelocation.created';
        if ($isInterRelocation === true) {
            $event = 'commongateway.vrijbrp.interrelocation.created';
        }

        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $relocationObjectEntity->getEntity()->getId()->toString(), 'response' => $relocationArray], $event);
        $this->data['response']['dossier'] = $relocationArray;

        return $this->data;
    }

    private function createDeceasementObject(array $zaakArray): array
    {
        isset($this->configuration['entities']['Death']) && $deathEntity = $this->entityRepo->find($this->configuration['entities']['Death']);

        if (!isset($deathEntity)) {
            throw new Exception('Death entity could not be found, check ZgwToVrijbrpAction config');
        }

        $deathArrayObject = [];

        $eigenschappenArray = [];

        $event = 'commongateway.vrijbrp.death.created';

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            $extractIndex = '';
            if (in_array(substr_replace($eigenschap['naam'], '', -1), ['amount', 'code'])) {
                $extractIndex = substr($eigenschap['naam'], -1);
            }
            $eigenschappenArray[] = ['naam' => $eigenschap['naam'], 'waarde' => $eigenschap['waarde']];
            switch ($eigenschap['naam']) {
                case 'sub.emailadres':
                    $deathArrayObject['correspondence']['email'] = $eigenschap['waarde'];
                    continue 2;
                case 'inp.bsn':
                    $deathArrayObject['deceased']['bsn'] = $eigenschap['waarde'];
                    continue 2;
                case 'voornamen':
                    $deathArrayObject['deceased']['firstname'] = $eigenschap['waarde'];
                    continue 2;
                case 'voorvoegselGeslachtsnaam':
                    $deathArrayObject['deceased']['prefix'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam':
                    $deathArrayObject['deceased']['lastname'] = $eigenschap['waarde'];
                    continue 2;
                case 'geboortedatum':
                    $deathArrayObject['deceased']['birthdate'] = (int) $eigenschap['waarde'];
                    continue 2;
                case 'natdood':
                    $deathArrayObject['deathByNaturalCauses'] = $eigenschap['waarde'] == 'True' ? true : false;
                    continue 2;
                case 'gemeentecode':
                    $deathArrayObject['municipality']['code'] = $eigenschap['waarde'];
                    continue 2;
                case 'datumoverlijden':
                    $dateTimeObject = new DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $deathArrayObject['dateOfDeath'] = $dateTimeFormatted;
                    continue 2;
                case 'tijdoverlijden':
                    $dateTimeObject = new DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('H:i');
                    $deathArrayObject['timeOfDeath'] = $dateTimeFormatted;
                    continue 2;
                case 'datumlijkvinding':
                    $dateTimeObject = new DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $deathArrayObject['dateOfFinding'] = $dateTimeFormatted;
                    continue 2;
                case 'tijdlijkvinding':
                    $dateTimeObject = new DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('H:i');
                    $deathArrayObject['timeOfFinding'] = $dateTimeFormatted;
                    continue 2;
                case 'type':
                    in_array($eigenschap['waarde'], ['BURIAL_CREMATION', 'DISSECTION']) && $deathArrayObject['funeralServices']['serviceType'] = $eigenschap['waarde'];
                    continue 2;
                case 'datumuitvaart':
                    $dateTimeObject = new DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $deathArrayObject['funeralServices']['date'] = $dateTimeFormatted;
                    continue 2;
                case 'tijduitvaart':
                    $dateTimeObject = new DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('H:i');
                    $deathArrayObject['funeralServices']['time'] = $dateTimeFormatted;
                    continue 2;
                case 'amount'.$extractIndex:
                    $deathArrayObject['extracts'][$extractIndex]['amount'] = (int) $eigenschap['waarde'];
                    continue 2;
                case 'code'.$extractIndex:
                    $deathArrayObject['extracts'][$extractIndex]['code'] = $eigenschap['waarde'];
                    continue 2;
                case 'datum':
                    $dateTimeObject = new DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $deathArrayObject['funeralServices']['date'] = $dateTimeFormatted;
                    continue 2;
                case 'buitenbenelux':
                    $deathArrayObject['funeralServices']['outsideBenelux'] = $eigenschap['waarde'] == 'True' ? true : false;
                    continue 2;
                case 'communicatietype':
                    in_array($eigenschap['waarde'], ['EMAIL', 'POST']) && $deathArrayObject['correspondence']['communicationType'] = $eigenschap['waarde'];
                    continue 2;
                case 'landcode':
                    $deathArrayObject['funeralServices']['countryOfDestination']['code'] = $eigenschap['waarde'];
                    continue 2;
                case 'plaatsbest':
                    $deathArrayObject['funeralServices']['placeOfDestination'] = $eigenschap['waarde'];
                    continue 2;
                case 'viabest':
                    $deathArrayObject['funeralServices']['via'] = $eigenschap['waarde'];
                    continue 2;
                case 'voertuigbest':
                    $deathArrayObject['funeralServices']['transportation'] = $eigenschap['waarde'];
                    continue 2;
                case 'contact.naam':
                    $deathArrayObject['correspondence']['name'] = $eigenschap['waarde'];
                    continue 2;
                case 'handelsnaam':
                    $deathArrayObject['correspondence']['organization'] = $eigenschap['waarde'];
                    continue 2;
                case 'aoa.huisnummer':
                    $deathArrayObject['correspondence']['houseNumber'] = (int) $eigenschap['waarde'];
                    continue 2;
                case 'aoa.huisletter':
                    !empty($eigenschap['waarde']) && $deathArrayObject['correspondence']['houseNumberLetter'] = $eigenschap['waarde'];
                    continue 2;
                case 'aoa.huisnummertoevoeging':
                    !empty($eigenschap['waarde']) && $deathArrayObject['correspondence']['houseNumberAddition'] = $eigenschap['waarde'];
                    continue 2;
                case 'aoa.postcode':
                    $deathArrayObject['correspondence']['postalCode'] = $eigenschap['waarde'];
                    continue 2;
                case 'wpl.woonplaatsnaam':
                    $deathArrayObject['correspondence']['residence'] = $eigenschap['waarde'];
                    continue 2;
                case 'aangevertype':
                    !empty($eigenschap['waarde']) && $event = 'commongateway.vrijbrp.foundbody.created';
                    continue 2;
                case 'contact.inp.bsn':
                    $deathArrayObject['declarant']['bsn'] = $eigenschap['waarde'];
                    continue 2;
            }
        }

        isset($deathArrayObject['extracts']) && $deathArrayObject['extracts'] = array_values($deathArrayObject['extracts']);

        $deathArrayObject['funeralServices']['causeOfDeathType'] = $deathArrayObject['deathByNaturalCauses'] == true ? 'NATURAL_CAUSES' : 'NON_CONTAGIOUS_DISEASE';

        $deathArrayObject['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];
        $deathArrayObject['dossier']['dossierId'] = $zaakArray['identificatie'] ?? $zaakArray['id'];

        $dateTimeObject = new DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $deathArrayObject['dossier']['startDate'] = $dateTimeFormatted;

        $dateTimeObject = new DateTime($zaakArray['registratiedatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
        $deathArrayObject['dossier']['entryDateTime'] = $dateTimeFormatted;
        $deathArrayObject['dossier']['status']['entryDateTime'] = $dateTimeFormatted;

        // Save in gateway
        $deathObjectEntity = new ObjectEntity();
        $deathObjectEntity->setEntity($deathEntity);

        $deathObjectEntity->hydrate($deathArrayObject);

        $this->entityManager->persist($deathObjectEntity);
        $this->entityManager->flush();

        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $deathEntity->getId()->toString(), 'response' => $deathArrayObject], $event);

        return $this->data;
    }

    /**
     * Creates a VrijRBP Soap Zaakgegevens array with the data of the zgwZaak.
     *
     * @param ObjectEntity $zaakObjectEntity
     * @param string|null  $type
     *
     * @return array zaakgegevens
     */
    public function createVrijBrpSoapZaakgegevens(ObjectEntity $zaakObjectEntity): array
    {
        return [
            'zaakId'      => $zaakObjectEntity->getValue('identificatie') ?? $zaakObjectEntity->getId()->toString(),
            'bron'        => 'eDienst',
            'leverancier' => $zaakObjectEntity->getValue('opdrachtgevendeOrganisatie'),
            //            'medewerker' => $zaakObjectEntity->getValue('identificatie'),
            'datumAanvraag' => $zaakObjectEntity->getValue('registratiedatum'),
            'toelichting'   => $zaakObjectEntity->getValue('toelichting'),
        ];
    }

    /**
     * Creates a VrijRBP Soap Contactgegevens array with the data of the zgwZaak.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @return array contactgegevens
     */
    public function createVrijBrpSoapContactgegevens(ObjectEntity $zaakObjectEntity): array
    {
        return [
            'emailadres'           => null,
            'telefoonnummerPrive'  => null,
            'telefoonnummerWerk'   => null,
            'telefoonnummerMobiel' => null,
        ];
    }

    /**
     * This function gets the zaakEigenschappen from the zgwZaak with the given properties (simXml elementen and Stuf extraElementen).
     *
     * @param ObjectEntity $zaakObjectEntity
     * @param array        $properties
     *
     * @return array zaakEigenschappen
     */
    public function getZaakEigenschappen(ObjectEntity $zaakObjectEntity, array $properties): array
    {
        $zaakEigenschappen = [];
        foreach ($zaakObjectEntity->getValue('eigenschappen') as $eigenschap) {
            if (key_exists($eigenschap->getValue('naam'), $properties)) {
                $zaakEigenschappen[$eigenschap->getValue('naam')] = $eigenschap->getValue('waarde');
            }
        }

        return $zaakEigenschappen;
    }

    /**
     * This function gets the bsn of the rol with the betrokkeneType set as natuurlijk_persoon.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @return string bsn of the natuurlijk_persoon
     */
    public function getRollen(ObjectEntity $zaakObjectEntity): ?string
    {
        foreach ($zaakObjectEntity->getValue('rollen') as $rol) {
            if ($rol->getValue('betrokkeneType') === 'natuurlijk_persoon') {
                $betrokkeneIdentificatie = $rol->getValue('betrokkeneIdentificatie');

                return $betrokkeneIdentificatie->getValue('inpBsn');
            }
        }

        return null;
    }

    /**
     * Creates a VrijRBP Soap object with the given entity and soap array.
     *
     * @param Entity $requestEntity
     * @param array  $soapArray
     *
     * @throws Exception
     *
     * @return ObjectEntity Soap object
     */
    public function createSoapObject(Entity $requestEntity, array $soapArray): ObjectEntity
    {
        $soapObject = new ObjectEntity($requestEntity);
        $soapObject->hydrate($soapArray);
        $this->entityManager->persist($soapObject);
        $this->entityManager->flush();

        return $soapObject;
    }

    /**
     * Creates a VrijRBP Soap Emigration from a ZGW Zaak.
     *
     * @param ObjectEntity      $zaakObjectEntity
     * @param ObjectEntity|null $zaakDocumentObjectEntity
     *
     * @throws Exception
     *
     * @return array $this->dataData which we entered the function with
     */
    public function zgwEmigrationToVrijBrpSoap(ObjectEntity $zaakObjectEntity): array
    {
        $properties = [
            'bsn'                        => null,
            'datumVertrek'               => null,
            'landcode'                   => null,
            'adresregel1'                => null,
            'adresregel2'                => null,
            'meeverhuizende_gezinsleden' => null,
        ];

        $emigratieaanvraagRequestEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['emigratieaanvraagRequestEntityId']);

        $soapEmigrationArray['zaakgegevens'] = $this->createVrijBrpSoapZaakgegevens($zaakObjectEntity);
        $soapEmigrationArray['contactgegevens'] = $this->createVrijBrpSoapContactgegevens($zaakObjectEntity);

        $zaakEigenschappen = $this->getZaakEigenschappen($zaakObjectEntity, $properties);
        $bsn = $this->getRollen($zaakObjectEntity);

        $meeverhuizende_gezinsleden = [];
        if (key_exists('meeverhuizende_gezinsleden', $zaakEigenschappen)) {
            $meeverhuizende_gezinsleden = json_decode($zaakEigenschappen['meeverhuizende_gezinsleden'], true);
        }

        $meeEmigranten = [];

        $meeEmigranten[] = [
            'burgerservicenummer'  => key_exists('bsn', $zaakEigenschappen) && $zaakEigenschappen['bsn'] !== null ? $zaakEigenschappen['bsn'] : $bsn,
            'omschrijvingAangifte' => 'G',
            'duur'                 => 'l',
        ];

        foreach ($meeverhuizende_gezinsleden as $meeverhuizende_gezinslid) {
            if (!$meeverhuizende_gezinslid['bsn']) {
                continue;
            }
            $meeEmigranten[] = [
                'burgerservicenummer'  => key_exists('bsn', $meeverhuizende_gezinslid) ? $meeverhuizende_gezinslid['bsn'] : null,
                'omschrijvingAangifte' => key_exists('rol', $meeverhuizende_gezinslid) ? $meeverhuizende_gezinslid['rol'] : null,
                'duur'                 => 'l',
            ];
        }

        $adresBuitenland = [
            'adresBuitenland1' => key_exists('adresregel1', $zaakEigenschappen) ? $zaakEigenschappen['adresregel1'] : null,
            'adresBuitenland2' => key_exists('adresregel2', $zaakEigenschappen) ? $zaakEigenschappen['adresregel2'] : null,
            'adresBuitenland3' => key_exists('adresregel3', $zaakEigenschappen) ? $zaakEigenschappen['adresregel3'] : null,
        ];

        $soapEmigrationArray['aanvraaggegevens'] = [
            'burgerservicenummerAanvrager' => key_exists('bsn', $zaakEigenschappen) && $zaakEigenschappen['bsn'] !== null ? $zaakEigenschappen['bsn'] : $bsn,
            'emigratiedatum'               => key_exists('datumVertrek', $zaakEigenschappen) ? $zaakEigenschappen['datumVertrek'] : null,
            'landcodeEmigratie'            => key_exists('landcode', $zaakEigenschappen) ? $zaakEigenschappen['landcode'] : null,
            'adresBuitenland'              => $adresBuitenland, // object
            'meeEmigranten'                => $meeEmigranten,
        ];

        $soapEmigration = $this->createSoapObject($emigratieaanvraagRequestEntity, $soapEmigrationArray);
        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $emigratieaanvraagRequestEntity->getId()->toString(), 'response' => $soapEmigration->toArray()], 'soap.object.handled');
        $this->data['response']['soapZaak'] = $soapEmigration->toArray();

        return $this->data;
    }

    /**
     * Creates a VrijRBP Soap Confidentiality from a ZGW Zaak.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @throws Exception
     *
     * @return array $this->dataData which we entered the function with
     */
    public function zgwConfidentialityToVrijBrpSoap(ObjectEntity $zaakObjectEntity): array
    {
        $properties = [
            'bsn'                => null,
            'bsn_geheimhouding'  => null,
            'code_geheimhouding' => null,
        ];

        $geheimhoudingaanvraagRequestEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['geheimhoudingaanvraagRequestEntityId']);

        $soapConfidentialityArray['zaakgegevens'] = $this->createVrijBrpSoapZaakgegevens($zaakObjectEntity);
        $soapConfidentialityArray['contactgegevens'] = $this->createVrijBrpSoapContactgegevens($zaakObjectEntity);

        $zaakEigenschappen = $this->getZaakEigenschappen($zaakObjectEntity, $properties);
        $bsn = $this->getRollen($zaakObjectEntity);

        $geheimhoudingBetrokkenen[] = [
            'burgerservicenummer' => key_exists('bsn_geheimhouding', $zaakEigenschappen) ? $zaakEigenschappen['bsn_geheimhouding'] : null,
            'codeGeheimhouding'   => key_exists('code_geheimhouding', $zaakEigenschappen) ? "{$zaakEigenschappen['code_geheimhouding']}" : "0",
        ];

        $soapConfidentialityArray['aanvraaggegevens'] = [
            'burgerservicenummerAanvrager' => $bsn,
            'geheimhoudingBetrokkenen'     => $geheimhoudingBetrokkenen,
        ];

        $soapConfidentiality = $this->createSoapObject($geheimhoudingaanvraagRequestEntity, $soapConfidentialityArray);
        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $geheimhoudingaanvraagRequestEntity->getId()->toString(), 'response' => $soapConfidentiality->toArray()], 'soap.object.handled');

        $this->data['response']['soapZaak'] = $soapConfidentiality->toArray();

        return $this->data;
    }

    /**
     * Creates a VrijRBP Soap Extract from a ZGW Zaak.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @throws Exception
     *
     * @return array $this->dataData which we entered the function with
     */
    public function zgwExtractToVrijBrpSoap(ObjectEntity $zaakObjectEntity): array
    {
        $properties = [
            'bsn'          => null,
            'code'         => null,
            'omschrijving' => null,
            'uittreksel'   => null,
        ];

        $uittrekselaanvraagRequestEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['uittrekselaanvraagRequestEntityId']);

        $soapExtractArray['zaakgegevens'] = $this->createVrijBrpSoapZaakgegevens($zaakObjectEntity);
        $soapExtractArray['contactgegevens'] = $this->createVrijBrpSoapContactgegevens($zaakObjectEntity);

        $zaakEigenschappen = $this->getZaakEigenschappen($zaakObjectEntity, $properties);
        $bsn = $this->getRollen($zaakObjectEntity);

        $uittreksels = [];
        if (key_exists('uittreksel', $zaakEigenschappen)) {
            $uittreksels = json_decode($zaakEigenschappen['uittreksel'], true);
        }

        $uittrekselBetrokkenen = [];
        foreach ($uittreksels as $uittreksel) {
            $uittrekselBetrokkenen[] = [
                'burgerservicenummer' => key_exists('bsn', $uittreksel) ? $uittreksel['bsn'] : null,
                'uittrekselcode'      => key_exists('code', $uittreksel) ? $uittreksel['code'] : null,
                'indicatieGratis'     => 'false',
            ];
        }

        $soapExtractArray['aanvraaggegevens'] = [
            'burgerservicenummerAanvrager' => key_exists('bsn', $zaakEigenschappen) && $zaakEigenschappen['bsn'] !== null ? $zaakEigenschappen['bsn'] : $bsn,
            'uittrekselBetrokkenen'        => $uittrekselBetrokkenen,
        ];

        $soapExtract = $this->createSoapObject($uittrekselaanvraagRequestEntity, $soapExtractArray);
        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $uittrekselaanvraagRequestEntity->getId()->toString(), 'response' => $soapExtract->toArray()], 'soap.object.handled');

        $this->data['response']['soapZaak'] = $soapExtract->toArray();

        return $this->data;
    }

    /**
     * Creates a VrijRBP Soap Naming from a ZGW Zaak.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @throws Exception
     *
     * @return array $this->data Data which we entered the function with
     */
    public function zgwNamingToVrijBrpSoap(ObjectEntity $zaakObjectEntity): array
    {
        $properties = [
            'bsn'                     => null,
            'gemeentecode'            => null,
            'sub.telefoonnummer'      => null,
            'sub.emailadres'          => null,
            'geselecteerdNaamgebruik' => null,
        ];

        $naamgebruikaanvraagRequestEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['naamgebruikaanvraagRequestEntityId']);

        $soapNamingArray['zaakgegevens'] = $this->createVrijBrpSoapZaakgegevens($zaakObjectEntity, 'naming');
        $soapNamingArray['contactgegevens'] = $this->createVrijBrpSoapContactgegevens($zaakObjectEntity);

        $zaakEigenschappen = $this->getZaakEigenschappen($zaakObjectEntity, $properties);
        $bsn = $this->getRollen($zaakObjectEntity);

        $naamgebruikBetrokkenen[] = [
            'burgerservicenummer' => $bsn,
            'codeNaamgebruik'     => $zaakEigenschappen['geselecteerdNaamgebruik'],
        ];

        $soapNamingArray['aanvraaggegevens'] = [
            'burgerservicenummerAanvrager' => $bsn,
            'naamgebruikBetrokkenen'       => $naamgebruikBetrokkenen,
        ];

        $soapNaming = $this->createSoapObject($naamgebruikaanvraagRequestEntity, $soapNamingArray);
        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $naamgebruikaanvraagRequestEntity->getId()->toString(), 'response' => $soapNaming->toArray()], 'soap.object.handled');

        $this->data['response']['soapZaak'] = $soapNaming->toArray();

        return $this->data;
    }

    /**
     * Maps dossier status to ztc statustype.
     *
     * @param array $dossierArrayObject Dossier array object.
     *
     * @return array StatusType array
     */
    private function mapStatusType($dossierArrayObject): array
    {
        return [
            'omschrijving'         => $dossierArrayObject['embedded']['status']['code'],
            'omschrijvingGeneriek' => $dossierArrayObject['embedded']['status']['description'],
            'isEindstatus'         => $dossierArrayObject['embedded']['status']['endStatus'] ?? false,
        ];
    }

    /**
     * Finds ZaakType statustype and if found creates a status.
     *
     * @param array $zaakTypeArrayObject ZaakType array object.
     * @param array $dossierArrayObject  Dossier array object.
     *
     * @return ?array Status array
     */
    private function findAndCreateStatus($zaakTypeArrayObject, $dossierArrayObject): ?array
    {
        foreach ($zaakTypeArrayObject['statustypen'] as $statusType) {
            if ($statusType['omschrijving'] == $dossierArrayObject['embedded']['status']['code']) {
                return [
                    'statustype'       => $this->entityManager->find('App:ObjectEntity', $statusType['id']),
                    'datumStatusGezet' => $dossierArrayObject['embedded']['status']['entryDateTime'],
                ];
            }
        }

        return null;
    }

    /**
     * Creates or updates a ZGW Zaak from a VrijBRP dossier with the use of mapping.
     *
     * @param array $data          Data from the handler where the vrijbrp dossier is in.
     * @param array $configuration Configuration from the Action where entity id's are stored in.
     *
     * @throws Exception
     *
     * @return array $this->data Data which we entered the function with
     */
    public function vrijbrpToZgwHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        isset($this->configuration['entities']['Zaak']) && $zaakEntity = $this->entityRepo->find($this->configuration['entities']['Zaak']);
        isset($this->configuration['entities']['ZaakType']) && $zaakTypeEntity = $this->entityRepo->find($this->configuration['entities']['ZaakType']);

        if (!isset($zaakEntity)) {
            throw new Exception('Zaak entity could not be found, check VrijbrpToZgwAction config');
        }

        $dossierArrayObject = $this->data['response'];

        $zaakObjectEntity = $this->objectEntityRepo->findOneBy(['entity' => $zaakEntity, 'externalId' => $dossierArrayObject['dossierId']]);
        $zaaktypeValues = $this->entityManager->getRepository('App:Value')->findBy(['stringValue' => $dossierArrayObject['embedded']['type']['code']]);
        foreach ($zaaktypeValues as $zaaktypeValue) {
            if ($zaaktypeValue->getObjectEntity()->getEntity()->getId()->toString() == $this->configuration['entities']['ZaakType']) {
                $zaakTypeObjectEntity = $zaaktypeValue->getObjectEntity();
            }
        }

        if (!isset($zaakTypeObjectEntity) || !$zaakTypeObjectEntity instanceof ObjectEntity) {
            // Create zaakType
            $zaakTypeObjectEntity = new ObjectEntity($zaakTypeEntity);
            $zaakTypeArray = [
                'identificatie' => $dossierArrayObject['embedded']['type']['code'],
                'omschrijving'  => $dossierArrayObject['embedded']['type']['description'],
                'statustypen'   => [
                    $this->mapStatusType($dossierArrayObject),
                ],
            ];
            $zaakTypeObjectEntity->hydrate($zaakTypeArray);
            $this->entityManager->persist($zaakTypeObjectEntity);
            $this->entityManager->flush();
            $zaakTypeObjectEntity = $this->entityManager->find('App:ObjectEntity', $zaakTypeObjectEntity->getId()->toString());
        }

        $zaakTypeArrayObject = $zaakTypeObjectEntity->toArray();

        !$zaakObjectEntity instanceof ObjectEntity && $zaakObjectEntity = new ObjectEntity($zaakEntity);
        $zaakArrayObject = [
            'zaaktype'         => $zaakTypeObjectEntity,
            'identificatie'    => $dossierArrayObject['dossierId'],
            'registratiedatum' => $dossierArrayObject['entryDateTime'],
            'startdatum'       => $dossierArrayObject['startDate'],
        ];

        $status = $this->findAndCreateStatus($zaakTypeArrayObject, $dossierArrayObject);

        if (!isset($status)) {
            $zaakTypeArrayObject['statustypen'][] = $this->mapStatusType($dossierArrayObject);
            $zaakTypeObjectEntity->hydrate($zaakTypeArrayObject);
            $this->entityManager->persist($zaakTypeObjectEntity);
            $this->entityManager->flush();
            $zaakTypeObjectEntity = $this->entityManager->find('App:ObjectEntity', $zaakTypeObjectEntity->getId()->toString());
            $zaakTypeArrayObject = $zaakTypeObjectEntity->toArray();
            $status = $this->findAndCreateStatus($zaakTypeArrayObject, $dossierArrayObject);
        }

        $zaakArrayObject['status'] = $status;
        $zaakObjectEntity->hydrate($zaakArrayObject);

        $this->entityManager->persist($zaakObjectEntity);
        $this->entityManager->flush();

        $zaakArrayObject = $zaakObjectEntity->toArray();

        $this->data['response'] = $zaakArrayObject;

        return $this->data;
    }

    /**
     * @param array  $zgwDocument
     * @param string $type
     *
     * @throws Exception
     *
     * @return void
     */
    public function createVrijBrpDocumenten(array $zgwDocument, string $type): void
    {
        $zaakInformatieObjectEntity = $this->entityManager->find('App:Entity', $this->configuration['zaakInformatieObjectEntityId']);
        $zaakDocumentObjectEntity = $this->entityManager->find('App:ObjectEntity', $zgwDocument['id']);

        $zaakInformatieObjecten = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($zaakInformatieObjectEntity, ['informatieobject' => $zaakDocumentObjectEntity->getId()->toString()]);
        $zaakInformatieObject = count($zaakInformatieObjecten) == 1 ? $zaakInformatieObjecten[0] : null;
        if ($zaakDocumentObjectEntity instanceof ObjectEntity && $zaakInformatieObject->getValue('zaak')) {
            $vrijBrpDossierEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['vrijBrpDossierEntityId']);

            $date = new DateTime($zaakDocumentObjectEntity->getValue('creatiedatum'));
            $dateTimeFormatted = $date->format('Y-m-d\TH:i:s');

            $vrijBrpDossierArray = [
                'title'         => $zaakDocumentObjectEntity->getValue('titel'),
                'filename'      => $zaakDocumentObjectEntity->getValue('bestandsnaam') ?? $zaakDocumentObjectEntity->getValue('titel'),
                'entryDateTime' => $dateTimeFormatted,
                'content'       => $zaakDocumentObjectEntity->getValue('inhoud'),
                'dossier'       => $zaakInformatieObject->getValue('zaak')->getValue('identificatie') ?? $zaakInformatieObject->getValue('zaak')->getId()->toString(),
            ];

            $this->createSoapObject($vrijBrpDossierEntity, $vrijBrpDossierArray);
            $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $vrijBrpDossierEntity->getId()->toString(), 'response' => $vrijBrpDossierArray], $type);
        }
    }

    /**
     * @param array $data
     * @param array $configuration
     *
     * @throws Exception
     *
     * @return void
     */
    public function zgwDocumentToVrijbrpHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;
        $this->createVrijBrpDocumenten($this->data['response']['zgwDocument'], 'vrijBrpApi.document.handled');

        return $this->data;
    }

    /**
     * Creates a vrijbrp object from a ZGW Zaak with the use of mapping.
     *
     * @param array $data          Data from the handler where the vrijbrp casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @throws Exception
     *
     * @return array $this->data Data which we entered the function with
     */
    public function zgwToVrijbrpHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if (!isset($this->data['response']['zgwZaak']['id'])) {
            throw new Exception('Zaak ID not given for ZgwToVrijbrpHandler');
        }

        $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $this->data['response']['zgwZaak']['id']);
        if (!$zaakObjectEntity instanceof ObjectEntity) {
            throw new Exception('Zaak not found with given ID for ZgwToVrijbrpHandler');
        }

        $zaakArray = $zaakObjectEntity->toArray();

        switch ($zaakArray['zaaktype']['identificatie']) {
            case 'B0237':
                return $this->createBirthObject($zaakArray);
            case 'B0366':
                $this->data = $this->createRelocationObject($zaakArray);
                 foreach ($this->data['response']['zgwDocumenten'] as $zgwDocument) {
                     $this->createVrijBrpDocumenten($zgwDocument, 'vrijBrpApi.dossier.handled');
                 }

                return $this->data;
            case 'B0337':
                return $this->createCommitmentObject($zaakArray);
            case 'B0360':
                return $this->createDeceasementObject($zaakArray);
            case 'B1425':
                $this->data = $this->zgwEmigrationToVrijBrpSoap($zaakObjectEntity);
                foreach ($this->data['response']['zgwDocumenten'] as $zgwDocument) {
                    $this->createVrijBrpDocumenten($zgwDocument, 'vrijBrp.dossier.handled');
                }

                return $this->data;
            case 'B0328':
                $this->data = $this->zgwConfidentialityToVrijBrpSoap($zaakObjectEntity);
                foreach ($this->data['response']['zgwDocumenten'] as $zgwDocument) {
                    $this->createVrijBrpDocumenten($zgwDocument, 'vrijBrp.dossier.handled');
                }

                return $this->data;
            case 'B0255':
                $this->data = $this->zgwExtractToVrijBrpSoap($zaakObjectEntity);
                foreach ($this->data['response']['zgwDocumenten'] as $zgwDocument) {
                    $this->createVrijBrpDocumenten($zgwDocument, 'vrijBrp.dossier.handled');
                }

                return $this->data;
            case 'B0348':
                return $this->zgwNamingToVrijBrpSoap($zaakObjectEntity);
            default:
                return $this->data;
        }
    }
}
