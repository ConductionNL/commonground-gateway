<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class MapSimXMLService
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private SynchronizationService $synchronizationService;
    private array $configuration;
    private array $data;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ObjectEntityService $objectEntityService,
        SynchronizationService $synchronizationService
    ) {
        $this->entityManager = $entityManager;
        $this->objectEntityService = $objectEntityService;
        $this->synchronizationService = $synchronizationService;

        $this->objectEntityRepo = $this->entityManager->getRepository(ObjectEntity::class);
        $this->entityRepo = $this->entityManager->getRepository(Entity::class);
    }

    /**
     * Creates drc informatie objecten from bijlagen.
     *
     * @param ObjectEntity $zaakObjectEntity This is the ZGW Zaak object.
     * @param array        $simXMLArray      This is the sim xml arrray.
     * @param array        $zaakTypeArray    This is the ZGW ZaakType array.
     *
     * @return void
     */
    private function createDocumenten(ObjectEntity &$zaakObjectEntity, Entity $documentEntity, array &$simXMLArray): array
    {
        if (!isset($simXMLArray['embedded']['Bijlagen'])) {
            return [];
        }
        $documents = [];
        // Create documenten
        $today = new \DateTime('now');
        $todayAsString = $today->format('Y-m-d h:i:s');
        //        var_dump(array_keys($simXMLArray['embedded']));
        foreach ($simXMLArray['embedded']['Bijlagen'] as $bijlage) {
            $objectInformatieObject = [
                'informatieobject' => [
                    'titel'                   => $bijlage['ns2:Naam'],
                    'bestandsnaam'            => $bijlage['ns2:Naam'],
                    'beschrijving'            => $bijlage['ns2:Omschrijving'],
                    'creatieDatum'            => $todayAsString,
                    'formaat'                 => $bijlage['embedded']['ns2:Inhoud']['@d6p1:contentType'],
                    'taal'                    => 'NLD',
                    'inhoud'                  => $bijlage['embedded']['ns2:Inhoud']['#'],
                    'status'                  => 'defintief',
                    'vertrouwelijkAanduiding' => 'OPENBAAR',
                    'auteur'                  => 'SIMform',

                ],
                'object'     => $zaakObjectEntity->getId()->toString(),
                'objectType' => 'zaak',
            ];

            $objectInformatieObjectObjectEntity = new ObjectEntity();
            $objectInformatieObjectObjectEntity->setEntity($documentEntity);

            $objectInformatieObjectObjectEntity->hydrate($objectInformatieObject);

            $this->entityManager->persist($objectInformatieObjectObjectEntity);
            $this->entityManager->flush();
            $this->synchronizationService->setApplicationAndOrganization($objectInformatieObjectObjectEntity);
            $this->synchronizationService->setApplicationAndOrganization($objectInformatieObjectObjectEntity->getValue('informatieobject'));
            $documents[] = $objectInformatieObjectObjectEntity->getId()->toString();
            $simXMLArray['zgwDocumenten'][] = $objectInformatieObjectObjectEntity->getId()->toString();
        }

        return $documents;
    }

    /**
     * Gets elements and adds them to the case array.
     *
     * @param array  $zaakTypeArray The case type array
     * @param string $elementName   The name of the element
     * @param $elementValue         The value of the element
     * @param array $zaakArray The case array
     *
     * @return array The new case array
     */
    private function getElements(array $zaakTypeArray, string $elementName, $elementValue, array $zaakArray): array
    {
        foreach ($zaakTypeArray['eigenschappen'] as $eigenschap) {
            if ($eigenschap['naam'] == $elementName && $eigenschap['naam'] !== 'embedded') {
                $zaakArray['eigenschappen'][] = [
                    'naam'       => $elementName,
                    'waarde'     => is_array($elementValue) ?: strval($elementValue),
                    'eigenschap' => $this->objectEntityRepo->find($eigenschap['id']),
                ];
            }
        }

        return $zaakArray;
    }

    /**
     * Maps the ZaakType from sim to zgw.
     *
     * @param ObjectEntity $zaakTypeObjectEntity This is the ZGW ZaakType object.
     * @param array        $simXMLArray          This is the sim xml arrray.
     * @param array        $zaakTypeArray        This is the ZGW ZaakType array.
     *
     * @return array $zaakArray This is the ZGW Zaak array with the added eigenschappen.
     */
    private function createEigenschappen(ObjectEntity &$zaakTypeObjectEntity, array $simXMLArray, array $zaakTypeArray): array
    {
        if (isset($simXMLArray['embedded']['Body']['embedded']['Elementen'])) {
            foreach ($simXMLArray['embedded']['Body']['embedded']['Elementen'] as $elementKey => $elementValue) {
                $zaakTypeArray['eigenschappen'][] = [
                    'naam'      => $elementKey,
                    'definitie' => $elementKey,
                ];
            }

            isset($simXMLArray['embedded']['Body']['DatumVerzending']) &&
                $zaakTypeArray['eigenschappen'][] = [
                    'naam'      => 'DATUM_VERZENDING',
                    'definitie' => 'DATUM_VERZENDING',
                ];
            isset($simXMLArray['embedded']['Body']['FormulierId']) &&
                $zaakTypeArray['eigenschappen'][] = [
                    'naam'      => 'FORMULIER_ID',
                    'definitie' => 'FORMULIER_ID',
                ];
            isset($simXMLArray['embedded']['Body']['MetaData']['INDIENER']) &&
                $zaakTypeArray['eigenschappen'][] = [
                    'naam'      => 'INDIENER_BSN',
                    'definitie' => 'INDIENER_BSN',
                ];
            isset($simXMLArray['embedded']['stuurgegevens']['Berichttype']) &&
                $zaakTypeArray['eigenschappen'][] = [
                    'naam'      => 'BERICHTTYPE',
                    'definitie' => 'BERICHTTYPE',
                ];
            isset($simXMLArray['embedded']['stuurgegevens']['Ontvanger']) &&
                $zaakTypeArray['eigenschappen'][] = [
                    'naam'      => 'ONTVANGER',
                    'definitie' => 'ONTVANGER',
                ];
            isset($simXMLArray['embedded']['stuurgegevens']['Zender']) &&
                $zaakTypeArray['eigenschappen'][] = [
                    'naam'      => 'ZENDER',
                    'definitie' => 'ZENDER',
                ];

            $zaakTypeObjectEntity->hydrate($zaakTypeArray);
            $this->entityManager->persist($zaakTypeObjectEntity);
            $this->entityManager->flush();
            $zaakTypeArray = $zaakTypeObjectEntity->toArray();

            $zaakArray = [];
            foreach ($simXMLArray['embedded']['Body']['embedded']['Elementen'] as $elementName => $elementValue) {
                $zaakArray = $this->getElements($zaakTypeArray, $elementName, $elementValue, $zaakArray);
            }

            foreach ($zaakTypeArray['eigenschappen'] as $eigenschap) {
                if ($eigenschap['naam'] == 'ONTVANGER') {
                    $zaakArray['eigenschappen'][] = [
                        'naam'       => 'ONTVANGER',
                        'waarde'     => $simXMLArray['embedded']['stuurgegevens']['Ontvanger'],
                        'eigenschap' => $this->objectEntityRepo->find($eigenschap['id']),
                    ];
                } elseif ($eigenschap['naam'] == 'ZENDER') {
                    $zaakArray['eigenschappen'][] = [
                        'naam'       => 'ZENDER',
                        'waarde'     => $simXMLArray['embedded']['stuurgegevens']['Zender'],
                        'eigenschap' => $this->objectEntityRepo->find($eigenschap['id']),
                    ];
                } elseif ($eigenschap['naam'] == 'BERICHTTYPE') {
                    $zaakArray['eigenschappen'][] = [
                        'naam'       => 'BERICHTTYPE',
                        'waarde'     => $simXMLArray['embedded']['stuurgegevens']['Berichttype'],
                        'eigenschap' => $this->objectEntityRepo->find($eigenschap['id']),
                    ];
                } elseif ($eigenschap['naam'] == 'INDIENER_BSN') {
                    $zaakArray['eigenschappen'][] = [
                        'naam'       => 'INDIENER_BSN',
                        'waarde'     => $simXMLArray['embedded']['Body']['MetaData']['INDIENER'],
                        'eigenschap' => $this->objectEntityRepo->find($eigenschap['id']),
                    ];
                } elseif ($eigenschap['naam'] == 'FORMULIER_ID') {
                    $zaakArray['eigenschappen'][] = [
                        'naam'       => 'FORMULIER_ID',
                        'waarde'     => $simXMLArray['embedded']['Body']['FormulierId'],
                        'eigenschap' => $this->objectEntityRepo->find($eigenschap['id']),
                    ];
                } elseif ($eigenschap['naam'] == 'DATUM_VERZENDING') {
                    $zaakArray['eigenschappen'][] = [
                        'naam'       => 'DATUM_VERZENDING',
                        'waarde'     => $simXMLArray['embedded']['Body']['DatumVerzending'],
                        'eigenschap' => $this->objectEntityRepo->find($eigenschap['id']),
                    ];
                } elseif ($eigenschap['naam'] == 'MEEVERHUIZENDE_GEZINSLEDEN') {
                    $zaakArray['eigenschappen'][] = [
                        'naam'       => 'MEEVERHUIZENDE_GEZINSLEDEN',
                        'waarde'     => json_encode($elementValue),
                        'eigenschap' => $this->objectEntityRepo->find($eigenschap['id']),
                    ];
                }
            }
        }

        if ($zaakTypeObjectEntity->getId() == null) {
            $this->entityManager->persist($zaakTypeObjectEntity);
            $this->entityManager->flush();
        }

        $zaakArray['zaaktype'] = $zaakTypeObjectEntity;

        return $zaakArray;
    }

    /**
     * Maps the ZaakType from sim to zgw.
     *
     * @param ObjectEntity $zaakObjectEntity     This is the ZGW Zaak object.
     * @param ObjectEntity $zaakTypeObjectEntity This is the ZGW ZaakType object.
     * @param array        $simXMLArray          This is the sim xml arrray.
     * @param Entity       $zaakEntity           This is the ZGW Zaak Entity.
     * @param Entity       $zaakTypeEntity       This is the ZGW ZaakType entity.
     *
     * @return array $zaakTypeArray This is the ZGW ZaakType array.
     */
    private function createZaakType(?ObjectEntity &$zaakObjectEntity = null, ?ObjectEntity &$zaakTypeObjectEntity = null, array $simXMLArray, Entity $zaakEntity, Entity $zaakTypeEntity): array
    {
        $zaakTypeArray = [];

        // If it does not exist, create new Zaak
        if (!$zaakObjectEntity instanceof ObjectEntity) {
            $zaakObjectEntity = new ObjectEntity();
            $zaakObjectEntity->setEntity($zaakEntity);
            $zaakObjectEntity->setExternalId($simXMLArray['embedded']['Body']['FormulierId']);
            $zaakTypeArray = $zaakObjectEntity->toArray();
        }

        // If it does not exist, create new ZaakType
        if (!$zaakTypeObjectEntity instanceof ObjectEntity) {
            $zaakTypeObjectEntity = new ObjectEntity();
            $zaakTypeObjectEntity->setEntity($zaakTypeEntity);
            $zaakObjectEntity->setExternalId($simXMLArray['embedded']['stuurgegevens']['Zaaktype']);
            $zaakTypeArray = $zaakTypeObjectEntity->toArray();
        }
        $zaakTypeArray['omschrijving'] = $simXMLArray['embedded']['stuurgegevens']['Zaaktype'];
        $zaakTypeArray['identificatie'] = $simXMLArray['embedded']['stuurgegevens']['Zaaktype'];
        $zaakTypeArray['identificatie'] = $simXMLArray['embedded']['Body']['FormulierId'];

        return $zaakTypeArray;
    }

    /**
     * Creates role types for the case type.
     *
     * @TODO: add the 'Overige betrokkene' role
     *
     * @param string       $rolTypeEntityId The entity id for role types
     * @param array        $simXmlArray     The original SimXML data
     * @param ObjectEntity $zaakType        The case type to add the role type to
     *
     * @throws \Exception
     * @throws \Exception
     *
     * @return ObjectEntity The resulting role type
     */
    public function createRolType(string $rolTypeEntityId, array $simXmlArray, ObjectEntity $zaakType): ObjectEntity
    {
        $rolTypeEntity = $this->entityManager->getRepository(Entity::class)->find($rolTypeEntityId);
        $rolTypes = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($rolTypeEntity, ['omschrijvingGeneriek' => 'initiator']);

        if (count($rolTypes) > 0) {
            return $rolTypes[0];
        }

        $rolTypeArray = [
            'zaaktype'             => $zaakType->getId()->toString(),
            'omschrijving'         => 'Initiator',
            'omschrijvingGeneriek' => 'initiator',
        ];

        $rolType = new ObjectEntity($rolTypeEntity);
        $rolType->hydrate($rolTypeArray);
        $this->synchronizationService->setApplicationAndOrganization($rolType);
        $this->entityManager->persist($rolType);
        $this->entityManager->flush();

        return $rolType;
    }

    /**
     * Creates roles for the case.
     *
     * @TODO: add the 'Overige betrokkene' role
     *
     * @param ObjectEntity $zaakObject    The case object to add the roles to
     * @param array        $simXMLArray   The original SimXML data
     * @param string       $rolEntity     The entity of roles
     * @param string       $rolTypeEntity The entity of role types
     * @param ObjectEntity $zaakType      The case type to link to the role type
     *
     * @throws \Exception
     *
     * @return ObjectEntity The resulting role
     */
    public function createRollen(ObjectEntity $zaakObject, array $simXMLArray, string $rolEntity, string $rolTypeEntity, ObjectEntity $zaakType): ObjectEntity
    {
        $rolType = $this->createRolType($rolTypeEntity, $simXMLArray, $zaakType);
        $rolArray = [
            'zaak'                    => $zaakObject,
            'betrokkeneType'          => 'natuurlijk_persoon',
            'roltype'                 => $rolType,
            'rolToelichting'          => 'Initiator',
            'betrokkeneIdentificatie' => ['inpBsn' => $simXMLArray['embedded']['Body']['embedded']['MetaData']['INDIENER']],
            'omschrijvingGeneriek'    => 'initiator',
            'omschrijving'            => 'Initiator',
        ];

        $rol = new ObjectEntity($this->entityManager->getRepository(Entity::class)->find($rolEntity));
        $rol->hydrate($rolArray);
        $this->synchronizationService->setApplicationAndOrganization($rol);
        $this->synchronizationService->setApplicationAndOrganization($rol->getValue('betrokkeneIdentificatie'));

        $this->entityManager->persist($rol);
        $this->entityManager->flush();

        return $rol;
    }

    /**
     * Creates or updates a ZGW ZaakType from a xxllnc casetype with the use of mapping.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @return array Data which we entered the function with
     */
    public function mapSimXMLHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;
        $simXMLArray = $data['response'];

        // Find ZGW entities by id from config
        $zaakTypeEntity = $this->entityRepo->find($this->configuration['entities']['ZaakType']);
        $zaakEntity = $this->entityRepo->find($this->configuration['entities']['Zaak']);
        $documentEntity = $this->entityRepo->find($this->configuration['entities']['ObjectInformatieObject']);

        if (!isset($zaakTypeEntity)) {
            throw new \Exception('ZaakType entity could not be found');
        }
        if (!isset($zaakEntity)) {
            throw new \Exception('Zaak entity could not be found');
        }
        if (!isset($documentEntity)) {
            throw new \Exception('ObjectInformatieObject entity could not be found');
        }

        // Get Zaak ObjectEntity
        $zaakObjectEntity = $this->objectEntityRepo->findOneBy(['externalId' => $simXMLArray['embedded']['Body']['FormulierId'], 'entity' => $zaakEntity]);
        // Get ZaakType ObjectEntity
        $zaakTypeObjectEntity = $this->objectEntityRepo->findOneBy(['externalId' => $simXMLArray['embedded']['stuurgegevens']['Zaaktype'], 'entity' => $zaakTypeEntity]);

        $zaakTypeArray = $this->createZaakType($zaakObjectEntity, $zaakTypeObjectEntity, $simXMLArray, $zaakEntity, $zaakTypeEntity);
        $zaakArray['zaaktype'] = $zaakTypeArray;

        $zaakArray = $this->createEigenschappen($zaakTypeObjectEntity, $simXMLArray, $zaakTypeArray);

        $zaakObjectEntity->hydrate($zaakArray);

        $zaakObjectEntity = $this->synchronizationService->setApplicationAndOrganization($zaakObjectEntity);

        $this->entityManager->persist($zaakObjectEntity);
        $this->entityManager->flush();

        $zaakObjectEntity = $this->objectEntityRepo->find($zaakObjectEntity->getId()->toString());

        $documents = $this->createDocumenten($zaakObjectEntity, $documentEntity, $simXMLArray);
        $this->createRollen($zaakObjectEntity, $simXMLArray, $this->configuration['entities']['rolEntityId'], $this->configuration['entities']['rolTypeEntityId'], $zaakObjectEntity);
        $simXMLObject = $this->entityManager->getRepository(ObjectEntity::class)->find($data['response']['id']);

        $simXMLObject->setValue('zgwZaak', $zaakObjectEntity->getId()->toString());
        $simXMLObject->setValue('zgwDocumenten', $documents);

        $this->entityManager->persist($simXMLObject);
        $this->entityManager->flush();
        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $zaakEntity->getId()->toString(), 'response' => $zaakObjectEntity->toArray()]);

        return $this->data;
    }
}
