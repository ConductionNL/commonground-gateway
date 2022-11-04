<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

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
                case 'emailadres':
                    $relocator['email'] = $eigenschap['waarde'];
                    continue 2;
                case 'aantal_pers_nieuw_adres':
                    $relocationArray['newAddress']['numberOfResidents'] = intval($eigenschap['waarde']);
                    continue 2;
                case 'gemeentecode':
                    $relocationArray['newAddress']['municipality']['code'] = $eigenschap['waarde'];
                    if ($eigenschap['waarde'] !== $this->configuration['gemeentecode']) {
                        $relocationArray['previousMunicipality']['code'] = $this->configuration['gemeentecode'];
                        $isInterRelocation = true;
                    }
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
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $deathArrayObject['dateOfDeath'] = $dateTimeFormatted;
                    continue 2;
                case 'tijdoverlijden':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('H:i');
                    $deathArrayObject['timeOfDeath'] = $dateTimeFormatted;
                    continue 2;
                case 'type':
                    in_array($eigenschap['waarde'], ['BURIAL_CREMATION', 'DISSECTION']) && $deathArrayObject['funeralServices']['serviceType'] = $eigenschap['waarde'];
                    continue 2;
                case 'amount' . $extractIndex:
                    $deathArrayObject['extracts'][$extractIndex]['amount'] = (int) $eigenschap['waarde'];
                    continue 2;
                case 'code' . $extractIndex:
                    $deathArrayObject['extracts'][$extractIndex]['code'] = $eigenschap['waarde'];
                    continue 2;
                case 'datum':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                    $deathArrayObject['funeralServices']['date'] = $dateTimeFormatted;
                    continue 2;
                case 'buitenbenelux':
                    $deathArrayObject['funeralServices']['outsideBenelux'] = $eigenschap['waarde'] == 'True' ? true : false;
                    continue 2;
                case 'communicatietype':
                    in_array($eigenschap['waarde'], ['EMAIL', 'POST']) && $deathArrayObject['correspondence']['communicationType'] = $eigenschap['waarde'];
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
                case 'lnd.landcode':
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
        $deathArrayObject['dossier']['dossierId'] = $zaakArray['id'];

        $dateTimeObject = new \DateTime($zaakArray['startdatum']);
        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
        $deathArrayObject['dossier']['startDate'] = $dateTimeFormatted;

        $dateTimeObject = new \DateTime($zaakArray['registratiedatum']);
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
     * Creates a VrijRBP Birth from a ZGW Zaak with the use of mapping.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @return array $this->data Data which we entered the function with
     */
    public function createVrijBrpSoapZaakgegevens(ObjectEntity $zaakObjectEntity): array
    {
        return [
            'zaakId'      => $zaakObjectEntity->getValue('identificatie'),
            'bron'        => $zaakObjectEntity->getValue('omschrijving'),
            'leverancier' => $zaakObjectEntity->getValue('opdrachtgevendeOrganisatie'),
            //            'medewerker' => $zaakObjectEntity->getValue('identificatie'),
            'datumAanvraag' => $zaakObjectEntity->getValue('registratiedatum'),
            'toelichting'   => $zaakObjectEntity->getValue('toelichting'),
        ];
    }

    /**
     * Creates a VrijRBP Birth from a ZGW Zaak with the use of mapping.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @return array $this->data Data which we entered the function with
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
     * Creates a VrijRBP Birth from a ZGW Zaak with the use of mapping.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @throws Exception
     *
     * @return array $this->data Data which we entered the function with
     */
    public function zgwEmigrationToVrijBrpSoap(ObjectEntity $zaakObjectEntity): array
    {
        $properties = [
            'bsn'                        => 'burgerservicenummerAanvrager',
            'datumVertrek'               => 'emigratiedatum',
            'landcode'                   => 'landcodeEmigratie',
            'adresregel1'                => 'adresBuitenland',
            'adresregel2'                => null,
            'meeverhuizende_gezinsleden' => 'meeEmigranten',
        ];
        $soapVrijBrpEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($this->configuration['soapVrijBrpEntityId']);

        $soapEmigrationArray['zaakgegevens'] = $this->createVrijBrpSoapZaakgegevens($zaakObjectEntity);
        $soapEmigrationArray['contactgegevens'] = $this->createVrijBrpSoapContactgegevens($zaakObjectEntity);

        $zaakEigenschappen = [];
        foreach ($zaakObjectEntity->getValue('eigenschappen') as $eigenschap) {
            if (key_exists($eigenschap->getValue('naam'), $properties)) {
                //                var_dump($eigenschap->getValue('naam'));
                $zaakEigenschappen[$eigenschap->getValue('naam')] = $eigenschap;
            }
        }

        //        foreach ($properties as $key => $value ) {
        //            if (key_exists($key, $zaakEigenschappen)){
        //                var_dump("joooo");
        //            }
        //        }

        //        var_dump($zaakEigenschappen);

        $soapEmigrationArray['aanvraaggegevens'] = [
            'burgerservicenummerAanvrager' => null,
            'emigratiedatum'               => null,
            'landcodeEmigratie'            => null,
            'adresBuitenland'              => null, // object
            'meeEmigranten'                => [],
        ];

        $soapEmigration = new ObjectEntity($soapVrijBrpEntity);
        $soapEmigration->hydrate($soapEmigrationArray);

        var_dump($soapEmigration->toArray());
        exit();

        return $this->data;
    }

    /**
     * Creates a VrijRBP Birth from a ZGW Zaak with the use of mapping.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @throws Exception
     *
     * @return array $this->data Data which we entered the function with
     */
    public function zgwConfidentialityToVrijBrpSoap(ObjectEntity $zaakObjectEntity): array
    {
        $soapVrijBrpEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($this->configuration['soapVrijBrpEntityId']);

        $soapConfidentialityArray['zaakgegevens'] = $this->createVrijBrpSoapZaakgegevens($zaakObjectEntity);
        $soapConfidentialityArray['contactgegevens'] = $this->createVrijBrpSoapContactgegevens($zaakObjectEntity);

        $soapConfidentialityArray['aanvraaggegevens'] = [
            'burgerservicenummerAanvrager' => null,
            'geheimhoudingBetrokkenen'     => [],
        ];

        $soapConfidentiality = new ObjectEntity($soapVrijBrpEntity);
        $soapConfidentiality->hydrate($soapConfidentialityArray);

        var_dump($soapConfidentiality->toArray());
        exit();

        return $this->data;
    }

    /**
     * Creates a VrijRBP Birth from a ZGW Zaak with the use of mapping.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @throws Exception
     *
     * @return array $this->data Data which we entered the function with
     */
    public function zgwExtractToVrijBrpSoap(ObjectEntity $zaakObjectEntity): array
    {
        $soapVrijBrpEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($this->configuration['soapVrijBrpEntityId']);

        $soapExtractArray['zaakgegevens'] = $this->createVrijBrpSoapZaakgegevens($zaakObjectEntity);
        $soapExtractArray['contactgegevens'] = $this->createVrijBrpSoapContactgegevens($zaakObjectEntity);

        $soapExtractArray['aanvraaggegevens'] = [
            'burgerservicenummerAanvrager' => null,
            'uittrekselBetrokkenen'        => [],
        ];

        $soapExtract = new ObjectEntity($soapVrijBrpEntity);
        $soapExtract->hydrate($soapExtractArray);

        var_dump($soapExtract->toArray());
        exit();

        return $this->data;
    }

    /**
     * Creates a VrijRBP Birth from a ZGW Zaak with the use of mapping.
     *
     * @param ObjectEntity $zaakObjectEntity
     *
     * @throws Exception
     *
     * @return array $this->data Data which we entered the function with
     */
    public function zgwNamingToVrijBrpSoap(ObjectEntity $zaakObjectEntity): array
    {
        $soapVrijBrpEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($this->configuration['soapVrijBrpEntityId']);

        $soapNamingArray['zaakgegevens'] = $this->createVrijBrpSoapZaakgegevens($zaakObjectEntity);
        $soapNamingArray['contactgegevens'] = $this->createVrijBrpSoapContactgegevens($zaakObjectEntity);

        $soapNamingArray['aanvraaggegevens'] = [
            'burgerservicenummerAanvrager' => null,
            'naamgebruikBetrokkenen'       => [],
        ];

        $soapNaming = new ObjectEntity($soapVrijBrpEntity);
        $soapNaming->hydrate($soapNamingArray);

        var_dump($soapNaming->toArray());
        exit();

        return $this->data;
    }

    /**
     * Maps dossier status to ztc statustype
     *
     * @param array $dossierArrayObject Dossier array object.
     *
     * @return array StatusType array
     */
    private function mapStatusType($dossierArrayObject): array
    {
        return [
            'omschrijving' => $dossierArrayObject['embedded']['status']['code'],
            'omschrijvingGeneriek' => $dossierArrayObject['embedded']['status']['description'],
            'isEindstatus' => $dossierArrayObject['embedded']['status']['endStatus'] ?? false
        ];
    }


    /**
     * Finds ZaakType statustype and if found creates a status
     *
     * @param array $zaakTypeArrayObject ZaakType array object.
     * @param array $dossierArrayObject Dossier array object.
     *
     * @return ?array Status array
     */
    private function findAndCreateStatus($zaakTypeArrayObject, $dossierArrayObject): ?array
    {
        foreach ($zaakTypeArrayObject['statustypen'] as $statusType) {
            if ($statusType['omschrijving'] == $dossierArrayObject['embedded']['status']['code']) {
                return [
                    'statustype' => $this->entityManager->find('App:ObjectEntity', $statusType['id']),
                    'datumStatusGezet' => $dossierArrayObject['embedded']['status']['entryDateTime']
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
                'omschrijving' => $dossierArrayObject['embedded']['type']['description'],
                'statustypen' => [
                    $this->mapStatusType($dossierArrayObject)
                ]
            ];
            $zaakTypeObjectEntity->hydrate($zaakTypeArray);
            $this->entityManager->persist($zaakTypeObjectEntity);
            $this->entityManager->flush();
            $zaakTypeObjectEntity = $this->entityManager->find('App:ObjectEntity', $zaakTypeObjectEntity->getId()->toString());
        }

        $zaakTypeArrayObject = $zaakTypeObjectEntity->toArray();

        !$zaakObjectEntity instanceof ObjectEntity && $zaakObjectEntity = new ObjectEntity($zaakEntity);
        $zaakArrayObject = [
            'zaaktype' => $zaakTypeObjectEntity,
            'identificatie' => $dossierArrayObject['dossierId'],
            'registratiedatum' => $dossierArrayObject['entryDateTime'],
            'startdatum' => $dossierArrayObject['startDate']
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


        if (!isset($data['response']['zgwZaak']['id'])) {
            throw new Exception('Zaak ID not given for ZgwToVrijbrpHandler');
        }

        // var_dump(json_encode($data));

        $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['response']['zgwZaak']['id']);
        if (!$zaakObjectEntity instanceof ObjectEntity) {
            throw new Exception('Zaak not found with given ID for ZgwToVrijbrpHandler');
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
            case 'B1425':
                return $this->data;
                //emigratie
                //                return $this->zgwEmigrationToVrijBrpSoap($zaakObjectEntity);
            case 'B0328':
                return $this->data;
                // geheimhouding
                //                return $this->zgwConfidentialityToVrijBrpSoap($zaakObjectEntity);
            case 'B0255':
                return $this->data;
                // brp uittreksel
                //                return $this->zgwExtractToVrijBrpSoap($zaakObjectEntity);
            case 'B0348':
                // naamsgebruik
                return $this->data;
                //                return $this->zgwNamingToVrijBrpSoap($zaakObjectEntity);
            default:
                return $this->data;
        }
    }
}
