<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use ErrorException;
use Exception;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class ZdsZaakService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;
    private ObjectEntityService $objectEntityService;
    private CommonGroundService $commonGroundService;
    private array $configuration;
    private array $data;
    private array $usedValues = [];

    /**
     * @param EntityManagerInterface $entityManager
     * @param SynchronizationService $synchronizationService
     * @param ObjectEntityService    $objectEntityService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $synchronizationService,
        ObjectEntityService $objectEntityService,
        CommonGroundService $commonGroundService
    ) {
        $this->entityManager = $entityManager;
        $this->synchronizationService = $synchronizationService;
        $this->objectEntityService = $objectEntityService;
        $this->commonGroundService = $commonGroundService;
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
     * This function validates whether the zds message has an identifier associated with a case type.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration array from the action
     *
     * @throws ErrorException
     *
     * @return array The modified data of the call with the case type and identification
     *
     * @todo Zgw zaaktype en identificatie toevoegen aan het zds bericht (DataService hebben we hiervoor nodig)
     */
    public function zdsValidationHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $zaakTypeIdentificatie = $this->getIdentifier($this->data['request']);

        if (!$zaakTypeIdentificatie) {
            throw new ErrorException('The identificatie is not found');
        }

        // Let get the zaaktype
        $zaakTypeObjectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findByAnyId($zaakTypeIdentificatie);
        if (!$zaakTypeObjectEntity || !$zaakTypeObjectEntity instanceof ObjectEntity) {
            // @todo fix error
            throw new ErrorException('The zaakType with identificatie: '.$zaakTypeIdentificatie.' can\'t be found');
        }

        // @todo change the data with the zaaktype and identification.

        //        $zds = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
        //
        //        $zdsArray = $zds->toArray();
        //        $zdsArray['object']['zgw'] = [
        //            'zaaktype' => $zaakTypeObjectEntity,
        //            'identificatie' => $zaakTypeIdentificatie,
        //        ];

        return $this->data;
    }

    /**
     * @param ObjectEntity $zdsObject
     * @param ObjectEntity $zaaktypeObjectEntity
     * @param ObjectEntity $zaak
     *
     * @throws Exception
     *
     * @return void The modified data of the call with the case type and identification
     */
    public function createNewZgwEigenschappen(ObjectEntity $zdsObject, ObjectEntity $zaaktypeObjectEntity, ObjectEntity $zaak): void
    {
        $eigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['eigenschapEntityId']);
        $zaakEigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakEigenschapEntityId']);

        $extraElementen = $zdsObject->getValue('extraElementen');
        $eigenschappen = [];
        $zaakEigenschappen = [];

        foreach ($extraElementen as $extraElement) {
            // Nieuwe eigenschap aanmaken
            $eigenschap = new ObjectEntity($eigenschapEntity);
            $eigenschap->setValue('definitie', $zdsObject->getValue('omschrijving'));
            $eigenschap->setValue('naam', $extraElement->getValue('@naam'));
            $eigenschap->setValue('toelichting', $zdsObject->getValue('omschrijving'));
            $eigenschap->setValue('zaaktype', $zaaktypeObjectEntity->getUri());
            $eigenschap->setValue('specificatie', null);
            $this->entityManager->persist($eigenschap);
            $this->synchronizationService->setApplicationAndOrganization($eigenschap);

            $eigenschappen[] = $eigenschap;

            // Nieuwe zaakEigenschap aanmaken
            $zaakEigenschap = new ObjectEntity($zaakEigenschapEntity);
            $zaakEigenschap->setValue('type', null);
            $zaakEigenschap->setValue('eigenschap', $eigenschap);
            $zaakEigenschap->setValue('naam', $extraElement->getValue('@naam'));
            $zaakEigenschap->setValue('waarde', $extraElement->getValue('#'));
            $zaakEigenschap->setValue('zaak', $zaak);
            $this->synchronizationService->setApplicationAndOrganization($zaakEigenschap);

            $this->entityManager->persist($zaakEigenschap);
            $zaakEigenschappen[] = $zaakEigenschap;
        }
        $zaaktypeObjectEntity->setValue('eigenschappen', $eigenschappen);
        $this->entityManager->persist($zaaktypeObjectEntity);

        $zaak->setValue('eigenschappen', $zaakEigenschappen);
    }

    /**
     * @param ObjectEntity $zdsObject
     * @param ObjectEntity $zaaktypeObjectEntity
     * @param ObjectEntity $zaak
     *
     * @throws Exception
     *
     * @return void The modified data of the call with the case type and identification
     */
    public function createZgwZaakEigenschappen(ObjectEntity $zdsObject, ObjectEntity $zaaktypeObjectEntity, ObjectEntity $zaak): void
    {
        $zaakEigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakEigenschapEntityId']);
        $unusedExtraElements = [
            'toelichting' => null,
        ];
        // Lets prepare an eigenschappen array
        $eigenschappen = $zaaktypeObjectEntity->getValue('eigenschappen');

        $eigenschappenArray = [];

        foreach ($eigenschappen as $eigenschap) {
            $eigenschappenArray[$eigenschap->getValue('naam')] = $eigenschap;
        }

        // Lets grep our extra elements to stuff into the zaak
        $extraElementen = $zdsObject->getValue('extraElementen');
        $zaakEigenschappen = [];
        foreach ($extraElementen as $extraElement) {
            // Extra element does exist in eigenschappen
            if (array_key_exists($extraElement->getValue('@naam'), $eigenschappenArray) && !in_array($extraElement->getValue('@naam'), $unusedExtraElements)) {

                // Eigenschap type
                $eigenschapType = $eigenschappenArray[$extraElement->getValue('@naam')];

                if (!$extraElement->getValue('#')) {
                    continue;
                }

                // Nieuwe eigenschap aanmaken
                $zaakEigenschap = new ObjectEntity($zaakEigenschapEntity);
                $zaakEigenschap->setValue('type', $eigenschapType->getValue('definitie'));
                $zaakEigenschap->setValue('eigenschap', $eigenschapType->getValue('url'));
                $zaakEigenschap->setValue('naam', $extraElement->getValue('@naam'));
                $zaakEigenschap->setValue('waarde', $extraElement->getValue('#'));
                $zaakEigenschap->setValue('zaak', $zaak);
                $zaakEigenschap->setValue('eigenschap', $eigenschapType->getValue('url'));

                $zaakEigenschappen[] = $zaakEigenschap;
                $this->entityManager->persist($zaakEigenschap);
                // Nieuwe eigenschap aan zaak toevoegen

                continue;
            }
            // Extra element doesn't exist in eigenschappen
            $zaak->setValue('toelichting', "{$zaak->getValue('toelichting')}|{$extraElement->getValue('#')}"); //If the name of the attribute is desirable, add this to the string: {$extraElement->getValue('@naam')}:
        }
        $zaak->setValue('eigenschappen', $zaakEigenschappen);
    }

    /**
     * @param ObjectEntity $zdsObject
     * @param ObjectEntity $zaaktypeObjectEntity
     * @param ObjectEntity $zaak
     *
     * @throws Exception
     *
     * @return void The modified data of the call with the case type and identification
     */
    public function createNewZgwRolObject(ObjectEntity $zdsObject, ObjectEntity $zaaktypeObjectEntity, ObjectEntity $zaak): void
    {
        $rolTypeEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['rolTypeEntityId']);

        $roltype = new ObjectEntity($rolTypeEntity);
        $roltype->setValue('zaaktype', $zaaktypeObjectEntity->getUri());
        $roltype->setValue('omschrijving', $zdsObject->getValue('omschrijving'));
        $roltype->setValue('omschrijvingGeneriek', 'initiator');
        $this->entityManager->persist($roltype);
        $this->synchronizationService->setApplicationAndOrganization($roltype);

        $roltypen[] = $roltype;

        $zaaktypeObjectEntity->setValue('roltypen', $roltypen);

        $rol[] = $this->createZgwRollen($zdsObject, $zaak, $roltype);
        $zaak->setValue('rollen', $rol);
    }

    public function vestigingToOrganisatorischeEenheid(ObjectEntity $vestiging): array
    {
        return [
            'identificatie'  => $vestiging->getValue('vestigingsNummer'),
            'naam'		         => $vestiging->getValue('handelsnaam'),
            'isGehuisvestIn' => $vestiging->getValue('verblijfsadres')->getValue('wplWoonplaatsNaam'),
        ];
    }

    /**
     * @param ObjectEntity $zdsObject
     * @param ObjectEntity $zaak
     * @param ObjectEntity $roltype
     *
     * @throws Exception
     *
     * @return ObjectEntity|null The modified data of the call with the case type and identification
     */
    public function createZgwRollen(ObjectEntity $zdsObject, ObjectEntity $zaak, ObjectEntity $roltype): ?ObjectEntity
    {
        $rolEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['rolEntityId']);

        $heeftAlsInitiatorObject = $zdsObject->getValue('heeftAlsInitiator');
        if ($roltype->getValue('omschrijvingGeneriek') == 'initiator') {
            $rol = new ObjectEntity($rolEntity);
            $rol->setValue('zaak', $zaak);
            $rol->setValue('roltype', $roltype);
            $rol->setValue('omschrijving', $roltype->getValue('omschrijving'));
            $rol->setValue('omschrijvingGeneriek', $roltype->getValue('omschrijvingGeneriek'));
            $rol->setValue('roltoelichting', $zaak->getValue('toelichting'));

            if ($natuurlijkPersoonObject = $heeftAlsInitiatorObject->getValue('natuurlijkPersoon')) {
                $rol->setValue('betrokkeneIdentificatie', $natuurlijkPersoonObject->toArray());
                $rol->setValue('betrokkeneType', 'natuurlijk_persoon');
            }

            if ($heeftAlsInitiatorObject->getValue('vestiging')->getValue('vestigingsNummer') || $heeftAlsInitiatorObject->getValue('vestiging')->getValue('handelsnaam')) {
                $rol->setValue('betrokkeneIdentificatie', $this->vestigingToOrganisatorischeEenheid($heeftAlsInitiatorObject->getValue('vestiging')));
                $rol->setValue('betrokkeneType', 'organisatorische_eenheid');
                $this->synchronizationService->setApplicationAndOrganization($rol->getValue('betrokkeneIdentificatie'));
            }

            $this->entityManager->persist($rol);
            $this->synchronizationService->setApplicationAndOrganization($rol);

            return $rol;
        }

        return null;
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
     *
     * @todo Eigenschappen ophalen uit de zaaktype (zaaktypen uit contezza synchroniseren met de eigenschappen)
     * @todo ExtraElementen ophalen uit het zds bericht (extraElementen moeten met naam en value gemapt worden in het zds object)
     */
    public function zdsToZGWHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $zds = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
        $zaakEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakEntityId']);

        // @todo remove the check for identification and zaaktype if the dataService is implemented
        // @todo get in the zds object the values of the properties casetype and identification and store this in the case
        $zaakTypeIdentificatie = $this->getIdentifier($this->data['request']);
        if (!$zaakTypeIdentificatie) {
            // @todo fix error
            throw new ErrorException('The identificatie is not found');
        }

        $zdsObject = $zds->getValue('object');
        $zdsStuurgegevens = $zds->getValue('stuurgegevens');
        $zds->setExternalId($zdsObject->getValue('identificatie'));

        // Let get the zaaktype
        $zaaktypeObjectEntity = null;
        $zaaktypeValues = $this->entityManager->getRepository('App:Value')->findBy(['stringValue' => $zaakTypeIdentificatie]);
        foreach ($zaaktypeValues as $zaaktypeValue) {
            if ($zaaktypeValue->getObjectEntity()->getEntity()->getId() == $this->configuration['zaakTypeEntityId'] && $zaaktypeValue->getObjectEntity()->getValue('eindeGeldigheid') == null) {
                $zaaktypeObjectEntity = $zaaktypeValue->getObjectEntity();
            }
        }
        if (!$zaaktypeObjectEntity || !$zaaktypeObjectEntity instanceof ObjectEntity) {
            if (
                key_exists('enrichData', $this->configuration) &&
                $this->configuration['enrichData']
            ) {
                $zaakTypeEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakTypeEntityId']);

                $zaaktypeObjectEntity = new ObjectEntity($zaakTypeEntity);

                $zaaktypeArray = [
                    'identificatie' => $zaakTypeIdentificatie,
                    'omschrijving'  => $zdsObject->getValue('omschrijving'),
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
        $zaak->setValue('referentienummer', $zdsStuurgegevens->getValue('referentienummer'));
        $zaak->setValue('registratiedatum', $zdsObject->getValue('registratiedatum'));
        $zaak->setValue('omschrijving', $zdsObject->getValue('omschrijving'));
        $zaak->setValue('einddatumGepland', $zdsObject->getValue('einddatumGepland'));
        $zaak->setValue('uiterlijkeEinddatumAfdoening', $zdsObject->getValue('uiterlijkeEinddatum'));
        $zaak->setValue('betalingsindicatie', $zdsObject->getValue('betalingsIndicatie'));
        $zaak->setValue('laatsteBetaaldatum', $zdsObject->getValue('laatsteBetaaldatum'));
        $zaak->setValue('startdatum', $zdsObject->getValue('startdatum'));
        $zaak->setValue('zaaktype', $zaaktypeObjectEntity);
        $this->entityManager->persist($zaak);

        if ($zaaktypeObjectEntity->getValue('eigenschappen')->toArray()) {
            $this->createZgwZaakEigenschappen($zdsObject, $zaaktypeObjectEntity, $zaak);
        } elseif (
            key_exists('enrichData', $this->configuration) &&
            $this->configuration['enrichData']
        ) {
            $this->createNewZgwEigenschappen($zdsObject, $zaaktypeObjectEntity, $zaak);
        } else {
            throw new ErrorException('Cannot create zaakeigenschappen');
        }

        if (
            $zaaktypeObjectEntity->getValue('roltypen') &&
            count($zaaktypeObjectEntity->getValue('roltypen')) > 0
        ) {
            $roltypen = $zaaktypeObjectEntity->getValue('roltypen');
            $rollen = [];
            foreach ($roltypen as $roltype) {
                $rol = $this->createZgwRollen($zdsObject, $zaak, $roltype);
                !$rol ?: $rollen[] = $rol;
            }
            $zaak->setValue('rollen', $rollen);
        } elseif (
            key_exists('enrichData', $this->configuration) &&
            $this->configuration['enrichData']
        ) {
            $this->createNewZgwRolObject($zdsObject, $zaaktypeObjectEntity, $zaak);
        } else {
            throw new ErrorException('Cannot create rollen');
        }
        $this->entityManager->persist($zaak);
        $this->synchronizationService->setApplicationAndOrganization($zaak);

        $zds->setValue('zgwZaak', $zaak);

        $this->entityManager->persist($zds);
        $this->entityManager->flush();

        $this->data['response'] = $zds->toArray();

        return $this->data;
    }

    /**
     * @param ObjectEntity $zdsObject
     * @param ObjectEntity $zaaktypeObjectEntity
     * @param ObjectEntity $zaak
     *
     * @throws Exception
     *
     * @return void The modified data of the call with the case type and identification
     */
    public function createZgwZaakInformatieObject(ObjectEntity $zdsObject, ObjectEntity $zaak, ObjectEntity $document): void
    {
        $zaakInformatieObjectEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakInformatieObjectEntityId']);

        // create zaakinformatieobject
        $zaakinformatieobject = new ObjectEntity($zaakInformatieObjectEntity);
        $zaakinformatieobject->setValue('informatieobject', $document);
        $zaakinformatieobject->setValue('zaak', $zaak);
        $zaakinformatieobject->setValue('aardRelatieWeergave', $document->getValue('titel'));
        $zaakinformatieobject->setValue('titel', $document->getValue('titel'));
        $zaakinformatieobject->setValue('beschrijving', $document->getValue('beschrijving') ?? '');
        //        $zaakinformatieobject->setValue('registratiedatum', $document->getValue(''));
        $zaakinformatieobject = $this->synchronizationService->setApplicationAndOrganization($zaakinformatieobject);

        $this->entityManager->persist($zaakinformatieobject);
    }

    /**
     * Finds the ZGW Zaak entity if an zdsObjectEntity is provided.
     *
     * @param ObjectEntity $zdsObjectEntity The ZDS objecte
     *
     * @return Entity The entity for ZGW zaken
     */
    public function getZaakEntityFromZdsObjectEntity(ObjectEntity $zdsObjectEntity): Entity
    {
        return $zdsObjectEntity->getAttributeObject('zgwZaak')->getObject();
    }

    /**
     * @param array $data
     * @param array $configuration
     *
     * @throws ErrorException
     *
     * @return array
     */
    public function zdsToZGWDocumentenHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $zdsDocument = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
        $informatieObjectTypeEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['informatieObjectTypeEntityId']);
        $enkelvoudiginformatieobjectEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['enkelvoudigInformatieObjectEntityId']);
        $zaakTypeInformatieObjectTypeEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakTypeInformatieObjectTypeEntityId']);

        $zdsObject = $zdsDocument->getValue('object');
        $zdsIsRelevantVoor = $zdsObject->getValue('isRelevantVoor');
        $zaakIdentificatie = $zdsIsRelevantVoor->getValue('identificatie');

        // Let get the zaak
        $zdsZaakObjectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findByAnyId($zaakIdentificatie);

        if (!$zdsZaakObjectEntity) {
            $zaken = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($this->getZaakEntityFromZdsObjectEntity($zdsDocument), ['identificatie' => $zaakIdentificatie]);
            $zaak = count($zaken) > 0 ? $zaken[0] : null;
        } else {
            $zaak = $zdsZaakObjectEntity->getValue('zgwZaak');
        }

        if (!$zaak) {
            throw new ErrorException("The zaak with identificatie $zaakIdentificatie can't be found");
        }

        // Let get the informatieobjecttypen
        for ($i = 0; $i < 20; $i++) {
            $informatieobjecttypenObjectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($informatieObjectTypeEntity, ['omschrijving' => $zdsObject->getValue('dctOmschrijving')]);
            if (count($informatieobjecttypenObjectEntity) > 0) {
                break;
            } else {
                sleep(1);
            }
        }
        if (count($informatieobjecttypenObjectEntity) == 0 || !$informatieobjecttypenObjectEntity[0] instanceof ObjectEntity) {
            if (
                key_exists('enrichData', $this->configuration) &&
                $this->configuration['enrichData']
            ) {
                $informatieobjecttypenObjectEntity = new ObjectEntity($informatieObjectTypeEntity);

                $informatieobjecttypenArray = [
                    'omschrijving'                 => $zdsObject->getValue('omschrijving'),
                    'vertrouwelijkheidaanduiding'  => 'zaakvertrouwelijk ',
                    'beginGeldigheid'              => $zdsObject->getValue('creatiedatum'),
                    'eindeGeldigheid'              => null,
                ];

                $informatieobjecttypenObjectEntity->hydrate($informatieobjecttypenArray);
                $this->entityManager->persist($informatieobjecttypenObjectEntity);
                $informatieobjecttypenObjectEntity->setValue('url', $informatieobjecttypenObjectEntity->getValue('url'));
            } else {
                // @todo fix error
                throw new ErrorException('The informatieobjecttypen with omschrijving: '.$zdsObject->getValue('dctOmschrijving').' can\'t be found');
            }
        } else {
            foreach ($informatieobjecttypenObjectEntity as $informatieObjectType) {
                if ($this->entityManager->getRepository('App:ObjectEntity')->findByEntity($zaakTypeInformatieObjectTypeEntity, ['zaaktype' => $zaak->getValue('zaaktype')->getValue('url'), 'informatieobjecttype' => $informatieObjectType->getValue('url')])) {
                    $informatieobjecttypenObjectEntity = $informatieObjectType;
                    break;
                }
                if (is_array($informatieObjectTypeEntity) && !isset($this->configuration['enrichData'])) {
                    throw new ErrorException('The zaaktypen-informatieobjecttypen with omschrijving: '.$zdsObject->getValue('dctOmschrijving').' can\'t be found');
                }
            }
        }

        // Lets start by setting up the document
        $document = new ObjectEntity($enkelvoudiginformatieobjectEntity);
        $document->setValue('identificatie', $zdsObject->getValue('identificatie'));
        $document->setValue('creatiedatum', $zdsObject->getValue('creatiedatum'));
        $document->setValue('titel', $zdsObject->getValue('titel'));
        $document->setValue('auteur', $zdsObject->getValue('auteur'));
        $document->setValue('status', strtolower($zdsObject->getValue('status')));
        $document->setValue('formaat', $zdsObject->getValue('formaat'));
        $document->setValue('taal', $zdsObject->getValue('taal'));
        $document->setValue('inhoud', $zdsObject->getValue('inhoud'));
        $document->setValue('beschrijving', $zdsObject->getValue('dctOmschrijving'));
        $document->setValue('informatieobjecttype', $informatieobjecttypenObjectEntity->getValue('url'));
        $document->setValue('vertrouwelijkheidaanduiding', $informatieobjecttypenObjectEntity->getValue('vertrouwelijkheidaanduiding'));

        //        $document->setValue('indicatieGebruiksrecht', $zdsObject->getValue(''));
        //        $document->setValue('bestandsnaam', $zdsObject->getValue(''));
        //        $document->setValue('ontvangstdatum', $zdsObject->getValue(''));
        //        $document->setValue('verzenddatum', $zdsObject->getValue('')); // stuurgegevens.tijdstipBericht
        $this->entityManager->persist($document);
        $this->synchronizationService->setApplicationAndOrganization($document);

        $this->createZgwZaakInformatieObject($zdsObject, $zaak, $document);

        if (isset($zdsZaakObjectEntity)) {
            $zdsZaakObjectEntity->setValue('zgwDocument', $document);
            $this->entityManager->persist($zdsZaakObjectEntity);
        }
        $zdsDocument->setValue('zgwDocument', $document);
        $zdsDocument->setValue('zgwZaak', $zaak);

        $this->entityManager->persist($document);
        $this->entityManager->persist($zdsDocument);
        $this->entityManager->persist($zaak);
        $this->entityManager->flush();

        return $this->data;
    }

    /**
     * @param array  $objectEntities
     * @param string $attributeName
     *
     * @return void
     */
    public function addObjectToZgwZaaktype(array $objectEntities, string $attributeName): void
    {
        foreach ($objectEntities as $objectEntity) {
            if ($objectEntity->getValue('zaaktype') !== null) {
                $zaaktype = $this->entityManager->getRepository('App:ObjectEntity')->findByAnyId($objectEntity->getValue('zaaktype'));
                if ($zaaktype == null) {
                    continue;
                }
                $zaaktype->getValueObject($attributeName)->addObject($objectEntity);
                $this->entityManager->persist($zaaktype);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @param array $data          The data from the call
     * @param array $configuration The configuration array from the action
     *
     * @return array The modified data of the call with the case type and identification
     */
    public function zgwZaaktypeHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $eigenschapObjectEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['eigenschapEntityId']);
        $eigenschappen = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $eigenschapObjectEntity]);
        $this->addObjectToZgwZaaktype($eigenschappen, 'eigenschappen');

        $roltypenObjectEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['roltypenEntityId']);
        $roltypen = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $roltypenObjectEntity]);
        $this->addObjectToZgwZaaktype($roltypen, 'roltypen');

        $resultaattypenObjectEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['resultaattypenEntityId']);
        $resultaattypen = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $resultaattypenObjectEntity]);
        $this->addObjectToZgwZaaktype($resultaattypen, 'resultaattypen');

        $statustypenObjectEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['statustypenEntityId']);
        $statustypen = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $statustypenObjectEntity]);
        $this->addObjectToZgwZaaktype($statustypen, 'statustypen');

        return $this->data;
    }

    /**
     * @param ObjectEntity $objectEntity The object entity that relates to the entity Eigenschap
     * @param array        $data         The data array
     *
     * @return Synchronization
     *
     * @todo wat doet dit?
     */
    public function createSynchronization(ObjectEntity $objectEntity, array $data): Synchronization
    {
        $zrcSource = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zrcSourceId']);

        $synchronization = new Synchronization();
        // @todo als er s'n sterke behoefte is om deze dingen meteen te kunnen zetten mogen ze in een magic constructor
        $synchronization->setObject($objectEntity);
        $synchronization->setEntity($objectEntity->getEntity());
        $synchronization->setGateway($zrcSource);

        //TODO: is this right this way? Feels very hardcoded
        //TODO: use twig parser on this instead
        $synchronization->setEndpoint("/zaken/{$data['response']['uuid']}/zaakeigenschappen");

        // @todo waar is de flush?
        $this->entityManager->persist($synchronization);

        return $synchronization;
    }

    /**
     * Adds the 'isVan' block to a ZDS object.
     *
     * @param ObjectEntity $zdsObject The ZDS object to add the block to
     * @param ObjectEntity $zaak      The case to read the case type from
     *
     * @throws Exception
     *
     * @return ObjectEntity The resulting 'isVan' block
     */
    public function getIsVan(ObjectEntity $zdsObject, ObjectEntity $zaak): ObjectEntity
    {
        $zdsIsVan = new ObjectEntity($zdsObject->getAttributeObject('isVan')->getObject());
        $zdsIsVan->setValue('omschrijving', $zaak->getValue('zaaktype')->getValue('omschrijving'));
        $zdsIsVan->setValue('code', $zaak->getValue('zaaktype')->getValue('identificatie'));

        return $zdsIsVan;
    }

    /**
     * Creates the 'address' block to a natural person block.
     *
     * @param ObjectEntity $zdsNatuurlijkPersoon The natural person to add the address to
     * @param ObjectEntity $rol                  The role to read the data from
     *
     * @throws Exception
     *
     * @return ObjectEntity The resulting address block
     */
    public function getVerblijfadres(ObjectEntity $zdsNatuurlijkPersoon, ObjectEntity $rol): ObjectEntity
    {
        $zdsVerblijfsadres = new ObjectEntity($zdsNatuurlijkPersoon->getAttributeObject('verblijfadres')->getObject());
        $zdsVerblijfsadres->setValue('wplWoonplaatsNaam', $rol->getValue('betrokkeneIdentificatie')->getValue('verblijfadres')->getValue('wplWoonplaatsNaam'));
        $zdsVerblijfsadres->setValue('gorOpenbareRuimteNaam', $rol->getValue('betrokkeneIdentificatie')->getValue('verblijfadres')->getValue('gorOpenbareRuimteNaam'));
        $zdsVerblijfsadres->setValue('aoaPostcode', $rol->getValue('betrokkeneIdentificatie')->getValue('verblijfadres')->getValue('aoaPostcode'));
        $zdsVerblijfsadres->setValue('aoaHuisnummer', $rol->getValue('betrokkeneIdentificatie')->getValue('verblijfadres')->getValue('aoaHuisnummer'));
        $zdsVerblijfsadres->setValue('aoaHuisletter', $rol->getValue('betrokkeneIdentificatie')->getValue('verblijfadres')->getValue('aoaHuisletter'));
        $zdsVerblijfsadres->setValue('aoaHuisnummertoevoeging', $rol->getValue('betrokkeneIdentificatie')->getValue('verblijfadres')->getValue('aoaHuisnummertoevoeging'));

        return $zdsVerblijfsadres;
    }

    /**
     * Creates a natural person block for a role.
     *
     * @param ObjectEntity $zdsHeeftAlsInitiator The object to add the person to
     * @param ObjectEntity $rol                  The role to read the data from
     *
     * @throws Exception
     *
     * @return ObjectEntity The resulting natural person block
     */
    public function getNatuurlijkPersoon(ObjectEntity $zdsHeeftAlsInitiator, ObjectEntity $rol): ObjectEntity
    {
        $zdsNatuurlijkPersoon = new ObjectEntity($zdsHeeftAlsInitiator->getAttributeObject('natuurlijkPersoon')->getObject());
        $zdsNatuurlijkPersoon->setValue('inpBsn', $rol->getValue('betrokkeneIdentificatie')->getValue('inpBsn'));
        $zdsNatuurlijkPersoon->setValue('geslachtsnaam', $rol->getValue('betrokkeneIdentificatie')->getValue('geslachtsnaam'));
        $zdsNatuurlijkPersoon->setValue('voorvoegselGeslachtsnaam', $rol->getValue('betrokkeneIdentificatie')->getValue('voorvoegselGeslachtsnaam'));
        $zdsNatuurlijkPersoon->setValue('voorletters', $rol->getValue('betrokkeneIdentificatie')->getValue('voorletters'));
        $zdsNatuurlijkPersoon->setValue('voornamen', $rol->getValue('betrokkeneIdentificatie')->getValue('voornamen'));
        $zdsNatuurlijkPersoon->setValue('geslachtsaanduiding', $rol->getValue('betrokkeneIdentificatie')->getValue('geslachtsaanduiding'));
        $zdsNatuurlijkPersoon->setValue('geboortedatum', $rol->getValue('betrokkeneIdentificatie')->getValue('geboortedatum'));
        $zdsNatuurlijkPersoon->setValue('verblijfadres', $rol->getValue('verblijfsadres') ? $this->getVerblijfadres($zdsNatuurlijkPersoon, $rol) : null);

        return $zdsNatuurlijkPersoon;
    }

    /**
     * Get the initiator block for an object from a role.
     *
     * @param ObjectEntity $zdsObject The ZDS object to add the initiator block to
     * @param ObjectEntity $rol       The role to read the initiator block from
     *
     * @throws Exception
     *
     * @return ObjectEntity The hasAsInitiator block
     */
    public function getHeeftAlsInitiator(ObjectEntity $zdsObject, ObjectEntity $rol): ObjectEntity
    {
        $zdsHeeftAlsInitiator = new ObjectEntity($zdsObject->getAttributeObject('heeftAlsInitiator')->getObject());
        $zdsVestiging = new ObjectEntity($zdsHeeftAlsInitiator->getAttributeObject('vestiging')->getObject());

        $zdsHeeftAlsInitiator->setValue('natuurlijkPersoon', $this->getNatuurlijkPersoon($zdsHeeftAlsInitiator, $rol));

        return $zdsHeeftAlsInitiator;
    }

    /**
     * Creates the Heeft subarray of a ZDS object.
     *
     * @param ObjectEntity $zdsObject The ZDS object to create the subarray for
     * @param ObjectEntity $zaak      The case related to the ZDS object
     *
     * @throws Exception
     *
     * @return array The resulting Heeft subarray
     */
    public function getHeeft(ObjectEntity $zdsObject, ObjectEntity $zaak): array
    {
        $statusEntity = $zaak->getAttributeObject('status')->getObject();
        $statussen = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($statusEntity, ['zaak.id' => $zaak->getId()->toString()]); //, ['datumStatusGezet' => 'desc']

        $heeft = [];
        foreach ($statussen as $status) {
            $dateSet = new DateTime($status->getValue('datumStatusGezet'));
            $gerelateerde = new ObjectEntity($zdsObject->getAttributeObject('heeft')->getObject());
            $gerelateerde->setValue('omschrijving', $status->getValue('statustoelichting'));
            $gerelateerde->setValue('omschrijvingGeneriek', strtolower($status->getValue('statustoelichting')));
            $gerelateerde->setValue('toelichting', $status->getValue('statustoelichting'));
            $gerelateerde->setValue('datumStatusGezet', $dateSet->format('YmdHisv'));
            $heeft[] = $gerelateerde;
        }

        return $heeft;
    }

    /**
     * Finds the enkelvoudigInformatieObject for a zaakInformatieObject.
     *
     * @param ObjectEntity $document The zaakInformatieObject to find the enkelvoudigInformatieObject for
     *
     * @return ObjectEntity|null
     */
    private function getInformatieObject(ObjectEntity $document): ?ObjectEntity
    {
        $values = $this->entityManager->getRepository(Value::class)->findBy(['stringValue' => $document->getValue('informatieobject')]);
        foreach ($values as $value) {
            if ($value instanceof Value && $value->getAttribute()->getName() == 'url') {
                $enkelvoudigInformatieObject = $value->getObjectEntity();

                return $enkelvoudigInformatieObject;
            }
        }

        return null;
    }

    /**
     * Creates the HeeftRelevant subarray of a ZDS object.
     *
     * @param ObjectEntity $zdsObject The ZDS object to create the subarray for
     * @param ObjectEntity $zaak      The zaak related to the ZDS object
     *
     * @throws Exception
     *
     * @return array The HeeftRelevant subarray
     */
    public function getHeeftRelevant(ObjectEntity $zdsObject, ObjectEntity $zaak): array
    {
        $documentEntity = $zaak->getAttributeObject('zaakinformatieobjecten')->getObject();
        $documenten = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($documentEntity, ['zaak.id' => $zaak->getId()->toString()]);

        $heeftRelevant = [];
        foreach ($documenten as $document) {
            $enkelvoudigInformatieObject = $this->getInformatieObject($document);
            if (!$enkelvoudigInformatieObject) {
                continue;
            }
            $createDate = new DateTime($enkelvoudigInformatieObject->getValue('creatiedatum'));
            $gerelateerde = new ObjectEntity($zdsObject->getAttributeObject('heeftRelevant')->getObject());
            $gerelateerde->setValue('identificatie', $enkelvoudigInformatieObject->getValue('identificatie'));
            $gerelateerde->setValue('creatiedatum', $createDate->format('Ymd'));
            $gerelateerde->setValue('titel', $enkelvoudigInformatieObject->getValue('titel'));
            $gerelateerde->setValue('formaat', $enkelvoudigInformatieObject->getValue('formaat'));
            $gerelateerde->setValue('taal', $enkelvoudigInformatieObject->getValue('taal'));
            $gerelateerde->setValue('status', $enkelvoudigInformatieObject->getValue('status'));
            $gerelateerde->setValue('vertrouwelijkAanduiding', $enkelvoudigInformatieObject->getValue('vertrouwelijkAanduiding'));
            $gerelateerde->setValue('auteur', $enkelvoudigInformatieObject->getValue('auteur'));
            $gerelateerde->setValue('link', $enkelvoudigInformatieObject->getValue('inhoud'));

            $heeftRelevant[] = $gerelateerde;
        }

        return $heeftRelevant;
    }

    /**
     * Creates a ZDS zaak-object.
     *
     * @param ObjectEntity $zds  The ZDS object to write the object to
     * @param ObjectEntity $zaak The case to read the data for the object from
     * @param ObjectEntity $rol  The role for the initiator
     *
     * @throws Exception
     *
     * @return ObjectEntity The resulting ZDS object
     */
    public function getZaakObject(ObjectEntity $zds, ObjectEntity $zaak, ObjectEntity $rol): ObjectEntity
    {
        $zdsObject = new ObjectEntity($zds->getAttributeObject('object')->getObject());
        $zdsObject->setValue('identificatie', $zaak->getValue('identificatie'));
        $zdsObject->setValue('registratiedatum', $zaak->getValue('registratiedatum'));
        $zdsObject->setValue('toelichting', $zaak->getValue('toelichting'));
        $zdsObject->setValue('omschrijving', $zaak->getValue('omschrijving'));
        $zdsObject->setValue('einddatumGepland', $zaak->getValue('einddatumGepland'));
        $zdsObject->setValue('uiterlijkeEinddatumAfdoening', $zaak->getValue('uiterlijkeEinddatum'));
        $zdsObject->setValue('betalingsindicatie', $zaak->getValue('betalingsIndicatie'));
        $zdsObject->setValue('laatsteBetaaldatum', $zaak->getValue('laatsteBetaaldatum'));
        $zdsObject->setValue('startdatum', $zaak->getValue('startdatum'));
        $zdsObject->setValue('isVan', $this->getIsVan($zdsObject, $zaak));
        $zdsObject->setValue('heeftAlsInitiator', $this->getHeeftAlsInitiator($zdsObject, $rol));
        $zdsObject->setValue('heeft', $this->getHeeft($zdsObject, $zaak));
        $zdsObject->setValue('heeftRelevant', $this->getHeeftRelevant($zdsObject, $zaak));
        $this->entityManager->persist($zdsObject);
        $this->entityManager->flush();

        return $zdsObject;
    }

    /**
     * Decides the message parameters for messages translated from SimXML to ZDS.
     *
     * @param ObjectEntity $simXML     The SimXML data
     * @param string       $entityType The entity type of the data
     *
     * @throws Exception
     *
     * @return array The resulting header block
     */
    public function getStuurgegevensFromSimXML(ObjectEntity $simXML, string $entityType): array
    {
        $simXMLStuurGegevens = $simXML->getValue('stuurgegevens');

        return [
            'berichtcode' => 'Lk01',
            'zender'      => [
                'applicatie' => $simXMLStuurGegevens->getValue('Zender'),
            ],
            'ontvanger' => [
                'applicatie' => $simXMLStuurGegevens->getValue('Ontvanger'),
            ],
            'tijdstipBericht'  => $simXMLStuurGegevens->getValue('Datum'),
            'referentienummer' => Uuid::uuid4(),
            'crosRefnummer'    => Uuid::uuid4(),
            'entiteitType'     => $entityType,
        ];
    }

    /**
     * Creates extra elementen in a ZDS object created from a ZGW case.
     *
     * @param ObjectEntity $zds  The ZDS object to write to
     * @param ObjectEntity $zaak The case to get the elements from
     *
     * @return ObjectEntity The resulting ZDS object
     */
    public function getExtraElementen(ObjectEntity $zds, ObjectEntity $zaak): ObjectEntity
    {
        $items = $zaak->getValue('eigenschappen');
        $elements = [];
        foreach ($items as $item) {
            $elements[] = [
                '@naam' => $item->getValue('naam'),
                '#'     => $item->getValue('waarde'),
            ];
        }

        $zds->getValue('object')->setValue('extraElementen', $elements);

        return $zds;
    }

    /**
     * Translates ZGW cases to ZDS.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the zaakeigenschap action
     *
     * @return array
     */
    public function zgwToZdsHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

        $result = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);

        $zakenEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakEntityId']);
        $rolEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['rolEntityId']);

        if ($result->getValue('object') && $result->getValue('object')->getValue('identificatie')) {
            $rollen = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($rolEntity, ['omschrijvingGeneriek' => 'initiator'],['_dateCreated' => 'DESC'],0, 250);
            $zaak = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($zakenEntity, ['identificatie' => $result->getValue('object')->getValue('identificatie')])[0];
            foreach ($rollen as $rol) {
                if ($rol->getValue('zaak') == $zaak) {
                    break;
                }
            }
            $result->setValue('object', $this->getZaakObject($result, $zaak, $rol));
            $data['response'] = $result->toArray();
        } elseif ($result->getValue('object') && $result->getValue('object')->getValue('heeftAlsInitiator')->getValue('natuurlijkPersoon')->getValue('inpBsn')) {
            $rollen = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($rolEntity, ['omschrijvingGeneriek' => 'initiator', 'betrokkeneIdentificatie.inpBsn' => $result->getValue('object')->getValue('heeftAlsInitiator')->getValue('natuurlijkPersoon')->getValue('inpBsn')],['_dateCreated' => 'DESC'],0, 250);
            $zaken = [];
            foreach ($rollen as $rol) {
                if ($rol->getValue('betrokkeneIdentificatie')->getValue('inpBsn') == $result->getValue('object')->getValue('heeftAlsInitiator')->getValue('natuurlijkPersoon')->getValue('inpBsn')) {
                    $zaken[] = $this->getZaakObject($result, $rol->getValue('zaak'), $rol)->toArray();
                }
            }
            $result = $result->toArray();
            $result['object'] = $zaken;
            $data['response'] = $result;
        } elseif ($result->getValue('Body') && $result->getValue('zgwZaak')) {
            $rollen = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($rolEntity, ['omschrijvingGeneriek' => 'initiator'],['_dateCreated' => 'DESC'],0, 250);
            $zaak = $this->entityManager->getRepository(ObjectEntity::class)->find($result->getValue('zgwZaak'));
            foreach ($rollen as $rol) {
                if ($rol->getValue('zaak') && $rol->getValue('zaak') == $zaak) {
                    break;
                }
            }

            $zds = new ObjectEntity($this->entityManager->getRepository(Entity::class)->find($this->configuration['zdsEntityId']));
            $zds->setValue('object', $this->getZaakObject($zds, $zaak, $rol));
            $zds->setValue('stuurgegevens', $this->getStuurgegevensFromSimXML($result, 'ZAK'));
            $zds = $this->getExtraElementen($zds, $zaak);
            $this->entityManager->persist($zds);
            $this->entityManager->flush();
        }

        return $data;
    }

    /**
     * Creates a Di02 message for either cases or documents.
     *
     * @param string $messageType     The message type that has to be launched, either for documents or cases
     * @param string $referenceNumber The reference number to use
     *
     * @return string The xml encoded Di02 message
     */
    public function createDi02Message(string $messageType, string $referenceNumber): string
    {
        $now = new \DateTime();
        $array = [
            '@xmlns:SOAP-ENV'   => 'http://schemas.xmlsoap.org/soap/envelope/',
            '@xmlns:ns1'        => 'http://www.egem.nl/StUF/StUF0301',
            '@xmlns:ns2'        => 'http://www.egem.nl/StUF/sector/zkn/0310',
            'SOAP-ENV:Body'     => [
                "ns2:{$messageType}_Di02"  => [
                    'ns2:stuurgegevens' => [
                        'ns1:berichtcode'       => 'Di02',
                        'ns1:zender'            => $this->configuration['xmlZender'],
                        'ns1:ontvanger'         => $this->configuration['xmlOntvanger'],
                        'ns1:referentienummer'  => $referenceNumber,
                        'ns1:tijdstipBericht'   => $now->format('Ymdhisu'),
                        'ns1:functie'           => $messageType,
                    ],
                ],
            ],
        ];
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'SOAP-ENV:Envelope']);

        return $xmlEncoder->encode($array, 'xml');
    }

    /**
     * Decodes a Du02 message and derives the identifier from it.
     *
     * @param string $Du02 The Du02 response message
     *
     * @throws Exception
     *
     * @return string The identifier in the Du02 response
     */
    public function getIdentifierFromDu02(string $Du02): string
    {
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'SOAP-ENV:Envelope']);
        $array = $xmlEncoder->decode($Du02, 'xml');

        if (isset($array['SOAP-ENV:Body']['ZKN:genereerZaakIdentificatie_Du02']['ZKN:zaak']['ZKN:identificatie'])) {
            return $array['SOAP-ENV:Body']['ZKN:genereerZaakIdentificatie_Du02']['ZKN:zaak']['ZKN:identificatie'];
        } else {
            throw new Exception('Creating case identifier resulted in an error.');
        }
    }

    public function getIdentificationForObject(string $messageType): string
    {
        $body = $this->createDi02Message($messageType, Uuid::uuid4());
        $source = $this->entityManager->getRepository(Gateway::class)->find($this->configuration['source']);
        $du02 = $this->commonGroundService->callService($source->toArray(), $source->getLocation().($this->configuration['apiSource']['location'] ?? ''), $body, [], [], false, 'POST');

        return $this->getIdentifierFromDu02($du02->getBody()->getContents());
    }

    /**
     * Checks if the case has an identification field and if not, launches a Di02 request to add it.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the zaakeigenschap action
     *
     * @throws Exception
     *
     * @return array
     */
    public function identificationHandler(array $data, array $configuration): array
    {
        $result = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
        $zaak = $this->entityManager->getRepository(ObjectEntity::class)->find($result->getValue('zgwZaak'));

        $this->configuration = $configuration;
        if ($this->configuration['entityType'] == 'ZAK' && !$zaak->getValue('identificatie')) {
            $messageType = 'genereerZaakIdentificatie';
            $zaak->setValue('identificatie', $this->getIdentificationForObject($messageType));
            $this->entityManager->persist($zaak);
        } else {
            $messageType = 'genereerDocumentIdentificatie';
            foreach ($result->getValue('zgwDocumenten') as $documentId) {
                $document = $this->entityManager->getRepository(ObjectEntity::class)->find($documentId);
                $document->getValue('identificatie') ?: $document->setValue('identificatie', $this->getIdentificationForObject($messageType));
                $this->entityManager->persist($document);
            }
        }
        $this->entityManager->flush();

        return $data;
    }

    public function getIsRelevantVoor(ObjectEntity $document, ObjectEntity $zaak, ObjectEntity $zdsObject): ObjectEntity
    {
        $zdsIsRelevantVoor = new ObjectEntity($zdsObject->getAttributeObject('isRelevantVoor')->getObject());
        $zdsIsRelevantVoor->setValue('identificatie', $zaak->getValue('identificatie'));

        $this->entityManager->persist($zdsIsRelevantVoor);

        return $zdsIsRelevantVoor;
    }

    public function getDocumentObject(ObjectEntity $zds, ObjectEntity $document, ObjectEntity $zaak): ObjectEntity
    {
        $now = new \DateTime();
        $zdsObject = new ObjectEntity($zds->getAttributeObject('object')->getObject());
        $zdsObject->setValue('identificatie', $document->getValue('informatieobject')->getValue('identificatie'));
        $zdsObject->setValue('dctOmschrijving', $document->getValue('informatieobject')->getValue('beschrijving'));
        $zdsObject->setValue('creatiedatum', $now->format('Ymd'));
        $zdsObject->setValue('ontvangstdatum', $now->format('Ymd'));
        $zdsObject->setValue('titel', $document->getValue('informatieobject')->getValue('titel'));
        $zdsObject->setValue('beschrijving', $document->getValue('informatieobject')->getValue('beschrijving'));
        $zdsObject->setValue('formaat', $document->getValue('informatieobject')->getValue('formaat'));
        $zdsObject->setValue('taal', $document->getValue('informatieobject')->getValue('taal'));
        $zdsObject->setValue('status', $document->getValue('informatieobject')->getValue('status'));
        $zdsObject->setValue('vertrouwelijkAanduiding', $document->getValue('informatieobject')->getValue('vertrouwelijkAanduiding'));
        $zdsObject->setValue('auteur', $document->getValue('informatieobject')->getValue('auteur'));
        $zdsObject->setValue('inhoud', $document->getValue('informatieobject')->getValue('inhoud'));
        $zdsObject->setValue('isRelevantVoor', $this->getIsRelevantVoor($document, $zaak, $zdsObject));

        $this->entityManager->persist($zdsObject);

        return $zdsObject;
    }

    public function zgwObjectInformatieObjectToZdsDocumentHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $objectInformatieObjectEntity = $this->entityManager->getRepository(Entity::class)->find($this->configuration['documentEntityId']);
        $result = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
        $documenten = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($objectInformatieObjectEntity, ['object' => $result->getValue('zgwZaak')],['_dateCreated' => 'DESC'],0, 250);
        $zaak = $this->entityManager->getRepository(ObjectEntity::class)->find($result->getValue('zgwZaak'));
        foreach ($documenten as $document) {
            $zds = new ObjectEntity($this->entityManager->getRepository(Entity::class)->find($this->configuration['zdsEntityId']));
            $zds->setValue('referentienummer', Uuid::uuid4());
            $zds->setValue('object', $this->getDocumentObject($zds, $document, $zaak));

            $zds->setValue('stuurgegevens', $this->getStuurgegevensFromSimXML($result, 'EDC'));
            $this->synchronizationService->setApplicationAndOrganization($zds);
            $zds->getEntity()->addObjectEntity($zds);
            $this->entityManager->persist($zds);
            $this->entityManager->flush();
        }

        return $this->data;
    }

    /**
     * Updates all zaakInformatieObjecten with correct URLs before syncing them to ZGW.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration array from the action
     *
     * @throws Exception
     *
     * @return array The data from the call
     */
    public function zaakInformatieObjectHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $informatieObject = $this->entityManager->getRepository(ObjectEntity::class)->find($this->data['response']['id'])->getValue('zgwDocument');
        if (!$informatieObject instanceof ObjectEntity) {
            return $this->data;
        }

        $zaakInformatieObjectEntity = $this->entityManager->getRepository(Entity::class)->findOneBy(['id' => $this->configuration['zaakInformatieObjectEntityId']]);
        $zaakInformatieObjecten = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($zaakInformatieObjectEntity, ['informatieobject' => $informatieObject->getId()->toString()]);
        foreach ($zaakInformatieObjecten as $zaakInformatieObject) {
            if (!$zaakInformatieObject instanceof ObjectEntity || $zaakInformatieObject->getEntity() !== $zaakInformatieObjectEntity) {
                continue;
            }
            $zaakInformatieObject->setValue('informatieobject', $informatieObject);
            $this->entityManager->persist($zaakInformatieObject);
        }
        $this->entityManager->flush();

        return $this->data;
    }
}
