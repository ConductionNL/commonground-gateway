<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class MapCommitmentService
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
    public function mapCommitmentHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if (!isset($data['id']) && !isset($data['response']['id'])) {
            throw new \Exception('Zaak ID not given for MapCommitmentHandler');
        }

        $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['id']);
        if (!$zaakObjectEntity instanceof ObjectEntity) {
            $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['response']['id']);
            if (!$zaakObjectEntity instanceof ObjectEntity) {
                throw new \Exception('Zaak not found with given ID for MapCommitmentHandler');
            }
        }

        $zaakArray = $zaakObjectEntity->toArray();

        if ($zaakArray['zaaktype']['omschrijving'] !== 'B0337') {
            return $this->data;
        }

        $commitmentEntity = $this->entityRepo->find($configuration['entities']['Commitment']);

        if (!isset($commitmentEntity)) {
            throw new \Exception('Commitment entity could not be found');
        }
        var_dump('MAP COMMITMENT TRIGGERED');

        $zaakArray['eigenschappen'] = [
            [
                "eigenschap" => "string",
                "naam" => "identificatie",
                "waarde" => "778bc0e0-8a26-4b92-b670-a174dfad2349"
            ],
            [
                "eigenschap" => "string",
                "naam" => "omschrijving",
                "waarde" => "huwelijk zds zonder bijlagen"
            ],
            [
                "eigenschap" => "string",
                "naam" => "startdatum",
                "waarde" => "20211203"
            ],
            [
                "eigenschap" => "string",
                "naam" => "registratiedatum",
                "waarde" => "20211203"
            ],
            [
                "eigenschap" => "string",
                "naam" => "sub.telefoonnummer",
                "waarde" => "0633309392"
            ],
            [
                "eigenschap" => "string",
                "naam" => "sub.emailadres",
                "waarde" => "s.everard@simgroep.nl"
            ],
            [
                "eigenschap" => "string",
                "naam" => "geselecteerdNaamgebruik",
                "waarde" => "N"
            ],
            [
                "eigenschap" => "string",
                "naam" => "verbintenisType",
                "waarde" => "MARRIAGE"
            ],
            [
                "eigenschap" => "string",
                "naam" => "verbintenisDatum",
                "waarde" => "230405"
            ],
            [
                "eigenschap" => "string",
                "naam" => "verbintenisTijd",
                "waarde" => "1970-01-01T10:00:00+01:00"
            ],
            [
                "eigenschap" => "string",
                "naam" => "naam",
                "waarde" => "Trouwzaal Stadhuis"
            ],
            [
                "eigenschap" => "string",
                "naam" => "naam",
                "waarde" => "Trouwzaal Stadhuis"
            ],
            [
                "eigenschap" => "string",
                "naam" => "naam1",
                "waarde" => "H. Smaling"
            ],
            [
                "eigenschap" => "string",
                "naam" => "naam2",
                "waarde" => "R.S. Nas"
            ],
            [
                "eigenschap" => "string",
                "naam" => "trouwboekje",
                "waarde" => "true"
            ],
            [
                "eigenschap" => "string",
                "naam" => "bsn1",
                "waarde" => "999993781"
            ],
            [
                "eigenschap" => "string",
                "naam" => "voornamen1",
                "waarde" => "Vanessa"
            ],
            [
                "eigenschap" => "string",
                "naam" => "voorvoegselGeslachtsnaam1",
                "waarde" => ""
            ],
            [
                "eigenschap" => "string",
                "naam" => "geslachtsnaam1",
                "waarde" => "Groeman"
            ],
            [
                "eigenschap" => "string",
                "naam" => "geboortedatum1",
                "waarde" => "931008"
            ],
            [
                "eigenschap" => "string",
                "naam" => "geboortedatum1",
                "waarde" => "931008"
            ],
            [
                "eigenschap" => "string",
                "naam" => "verzorgdgem",
                "waarde" => "2"
            ],
            [
                "eigenschap" => "string",
                "naam" => "bsn2",
                "waarde" => "999992478"
            ],
            [
                "eigenschap" => "string",
                "naam" => "voornamen2",
                "waarde" => "Troje"
            ],
            [
                "eigenschap" => "string",
                "naam" => "voorvoegselGeslachtsnaam2",
                "waarde" => ""
            ],
            [
                "eigenschap" => "string",
                "naam" => "geslachtsnaam2",
                "waarde" => "Homeros"
            ],
            [
                "eigenschap" => "string",
                "naam" => "geboortedatum2",
                "waarde" => "911221"
            ],
            [
                "eigenschap" => "string",
                "naam" => "inp.bsn",
                "waarde" => "999990974"
            ],
            [
                "eigenschap" => "string",
                "naam" => "sub.emailadres",
                "waarde" => "s.everard@simgroep.nl"
            ],
            [
                "eigenschap" => "string",
                "naam" => "voornamen",
                "waarde" => "Patrick"
            ],
            [
                "eigenschap" => "string",
                "naam" => "voornamen",
                "waarde" => "Patrick"
            ],
            [
                "eigenschap" => "string",
                "naam" => "voornamen",
                "waarde" => "Patrick"
            ],
            [
                "eigenschap" => "string",
                "naam" => "voorvoegselGeslachtsnaam",
                "waarde" => "de"
            ],
            [
                "eigenschap" => "string",
                "naam" => "geslachtsnaam",
                "waarde" => "Boer"
            ],
            [
                "eigenschap" => "string",
                "naam" => "gor.openbareRuimteNaam",
                "waarde" => "TESTadres"
            ],
            [
                "eigenschap" => "string",
                "naam" => "geboortedatum",
                "waarde" => "891112"
            ],
            [
                "eigenschap" => "string",
                "naam" => "aoa.huisnummer",
                "waarde" => "16"
            ],
            [
                "eigenschap" => "string",
                "naam" => "aoa.huisletter",
                "waarde" => "a"
            ],
            [
                "eigenschap" => "string",
                "naam" => "aoa.huisnummertoevoeging",
                "waarde" => ""
            ],
            [
                "eigenschap" => "string",
                "naam" => "aoa.postcode",
                "waarde" => "1234AB"
            ],
            [
                "eigenschap" => "string",
                "naam" => "wpl.woonplaatsNaam",
                "waarde" => "TestWoonplaats"
            ],
            [
                "eigenschap" => "string",
                "naam" => "sub.telefoonnummer",
                "waarde" => "0633309392"
            ],
            [
                "eigenschap" => "string",
                "naam" => "sub.emailadres",
                "waarde" => "s.everard@simgroep.nl"
            ],
            [
                "eigenschap" => "string",
                "naam" => "geselecteerdNaamgebruik",
                "waarde" => "E"
            ]
        ];
        
        $commitmentArray = [];
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
                case 'bsn1':
                    $commitmentArray['partner1'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam1':
                    $commitmentArray['partner1']['nameAfterCommitment']['lastname'] = $eigenschap['waarde'];
                    continue 2;
                case 'geselecteerdNaamgebruik':
                    $commitmentArray['partner1']['nameAfterCommitment']['nameUseType'] = $eigenschap['waarde'];
                    $commitmentArray['partner2']['nameAfterCommitment']['nameUseType'] = $eigenschap['waarde'];
                    continue 2;
                case 'bsn2':
                    $commitmentArray['partner2'] = $eigenschap['waarde'];
                    continue 2;
                case 'geslachtsnaam2':
                    $commitmentArray['partner2']['nameAfterCommitment']['lastname'] = $eigenschap['waarde'];
                case 'verbintenisType':
                    $commitmentArray['planning']['commitmentType'] = $eigenschap['waarde'];
                    continue 2;
                case 'verbintenisDatum':
                    $dateTimeObject = new \DateTime($eigenschap['waarde']);
                    $dateTimeFormatted = $dateTimeObject->format('Y-m-d|TH:i:s');
                    $commitmentArray['planning']['commitmentDateTime'] = $dateTimeFormatted;
                    continue 2;
        }

        $commitmentArray['dossier']['type']['code'] = $zaakArray['zaaktype']['identificatie'];



        // Save in gateway

        $commitmentObjectEntity = new ObjectEntity();
        $commitmentObjectEntity->setEntity($commitmentEntity);

        $commitmentObjectEntity->hydrate($commitmentArray);

        $this->entityManager->persist($commitmentObjectEntity);
        $this->entityManager->flush();

        $intraObjectArray = $commitmentObjectEntity->toArray();

        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $commitmentEntity->getId()->toString(), 'response' => $intraObjectArray]);

        return $this->data;
    }
}
