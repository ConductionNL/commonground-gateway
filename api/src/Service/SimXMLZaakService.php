<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use ErrorException;
use Exception;

class SimXMLZaakService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;
    private array $configuration;
    private array $data;

    /**
     * @param EntityManagerInterface $entityManager
     * @param SynchronizationService $synchronizationService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $synchronizationService
    ) {
        $this->entityManager = $entityManager;
        $this->synchronizationService = $synchronizationService;
    }

    /**
     * This function returns the identifier from data based on the identifierPath field from the configuration array.
     *
     * @param array $data The data from the call
     *
     * @return string The identifierPath in the action configuration
     */
    public function getIdentifier(array $data): string
    {
        $dotData = new \Adbar\Dot($data);

        // @todo in de sync service noemen we dit niet identifierPath maar locationIdField
        return $dotData->get($this->configuration['identifierPath']);
    }

    /**
     * @param ObjectEntity $simXmlBody
     * @param ObjectEntity $simXmlStuurgegevens
     * @param ObjectEntity $zaaktypeObjectEntity
     * @param ObjectEntity $zaak
     *
     * @throws Exception
     *
     * @return ObjectEntity $zaak The modified data of the call with the case type and identification
     * @return void         The modified data of the call with the case type and identification
     */
    public function createNewZgwEigenschappen(ObjectEntity $simXmlBody, ObjectEntity $simXmlStuurgegevens, ObjectEntity $zaaktypeObjectEntity, ObjectEntity $zaak): ObjectEntity
    {
        $eigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['eigenschapEntityId']);
        $zaakEigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakEigenschapEntityId']);

        $elementen = $simXmlBody->getValue('elementen')->toArray();
        $eigenschappen = [];
        $zaakEigenschappen = [];

        foreach ($elementen as $key => $value) {
            if ($key == 'id' || $value == null) {
                continue;
            }

            // Nieuwe eigenschap aanmaken
            $eigenschap = new ObjectEntity($eigenschapEntity);
            $eigenschap->setValue('definitie', $simXmlStuurgegevens->getValue('berichttype'));
            $eigenschap->setValue('naam', $key);
            $eigenschap->setValue('toelichting', $simXmlStuurgegevens->getValue('berichttype'));
            $eigenschap->setValue('zaaktype', $zaaktypeObjectEntity->getUri());
            $eigenschap->setValue('specificatie', null);
            $this->entityManager->persist($eigenschap);
            $zaaktypeObjectEntity->setValue('eigenschappen', $eigenschappen);
            $this->entityManager->persist($zaaktypeObjectEntity);

            $eigenschappen[] = $eigenschap;

            // Nieuwe zaakEigenschap aanmaken
            $zaakEigenschap = new ObjectEntity($zaakEigenschapEntity);
            $zaakEigenschap->setValue('type', null);
            $zaakEigenschap->setValue('eigenschap', $eigenschap);
            $zaakEigenschap->setValue('naam', $key);
            $zaakEigenschap->setValue('waarde', is_array($value) ? json_encode($value) : $value);
            $zaakEigenschap->setValue('zaak', $zaak);

            $this->entityManager->persist($zaakEigenschap);
            $zaakEigenschappen[] = $zaakEigenschap;
        }

        $zaak->setValue('eigenschappen', $zaakEigenschappen);

        return $zaak;
    }

    /**
     * @param ObjectEntity $simXmlBody
     * @param ObjectEntity $zaaktypeObjectEntity
     * @param ObjectEntity $zaak
     *
     * @throws Exception
     *
     * @return ObjectEntity $zaak The modified data of the call with the case type and identification
     */
    public function createZgwZaakEigenschappen(ObjectEntity $simXmlBody, ObjectEntity $zaaktypeObjectEntity, ObjectEntity $zaak): ObjectEntity
    {
        $zaakEigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakEigenschapEntityId']);
        $unusedExtraElements = [
            'toelichting' => null,
        ];
        // Lets prepare an eigenschappen array
        $eigenschappen = $zaaktypeObjectEntity->getValue('eigenschappen');

        $eigenschappenArray = [];

        foreach ($eigenschappen as $eigenschapObjectEntity) {
            $eigenschappenArray[$eigenschapObjectEntity->getValue('naam')] = $eigenschapObjectEntity;
        }

        // Lets grep our extra elements to stuff into the zaak
        $elementen = $simXmlBody->getValue('elementen')->toArray();

        $zaakEigenschappenArray = [];
        foreach ($elementen as $key => $value) {
            // Extra element does exist in eigenschappen
            if ($value !== null && array_key_exists($key, $eigenschappenArray) && !in_array($key, $unusedExtraElements)) {

                // Eigenschap type
                $eigenschapType = $eigenschappenArray[$key];

                // Nieuwe eigenschap aanmaken
                $zaakEigenschap = new ObjectEntity($zaakEigenschapEntity);
                $zaakEigenschap->setValue('type', $eigenschapType->getValue('definitie'));
                $zaakEigenschap->setValue('eigenschap', $eigenschapType->getValue('url'));
                $zaakEigenschap->setValue('naam', $key);
                $zaakEigenschap->setValue('waarde', is_string($value) ? $value : $value = json_encode($value));
                $zaakEigenschap->setValue('zaak', $zaak);
                $zaakEigenschap->setValue('eigenschap', $eigenschapType->getValue('url'));

                $this->entityManager->persist($zaakEigenschap);
                // Nieuwe eigenschap aan zaak toevoegen
                $zaakEigenschappenArray[] = $zaakEigenschap;

                continue;
            }
            is_array($value) && $value = json_encode($value);
            // Extra element doesn't exist in eigenschappen
            $zaak->setValue('toelichting', "{$zaak->getValue('toelichting')}\n$key: $value");
        }
        $zaak->setValue('eigenschappen', $zaakEigenschappenArray);

        return $zaak;
    }

    /**
     * @param ObjectEntity $simXmlBody
     * @param ObjectEntity $simXmlStuurgegevens
     * @param ObjectEntity $zaaktypeObjectEntity
     * @param ObjectEntity $zaak
     *
     * @throws Exception
     *
     * @return ObjectEntity $zaak The modified data of the call with the case type and identification
     * @return void         The modified data of the call with the case type and identification
     */
    public function createNewZgwRolObject(ObjectEntity $simXmlBody, ObjectEntity $simXmlStuurgegevens, ObjectEntity $zaaktypeObjectEntity, ObjectEntity $zaak): ObjectEntity
    {
        $rolTypeEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['rolTypeEntityId']);

        $roltype = new ObjectEntity($rolTypeEntity);
        $roltype->setValue('zaaktype', $zaaktypeObjectEntity->getUri());
        $roltype->setValue('omschrijving', $simXmlStuurgegevens->getValue('berichttype'));
        $roltype->setValue('omschrijvingGeneriek', 'initiator');
        $this->entityManager->persist($roltype);

        $roltypen[] = $roltype;

        $zaaktypeObjectEntity->setValue('roltypen', $roltypen);

        $rol[] = $this->createZgwRollen($simXmlBody, $zaak, $roltype);
        $zaak->setValue('rollen', $rol);

        return $zaak;
    }

    /**
     * @param ObjectEntity $simXmlBody
     * @param ObjectEntity $zaak
     * @param ObjectEntity $roltype
     *
     * @throws Exception
     *
     * @return ObjectEntity|null The modified data of the call with the case type and identification
     */
    public function createZgwRollen(ObjectEntity $simXmlBody, ObjectEntity $zaak, ObjectEntity $roltype): ?ObjectEntity
    {
        $rolEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['rolEntityId']);

        $metadataObject = $simXmlBody->getValue('metadata');
        if ($roltype->getValue('omschrijvingGeneriek') == 'initiator') {
            $rol = new ObjectEntity($rolEntity);
            $rol->setValue('zaak', $zaak);
            $rol->setValue('roltype', $roltype);
            $rol->setValue('omschrijving', $roltype->getValue('omschrijving'));
            $rol->setValue('omschrijvingGeneriek', $roltype->getValue('omschrijvingGeneriek'));
            $rol->setValue('roltoelichting', 'indiener');

            if ($metadataObject->getValue('indienersoort') === 'burger') {
                $rol->setValue('betrokkeneIdentificatie', ['inpBsn' => $metadataObject->getValue('indiener')]);
                $rol->setValue('betrokkeneType', 'natuurlijk_persoon');
            }

            // @todo indienersoort voor vestiging achterhalen
            if ($metadataObject->getValue('indienersoort') === 'vestiging') {
                $rol->setValue(
                    'betrokkeneIdentificatie',
                    [
                        'vestigingsNummer' => $metadataObject->getValue('indiener'),
                        'handelsnaam'      => $metadataObject->getValue('indiener'),
                    ]
                );
                $rol->setValue('betrokkeneType', 'vestiging');
            }

            $this->entityManager->persist($rol);

            return $rol;
        }

        return null;
    }

    /**
     * @param ObjectEntity $simXmlBody
     * @param ObjectEntity $zaak
     * @param ObjectEntity $roltype
     *
     * @throws Exception
     *
     * @return ObjectEntity|null The modified data of the call with the case type and identification
     */
    public function createZgwEnkelvoudigInformatieObject(ObjectEntity $simXml, ObjectEntity $simXmlBody, ObjectEntity $simXmlStuurgegevens): ?array
    {
        $documenten = [];
        if ($bijlagen = $simXml->getValue('bijlagen')) {
            $informatieObjectTypeEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['informatieObjectTypeEntityId']);
            $enkelvoudiginformatieobjectEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['enkelvoudigInformatieObjectEntityId']);

            foreach ($bijlagen as $bijlage) {
                $informatieObjectType = new ObjectEntity($informatieObjectTypeEntity);
                $informatieObjectType->setValue('omschrijving', $bijlage->getValue('omschrijving'));
                $informatieObjectType->setValue('vertrouwelijkheidaanduiding', 'OPENBAAR');
                $informatieObjectType->setValue('beginGeldigheid', $simXmlBody->getValue('datumVerzending'));
                $this->entityManager->persist($informatieObjectType);
                $this->synchronizationService->setApplicationAndOrganization($informatieObjectType);

                $inhoudObject = $bijlage->getValue('inhoud');
                $enkelvoudigInformatieObjectArray = [
                    'titel'                   => $bijlage->getValue('naam'),
                    'bestandsnaam'            => $bijlage->getValue('naam'),
                    'beschrijving'            => $bijlage->getValue('omschrijving'),
                    'creatiedatum'            => $informatieObjectType->getValue('beginGeldigheid'),
                    'formaat'                 => $inhoudObject->getValue('contentType'),
                    'taal'                    => 'NLD',
                    'inhoud'                  => $inhoudObject->getValue('content'),
                    'status'                  => 'defintief',
                    'vertrouwelijkAanduiding' => $informatieObjectType->getValue('vertrouwelijkheidaanduiding'),
                    'auteur'                  => $simXmlStuurgegevens->getValue('zender'),
                ];

                $enkelvoudigInformatieObject = new ObjectEntity($enkelvoudiginformatieobjectEntity);
                $enkelvoudigInformatieObject->hydrate($enkelvoudigInformatieObjectArray);
                $this->entityManager->persist($enkelvoudigInformatieObject);
                $this->synchronizationService->setApplicationAndOrganization($enkelvoudigInformatieObject);
                $documenten[] = $enkelvoudigInformatieObject;
            }
        }

        return $documenten;
    }

    /**
     * Creates a 'zaakInformatieobject' to link the zgw case to the 'enkelvoudigInformatieobject'
     *
     * @param ObjectEntity $informatieObject The information object to link to a case
     * @param ObjectEntity $zaak             The case to link the information object to
     *
     * @return void
     * @throws Exception
     */
    public function createZaakInformatieObject(ObjectEntity $informatieObject, ObjectEntity $zaak): void
    {
        $zaakInformatieObjectEntity = $zaak->getAttributeObject('zaakinformatieobjecten')->getObject();

        $zaakInformatieobject = new ObjectEntity($zaakInformatieObjectEntity);
        $zaakInformatieobject->setValue('informatieobject', $informatieObject);
        $zaakInformatieobject->setValue('zaak', $zaak);
        $zaakInformatieobject->setValue('aardRelatieWeergave', $informatieObject->getValue('titel'));
        $zaakInformatieobject->setValue('titel', $informatieObject->getValue('titel'));
        $zaakInformatieobject->setValue('beschrijving', $informatieObject->getValue('beschrijving'));

        $this->synchronizationService->setApplicationAndOrganization($zaakInformatieobject);
        $this->entityManager->persist($zaakInformatieobject);
    }

    /**
     * This function converts a zds message to zgw.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration array from the action
     *
     * @throws ErrorException
     *
     * @return array The data from the call
     */
    public function simXMLToZGWHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $simXml = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
        $zaakEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakEntityId']);

        // @todo remove the check for identification and zaaktype if the dataService is implemented
        // @todo get in the zds object the values of the properties casetype and identification and store this in the case
        $zaakTypeIdentificatie = $this->getIdentifier($this->data['request']);
        if (!$zaakTypeIdentificatie) {
            // @todo fix error
            throw new ErrorException('The identificatie is not found');
        }

        $simXmlBody = $simXml->getValue('body');
        $simXmlMetadata = $simXmlBody->getValue('metaData');
        $simXml->setExternalId($simXmlBody->getValue('formulierId'));

        $simXmlStuurgegevens = $simXml->getValue('stuurgegevens');

        // Let get the zaaktype
        $zaaktypeObjectEntity = null;
        $zaaktypeValues = $this->entityManager->getRepository('App:Value')->findBy(['stringValue' => $zaakTypeIdentificatie]);
        foreach ($zaaktypeValues as $zaaktypeValue) {
            if ($zaaktypeValue->getObjectEntity()->getEntity()->getId() == $this->configuration['zaakTypeEntityId'] && $zaaktypeValue->getObjectEntity()->getValue('eindeGeldigheid') == null) {
                $zaaktypeObjectEntity = $zaaktypeValue->getObjectEntity();
            }
        }
        if (!$zaaktypeObjectEntity && !$zaaktypeObjectEntity instanceof ObjectEntity) {
            if (
                key_exists('enrichData', $this->configuration) &&
                $this->configuration['enrichData']
            ) {
                $zaakTypeEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakTypeEntityId']);
                // create zaaktype if not found

                $zaaktypeObjectEntity = new ObjectEntity($zaakTypeEntity);

                $zaaktypeArray = [
                    'identificatie' => $zaakTypeIdentificatie,
                    'omschrijving'  => $simXmlStuurgegevens->getValue('berichttype'),
                ];

                $zaaktypeObjectEntity->hydrate($zaaktypeArray);
                $this->entityManager->persist($zaaktypeObjectEntity);
                $zaaktypeObjectEntity->setValue('url', $zaaktypeObjectEntity->getUri());
            } else {
                // @todo fix error
                throw new ErrorException('The zaakType with identificatie: '.$zaakTypeIdentificatie.' can\'t be found');
            }
        }

        // Lets start by setting up the case
        $zaak = new ObjectEntity($zaakEntity);
        $zaak->setValue('identificatie', $simXmlBody->getValue('formulierId'));
        $zaak->setValue('registratiedatum', $simXmlStuurgegevens->getValue('datum'));
        $zaak->setValue('omschrijving', $simXmlStuurgegevens->getValue('berichttype'));
        $zaak->setValue('startdatum', $simXmlBody->getValue('datumVerzending'));
        $zaak->setValue('zaaktype', $zaaktypeObjectEntity);

        $this->entityManager->persist($zaak);

        if (count($zaaktypeObjectEntity->getValue('eigenschappen')) > 0) {
            $zaak = $this->createZgwZaakEigenschappen($simXmlBody, $zaaktypeObjectEntity, $zaak);
        } elseif (
            key_exists('enrichData', $this->configuration) &&
            $this->configuration['enrichData']
        ) {
            $zaak = $this->createNewZgwEigenschappen($simXmlBody, $simXmlStuurgegevens, $zaaktypeObjectEntity, $zaak);
        } else {
            throw new ErrorException('Cannot create zaakeigenschappen');
        }

        if (count($zaaktypeObjectEntity->getValue('roltypen')) > 0 && $roltypen = $zaaktypeObjectEntity->getValue('roltypen')) {
            $rollenArray = [];
            foreach ($roltypen as $roltype) {
                $rollenArray[] = $this->createZgwRollen($simXmlBody, $zaak, $roltype);
            }
            $zaak->setValue('rollen', $rollenArray);
        } elseif (
            key_exists('enrichData', $this->configuration) &&
            $this->configuration['enrichData']
        ) {
            $this->createNewZgwRolObject($simXmlBody, $simXmlStuurgegevens, $zaaktypeObjectEntity, $zaak);
        } else {
            throw new ErrorException('Cannot create rollen');
        }

        // add bijlagen
        $documenten = $this->createZgwEnkelvoudigInformatieObject($simXml, $simXmlBody, $simXmlStuurgegevens);

        foreach($documenten as $document) {
            $this->createZaakInformatieObject($document, $zaak);
        }

        $this->entityManager->persist($zaak);

        $simXml->setValue('zgwZaak', $zaak);
        $simXml->setValue('zgwDocumenten', $documenten);

        $this->entityManager->persist($simXml);
        $this->entityManager->flush();

        $this->data['response'] = $simXml->toArray();

        return $this->data;
    }
}
