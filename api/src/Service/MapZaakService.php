<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Gateway;
use Doctrine\ORM\EntityManagerInterface;

class MapZaakService
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
            'identificatie' => 'reference',
            'omschrijving' => 'instance.embedded.subject',
            'toelichting' => 'instance.embedded.subject_external',
            'registratiedatum' => 'instance.embedded.date_of_registration',
            'startdatum' => 'instance.embedded.date_of_registration',
            'einddatum' => 'instance.embedded.date_of_completion',
            'einddatumGepland' => 'instance.embedded.date_target',
            'publicatiedatum' => 'instance.embedded.date_target',
            'communicatiekanaal' => 'instance.embedded.channel_of_contact',
            'vertrouwelijkheidaanduidng' => 'instance.embedded.confidentiality.mapped'


        ];

        $this->skeletonIn = [
            'verantwoordelijkeOrganisatie' => '070124036',
            'betalingsindicatie' => 'geheel',
            'betalingsindicatieWeergave' => 'Bedrag is volledig betaald',
            'laatsteBetaalDatum' => '15-07-2022',
            'archiefnominatie' => 'blijvend_bewaren',
            'archiefstatus' => 'nog_te_archiveren'
        ];
    }

    /**
     * Finds or creates a ObjectEntity from the Zaak Entity.
     *
     * @param Entity $zaakEntity This is the Zaak Entity in the gateway.
     *
     * @return ObjectEntity $zaakObjectEntity This is the ZGW Zaak ObjectEntity.
     */
    private function getZaakObjectEntity(Entity $zaakEntity): ObjectEntity
    {
        // Find already existing zgwZaak by $this->data['reference']
        $zaakObjectEntity = $this->objectEntityRepo->findOneBy(['externalId' => $this->data['reference'], 'entity' => $zaakEntity]);

        // Create new empty ObjectEntity if no ObjectEntity has been found
        if (!$zaakObjectEntity instanceof ObjectEntity) {
            $zaakObjectEntity = new ObjectEntity();
            $zaakObjectEntity->setEntity($zaakEntity);
        }

        return $zaakObjectEntity;
    }

    /**
     * Creates or updates a ZGW Zaak from a xxllnc casetype with the use of mapping.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the Zaak entity id is stored in.
     *
     * @return array $this->data Data which we entered the function with
     */
    public function mapZaakHandler(array $data, array $configuration): array
    {
        $this->data = $data['response'];
        $this->configuration = $configuration;

        // ik heb in config nog nodig: domein url (kan via gekoppelde source), xxllncZaakTypeEntityId

        var_dump('MapZaak triggered');

        // Find ZGW Type entities by id from config
        $zaakEntity = $this->entityRepo->find($configuration['entities']['Zaak']);
        $zaakTypeEntity = $this->entityRepo->find($configuration['entities']['ZaakType']);
        $xxllncZaakTypeEntity = $this->entityRepo->find($configuration['entities']['XxllncZaakType']);

        // Get xxllnc Gateway
        $xxllncGateway = $this->entityManager->getRepository(Gateway::class)->find($configuration['source']);

        if (!$zaakEntity instanceof Entity) {
            throw new \Exception('Zaak entity could not be found, plugin configuration could be wrong');
        }
        if (!$zaakTypeEntity instanceof Entity) {
            throw new \Exception('ZaakType entity could not be found, plugin configuration could be wrong');
        }
        if (!$xxllncZaakTypeEntity instanceof Entity) {
            throw new \Exception('Xxllnc zaaktype entity could not be found, plugin configuration could be wrong');
        }
        if (!$xxllncGateway instanceof Gateway) {
            throw new \Exception('Xxllnc gateway could not be found, plugin configuration could be wrong');
        }

        // if no casetype id return
        if (!isset($this->data['embedded']['instance']['embedded']['casetype'])) {
            return $this->data;
        }

        $zaakTypeId = $this->data['embedded']['instance']['embedded']['casetype']['reference'];


        // Get ZaakType ObjectEntity
        $zaakTypeObjectEntity = $this->objectEntityRepo->findOneBy(['externalId' => $zaakTypeId, 'entity' => $zaakTypeEntity]);

        $xxllncZaakTypeConfiguration = [
            'source' => $xxllncGateway->getId()->toString(),
            'location' => '/casetype',
            'apiSource' => [
                'location' => [
                    'objects' => 'result.instance.rows',
                    'object' => 'result',
                    'idField' => 'reference'
                ],
                'queryMethod' => 'page',
                'syncFromList' => true,
                'sourceLeading' => true,
                'useDataFromCollection' => false,
                'mappingIn' => [],
                'mappingOut' => [],
                'translationsIn' => [],
                'translationsOut' => [],
                'skeletonIn' => []
            ]
        ];


        // $zaakTypeObjectEntity instanceof ObjectEntity && $zaakTypeArray = $zaakTypeObjectEntity->toArray();

        // If it does not exist, fetch the casetype from xxllnc, sync it, then map it to zgw ZaakType
        $i = 0;
        while ($i < 5 && !$zaakTypeObjectEntity instanceof ObjectEntity) :
            $zaakTypeSync = $this->synchronizationService->findSyncBySource($xxllncGateway, $xxllncZaakTypeEntity, $zaakTypeId);
            $zaakTypeSync = $this->synchronizationService->handleSync($zaakTypeSync, [], $xxllncZaakTypeConfiguration);
            sleep(5);
            $zaakTypeObjectEntity = $this->objectEntityRepo->findOneBy(['externalId' => $zaakTypeId, 'entity' => $zaakTypeEntity]);
            $i++;
        endwhile;

        if (!$zaakTypeObjectEntity instanceof ObjectEntity) {
            return $this->data;
        }

        var_dump('ZaakType found/created: ' . $zaakTypeObjectEntity->getId()->toString());
        // $zaakTypeArray = $zaakTypeObjectEntity->toArray();

        // Get XxllncZaakObjectEntity from this->data['id']
        $XxllncZaakObjectEntity = $this->objectEntityRepo->find($this->data['id']);

        $zaakObjectEntity = $this->getZaakObjectEntity($zaakEntity);

        // set organization, application and owner on zaakObjectEntity from this->data
        $zaakObjectEntity->setOrganization($XxllncZaakObjectEntity->getOrganization());
        $zaakObjectEntity->setOwner($XxllncZaakObjectEntity->getOwner());
        $zaakObjectEntity->setApplication($XxllncZaakObjectEntity->getApplication());

        // Map and set default values from xxllnc casetype to zgw zaaktype
        $zgwZaakArray = $this->translationService->dotHydrator(isset($this->skeletonIn) ? array_merge($this->data, $this->skeletonIn) : $this->data, $this->data, $this->mappingIn);
        $zgwZaakArray['instance'] = null;
        $zgwZaakArray['embedded'] = null;

        // Set zaakType
        $zgwZaakArray['zaaktype'] = $zaakTypeObjectEntity;

        // $zgwZaakArray = $this->mapStatus($zgwZaakArray);
        // $zgwZaakArray = $this->mapRollen($zgwZaakArray);
        // $zgwZaakArray = $this->mapEigenschappen($zgwZaakArray);

        $zaakObjectEntity->hydrate($zgwZaakArray);

        $zaakObjectEntity->setExternalId($this->data['reference']);
        $zaakObjectEntity = $this->synchronizationService->setApplicationAndOrganization($zaakObjectEntity);

        $this->entityManager->persist($zaakObjectEntity);
        $this->entityManager->flush();

        return $this->data;
    }
}
