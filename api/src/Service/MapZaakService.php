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
     * Maps the eigenschappen from xxllnc to zgw.
     *
     * @param array $zaakArray This is the ZGW Zaak array.
     * @param array  $zaakTypeArray This is the ZGW ZaakType array.
     * @param attributes $ar This is the xxllnc attributes array that will be mapped to eigenschappen.
     *
     * @return array $zaakArray This is the ZGW Zaak array with the added eigenschappen.
     */
    private function mapEigenschappen(array $zaakArray, array $zaakTypeArray, ObjectEntity $zaakTypeObjectEntity, array $attributes): array
    {
        var_dump('trololol');
        // Manually map properties to eigenschappen
        if (!isset($zaakTypeArray['eigenschappen'])) {
            $eigenschappen = [];
            foreach ($attributes as $attributeName => $attributeValue) {
                $eigenschappen[] = [
                    'naam' => $attributeName,
                    'definitie' => $attributeName
                ];
            }
            $zaakTypeObjectEntity->setValue('eigenschappen', $eigenschappen);
            $this->entityManager->persist($zaakTypeObjectEntity);
            $this->entityManager->flush();
        }
        //        var_dump($zaakTypeArray['eigenschappen']);

        $zaakTypeArray = $zaakTypeObjectEntity->toArray();

        !isset($zaakArray['eigenschappen']) && $zaakArray['eigenschappen'] = [];
        foreach ($attributes as $attributeName => $attributeValue) {
            foreach ($zaakTypeArray['eigenschappen'] as $eigenschap) {
                if ($eigenschap['naam'] == $attributeName) {
                    $zaakArray['eigenschappen'][] = [
                        'naam' => $attributeName,
                        'waarde' => is_array($attributeValue) ?
                            strval(array_shift($attributeValue)) :
                            strval($attributeValue),
                        'eigenschap' => $this->objectEntityRepo->find($eigenschap['id'])
                    ];
                }
            }
        }

        return $zaakArray;
    }


    /**
     * Maps the rollen from xxllnc to zgw.
     *
     * @param array $zaakArray This is the ZGW Zaak array.
     * @param array  $zaakTypeArray This is the ZGW ZaakType array.
     * @param array $rol This is the xxllnc Rol array.
     *
     * @return array $zaakArray This is the ZGW Zaak array with the added rollen.
     */
    private function mapRollen(array $zaakArray, array $zaakTypeArray, array $rol): array
    {
        $zaakArray['rollen'] = [];
        foreach ($zaakTypeArray['roltypen'] as $rolType) {
            if (strtolower($rol['preview']) == strtolower($rolType['omschrijving'])) {
                $zaakArray['rollen'][] = [
                    'roltype'       => $this->objectEntityRepo->find($rolType['id']),
                    'omschrijving' => $rol['preview'],
                    'omschrijvingGeneriek' => strtolower($rol['preview']),
                    'roltoelichting' => $rol['embedded']['insatnce']['description'],
                    'betrokkeneType' => 'natuurlijk_persoon'
                ];
            }
        }


        return $zaakArray;
    }

    /**
     * Maps the status from xxllnc to zgw.
     *
     * @param array  $zaakArray This is the ZGW Zaak array.
     * @param array  $zaakTypeArray This is the ZGW ZaakType array.
     * @param array $status This is the xxllnc Status array.
     *
     * @return array $zaakArray This is the ZGW Zaak array with the added status.
     */
    private function mapStatus(array $zaakArray, array $zaakTypeArray, array $status): array
    {
        foreach ($zaakTypeArray['statustypen'] as $statusType) {
            if ($status['preview'] == $statusType['omschrijving']) {
                $zaakArray['status'] = [
                    'statustype'       => $this->objectEntityRepo->find($statusType['id']),
                    'datumStatusGezet' => isset($status['embedded']['instance']['date_modified']) ? $status['embedded']['instance']['date_modified'] : '2020-04-15',
                    'statustoelichting' => isset($status['embedded']['instance']['milestone_label']) && strval($status['embedded']['instance']['milestone_label'])
                ];
                return $zaakArray;
            }
        }

        return $zaakArray;
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
    public function zgwToXxllncHandler(array $data, array $configuration): array
    {
        $this->entityManager->clear();
        $this->data = $data['response'];
        $this->configuration = $configuration;

        isset($this->configuration['entities']['XxllncZaakPost']) && $xxllncZaakPostEntity = $this->entityRepo->find($this->configuration['entities']['XxllncZaakPost']);

        if (!isset($xxllncZaakPostEntity)) {
            throw new \Exception('Xxllnc zaak entity not found, check ZgwToXxllncHandler conngi');
        }

        var_dump('mapZgwToZaakHandler triggered');
        if (isset($this->data['zaaktype'])) {
            $zaakTypeId = isset($this->data['zaaktype']['id']) ? $this->data['zaaktype']['id'] : $this->data['zaaktype'];
            $array = explode('/', $zaakTypeId);
            /* @todo we might want to validate against uuid and id here */
            $zaakTypeId = end($array);
        } else {
            throw new \Exception('No zaaktype set on zaak');
        }
        if (isset($this->data['id'])) {
            $zaakArrayObject = $this->entityManager->find('App:ObjectEntity', $this->data['id'])->toArray();
        } else {
            throw new \Exception('No id on zaak');
        }

        $xxllncZaakArray = ['casetype' => $zaakTypeId];
        $xxllncZaakArray['confidentiality'] = "public";

        // eigenschappen to values
        foreach ($zaakArrayObject['eigenschappen'] as $zaakEigenschap) {
            $xxllncZaakArray['values'][] = [
                'name' => $zaakEigenschap['eigenschap']['definitie'],
                'value' => $zaakEigenschap['waarde']
            ];
        }

        // zaakinformatieobjecten to files
        foreach ($zaakArrayObject['zaakinformatieobjecten'] as $infoObject) {
            isset($infoObject['informatieobject']) && $xxllncZaakArray['files'][] = [
                // 'reference' => $infoObject['id'],
                'type' => 'metadata',
                'naam' => $infoObject['titel'],
                'metadata' => [
                    // 'reference' =>  null,
                    'type' => 'metadata',
                    'instance' => [
                        'appearance' => $infoObject['informatieobject']['bestandsnaam'],
                        'category' => null,
                        'description' => $infoObject['informatieobject']['beschrijving'],
                        'origin' => 'Inkomend',
                        'origin_date' => $infoObject['informatieobject']['creatiedatum'],
                        'pronom_format' => $infoObject['informatieobject']['formaat'],
                        'structure' => 'text',
                        'trust_level' => $infoObject['integriteit']['waarde'] ?? 'Openbaar',
                        'status' => 'original',
                        'creation_date' => $infoObject['informatieobject']['creatiedatum']
                    ]
                ]
            ];
        }

        // rollen to subjects
        foreach ($zaakArrayObject['rollen'] as $rol) {
            $xxllncZaakArray['subjects'][] = [
                'subject' => [
                    'type' => 'subject',
                    // 'referene' => $rol['id']
                ],
                'role' => $rol['roltoelichting'],
                'magic_string_prefix' => $rol['roltoelichting'],
                'pip_authorized' => true,
                'send_auth_notification' => false
            ];
        }

        // DONT COMMIT
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $xxllncZaakPostEntity]);
        foreach ($objectEntities as $object) {
            $this->entityManager->remove($object);
        }

        $xxllncZaakObjectEntity = new ObjectEntity();
        $xxllncZaakObjectEntity->setEntity($xxllncZaakPostEntity);

        $xxllncZaakObjectEntity->hydrate($xxllncZaakArray);

        $this->entityManager->persist($xxllncZaakObjectEntity);
        $this->entityManager->flush();

        $xxllncZaakArrayObject = $xxllncZaakObjectEntity->toArray();


        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $xxllncZaakPostEntity->getId()->toString(), 'response' => $xxllncZaakArrayObject]);

        return $this->data;
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

        $this->entityManager->clear();
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
            'entity' => $configuration['entities']['XxllncZaakType'],
            'source' => $configuration['source'],
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
        while ($i < 5 && !$zaakTypeObjectEntity instanceof ObjectEntity)
        {
            $zaakTypeSync = $this->synchronizationService->findSyncBySource($xxllncGateway, $xxllncZaakTypeEntity, $zaakTypeId);
            $zaakTypeSync = $this->synchronizationService->handleSync($zaakTypeSync, [], $xxllncZaakTypeConfiguration);


            // dump($zaakTypeSync);
            sleep(5);

            var_dump('Trying to find ZaakType with entity.id: ' . $zaakTypeEntity->getId()->toString() . ' and externalId: ' . $zaakTypeSync->getObject()->getExternalId());
            $zaakTypeObjectEntity = $this->objectEntityRepo->findOneBy(['externalId' => $zaakTypeSync->getObject()->getExternalId(), 'entity' => $zaakTypeEntity]);
            // var_dump('Find/create zaakType count: ' . strval($i));
            $i++;
        }

        if (!$zaakTypeObjectEntity instanceof ObjectEntity) {
            var_dump('No zaakType could be found or created in 25s, returning data..');
            return $this->data;
        }

        var_dump('ZaakType found: ' . $zaakTypeObjectEntity->getId()->toString());
        // $zaakTypeArray = $zaakTypeObjectEntity->toArray();

        // Get XxllncZaakObjectEntity from this->data['id']
        $XxllncZaakObjectEntity = $this->objectEntityRepo->find($this->data['id']);

        var_dump('found zaakobjectentity');

        // Map and set default values from xxllnc casetype to zgw zaaktype
        $zgwZaakArray = $this->translationService->dotHydrator(isset($this->skeletonIn) ? array_merge($this->data, $this->skeletonIn) : $this->data, $this->data, $this->mappingIn);

        // Get array version of the ZaakType
        var_dump('Get array version of the ZaakType');
        $zaakTypeArray = $zaakTypeObjectEntity->toArray();

        // Set zaakType
        $zgwZaakArray['zaaktype'] = $zaakTypeObjectEntity;

        var_dump('add subobjects', $zaakTypeArray);

        $zgwZaakArray = $this->mapStatus($zgwZaakArray, $zaakTypeArray, $XxllncZaakObjectEntity->getValue('instance')->getValue('milestone')->toArray());
        $zgwZaakArray = $this->mapRollen($zgwZaakArray, $zaakTypeArray, $XxllncZaakObjectEntity->getValue('instance')->getValue('route')->getValue('instance')->getValue('role')->toArray()); //$this->data['embedded']['instance']['embedded']['route']['embedded']['instance']['embedded']['role']);
        $zgwZaakArray = $this->mapEigenschappen($zgwZaakArray, $zaakTypeArray, $zaakTypeObjectEntity, $XxllncZaakObjectEntity->getValue('instance')->getValue('attributes'));

        $zaakObjectEntity = $this->getZaakObjectEntity($zaakEntity);

        // set organization, application and owner on zaakObjectEntity from this->data
        $zaakObjectEntity->setOrganization($XxllncZaakObjectEntity->getOrganization());
        $zaakObjectEntity->setOwner($XxllncZaakObjectEntity->getOwner());
        $zaakObjectEntity->setApplication($XxllncZaakObjectEntity->getApplication());

        $zaakObjectEntity->hydrate($zgwZaakArray);

        $zaakObjectEntity->setExternalId($this->data['reference']);
        $zaakObjectEntity = $this->synchronizationService->setApplicationAndOrganization($zaakObjectEntity);

        $this->entityManager->persist($zaakObjectEntity);
        $this->entityManager->flush();
        $this->entityManager->clear();
        var_dump('ZGW Zaak created');

        return $this->data;
    }
}
