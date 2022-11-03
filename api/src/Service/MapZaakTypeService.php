<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class MapZaakTypeService
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
     * Maps the statusTypen from xxllnc to zgw.
     *
     * @param array $zaakTypeArray This is the ZGW ZaakType array.
     *
     * @return array $zaakTypeArray This is the ZGW ZaakType array with the added statustypen.
     */
    private function mapStatusTypen(array $zaakTypeArray): array
    {
        $zaakTypeArray['roltypen'] = [];

        // Manually map phases to statustypen
        if (isset($this->data['embedded']['instance']['embedded']['phases'])) {
            $zaakTypeArray['statustypen'] = [];

            foreach ($this->data['embedded']['instance']['embedded']['phases'] as $phase) {
                // Mapping maken voor status
                $statusTypeArray = [];
                isset($phase['name']) && $statusTypeArray['omschrijving'] = $phase['name'];
                isset($phase['embedded']['fields'][0]['label']) ? $statusTypeArray['omschrijvingGeneriek'] = $phase['embedded']['fields'][0]['label'] : 'geen omschrijving';
                isset($phase['embedded']['fields'][0]['help']) ? $statusTypeArray['statustekst'] = $phase['embedded']['fields'][0]['help'] : 'geen statustekst';
                isset($phase['seq']) && $statusTypeArray['volgnummer'] = $phase['seq'];

                if (isset($phase['embedded']['route']['embedded']['role'])) {
                    $rolTypeArray = [];

                    // Get rolInstanceObject
                    $rolIdArray = explode('/', $phase['embedded']['route']['role']);
                    $rolObjectEntity = $this->objectEntityRepo->find(end($rolIdArray));
                    $roleInstanceObjectEntity = $this->objectEntityRepo->find($rolObjectEntity->getValue('instance')->getId()->toString());

                    $rolTypeArray = [
                        'omschrijving'         => $roleInstanceObjectEntity->getValue('description'),
                        'omschrijvingGeneriek' => strtolower($roleInstanceObjectEntity->getValue('name')),
                    ];
                    $zaakTypeArray['roltypen'][] = $rolTypeArray;
                }

                $zaakTypeArray['statustypen'][] = $statusTypeArray;
            }
        }

        return $zaakTypeArray;
    }

    /**
     * Maps the resultaatTypen from xxllnc to zgw.
     *
     * @param array $zaakTypeArray This is the ZGW ZaakType array.
     *
     * @return array $zaakTypeArray This is the ZGW ZaakType array with the added resultaattypen.
     */
    private function mapResultaatTypen(array $zaakTypeArray): array
    {
        // Manually map results to resultaattypen
        if (isset($this->data['embedded']['instance']['embedded']['results'])) {
            $zaakTypeArray['resultaattypen'] = [];
            foreach ($this->data['embedded']['instance']['embedded']['results'] as $result) {
                $resultaatTypeArray = [];
                $result['type'] && $resultaatTypeArray['omschrijving'] = $result['type'];
                $result['label'] && $resultaatTypeArray['toelichting'] = $result['label'];
                $resultaatTypeArray['selectielijstklasse'] = $result['selection_list'] ?? 'http://localhost';
                $result['type_of_archiving'] && $resultaatTypeArray['archiefnominatie'] = $result['type_of_archiving'];
                $result['period_of_preservation'] && $resultaatTypeArray['archiefactietermijn'] = $result['period_of_preservation'];

                $zaakTypeArray['resultaattypen'][] = $resultaatTypeArray;
            }
        }

        return $zaakTypeArray;
    }

    /**
     * Maps the eigenschappen from xxllnc to zgw.
     *
     * @param array $zaakTypeArray This is the ZGW ZaakType array.
     *
     * @return array $zaakTypeArray This is the ZGW ZaakType array with the added eigenschappen.
     */
    private function mapEigenschappen(array $zaakTypeArray): array
    {
        // // Manually map properties to eigenschappen
        $zaakTypeArray['eigenschappen'] = [];
        $propertyIgnoreList = ['lead_time_legal', 'lead_time_service', 'designation_of_confidentiality', 'extension', 'publication', 'supervisor_relation', 'suspension'];
        foreach ($this->data['embedded']['instance']['embedded']['properties'] as $propertyName => $propertyValue) {
            !in_array($propertyName, $propertyIgnoreList) && $zaakTypeArray['eigenschappen'][] = ['naam' => $propertyName, 'definitie' => $propertyName];
        }

        return $zaakTypeArray;
    }

    /**
     * Finds or creates a ObjectEntity from the ZaakType Entity.
     *
     * @param Entity $zaakTypeEntity This is the ZaakType Entity in the gateway.
     *
     * @return ObjectEntity $zaakTypeObjectEntity This is the ZGW ZaakType ObjectEntity.
     */
    private function getZaakTypeObjectEntity(Entity $zaakTypeEntity): ObjectEntity
    {
        // Find already existing zgwZaakType by $this->data['reference']
        $zaakTypeObjectEntity = $this->objectEntityRepo->findOneBy(['externalId' => $this->data['reference'], 'entity' => $zaakTypeEntity]);

        // Create new empty ObjectEntity if no ObjectEntity has been found
        if (!$zaakTypeObjectEntity instanceof ObjectEntity) {
            $zaakTypeObjectEntity = new ObjectEntity();
            $zaakTypeObjectEntity->setEntity($zaakTypeEntity);
        }

        return $zaakTypeObjectEntity;
    }

    /**
     * Creates or updates a ZGW ZaakType from a xxllnc casetype with the use of mapping.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @return array $this->data Data which we entered the function with
     */
    public function mapZaakTypeHandler(array $data, array $configuration): array
    {
        $this->data = $data['response'];
        $this->configuration = $configuration;

        // Find ZGW Type entities by id from config
        $zaakTypeEntity = $this->entityRepo->find($configuration['entities']['ZaakType']);

        if (!isset($zaakTypeEntity)) {
            throw new \Exception('ZaakType entity could not be found');
        }

        $zaakTypeObjectEntity = $this->getZaakTypeObjectEntity($zaakTypeEntity);

        // Map and set default values from xxllnc casetype to zgw zaaktype
        $zgwZaakTypeArray = $this->translationService->dotHydrator(isset($skeletonIn) ? array_merge($this->data, $this->skeletonIn) : $this->data, $this->data, $this->mappingIn);
        $zgwZaakTypeArray['instance'] = null;
        $zgwZaakTypeArray['embedded'] = null;

        $zgwZaakTypeArray = $this->mapStatusTypen($zgwZaakTypeArray);
        $zgwZaakTypeArray = $this->mapResultaatTypen($zgwZaakTypeArray);
        $zgwZaakTypeArray = $this->mapEigenschappen($zgwZaakTypeArray);

        $zaakTypeObjectEntity->hydrate($zgwZaakTypeArray);

        $zaakTypeObjectEntity->setExternalId($this->data['reference']);
        $zaakTypeObjectEntity = $this->synchronizationService->setApplicationAndOrganization($zaakTypeObjectEntity);

        $this->entityManager->persist($zaakTypeObjectEntity);
        $this->entityManager->flush();

        return $this->data;
    }
}
