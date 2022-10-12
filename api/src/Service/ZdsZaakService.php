<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
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
        foreach ($extraElementen as $extraElement) {
            // Extra element does exist in eigenschappen
            if (array_key_exists($extraElement->getValue('@naam'), $eigenschappenArray) && !in_array($extraElement->getValue('@naam'), $unusedExtraElements)) {

                // Eigenschap type
                $eigenschapType = $eigenschappenArray[$extraElement->getValue('@naam')];

                // Nieuwe eigenschap aanmaken
                $zaakEigenschap = new ObjectEntity($zaakEigenschapEntity);
                $zaakEigenschap->setValue('type', $eigenschapType->getValue('definitie'));
                $zaakEigenschap->setValue('eigenschap', $eigenschapType->getValue('url'));
                $zaakEigenschap->setValue('naam', $extraElement->getValue('@naam'));
                $zaakEigenschap->setValue('waarde', $extraElement->getValue('#'));
                $zaakEigenschap->setValue('zaak', $zaak);
                $zaakEigenschap->setValue('eigenschap', $eigenschapType->getValue('url'));

                $this->entityManager->persist($zaakEigenschap);
                // Nieuwe eigenschap aan zaak toevoegen

                continue;
            }
            // Extra element doesn't exist in eigenschappen
            $zaak->setValue('toelichting', "{$zaak->getValue('toelichting')}\n{$extraElement->getValue('@naam')}: {$extraElement->getValue('#')}");
        }
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
    public function createZgwRollen(ObjectEntity $zdsObject, ObjectEntity $zaaktypeObjectEntity, ObjectEntity $zaak): void
    {
        $rolEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['rolEntityId']);

        $heeftAlsInitiatorObject = $zdsObject->getValue('heeftAlsInitiator');
        $roltypen = $zaaktypeObjectEntity->getValue('roltypen');
        foreach ($roltypen as $roltype) {
            if ($roltype->getValue('omschrijvingGeneriek') == 'initiator') {
                $rol = new ObjectEntity($rolEntity);
                $rol->setValue('zaak', $zaak);
                $rol->setValue('roltype', $roltype);
                $rol->setValue('omschrijving', $roltype->getValue('omschrijving'));
                $rol->setValue('omschrijvingGeneriek', $roltype->getValue('omschrijvingGeneriek'));
                $rol->setValue('roltoelichting', 'indiener');

                if ($natuurlijkPersoonObject = $heeftAlsInitiatorObject->getValue('natuurlijkPersoon')) {
                    $rol->setValue('betrokkeneIdentificatie', $natuurlijkPersoonObject);
                    $rol->setValue('betrokkeneType', 'natuurlijk_persoon');
                }

                if ($heeftAlsInitiatorObject->getValue('vestiging')->getValue('vestigingsNummer') || $heeftAlsInitiatorObject->getValue('vestiging')->getValue('handelsnaam')) {
                    $rol->setValue('betrokkeneIdentificatie', $heeftAlsInitiatorObject->getValue('vestiging'));
                    $rol->setValue('betrokkeneType', 'vestiging');
                }

                $this->entityManager->persist($rol);
            }
        }
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

        // Let get the zaaktype
        $zaaktypeObjectEntity = $this->entityManager->getRepository('App:Value')->findOneBy(['stringValue' => $zaakTypeIdentificatie])->getObjectEntity();
        if (!$zaaktypeObjectEntity && !$zaaktypeObjectEntity instanceof ObjectEntity) {
            // @todo fix error
            throw new ErrorException('The zaakType with identificatie: '.$zaakTypeIdentificatie.' can\'t be found');
        }

        $zdsObject = $zds->getValue('object');
        $zdsStuurgegevens = $zds->getValue('stuurgegevens');
        $zds->setExternalId($zdsObject->getValue('identificatie'));

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

        $this->createZgwZaakEigenschappen($zdsObject, $zaaktypeObjectEntity, $zaak);
        $this->createZgwRollen($zdsObject, $zaaktypeObjectEntity, $zaak);

        $this->entityManager->persist($zaak);

        $zds->setValue('zgwZaak', $zaak);

        $this->entityManager->persist($zds);
        $this->entityManager->flush();

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
    public function createZgwZaakInformatieObject(ObjectEntity $zdsObject, ObjectEntity $zdsZaakObjectEntity, ObjectEntity $document): void
    {
        $zaakInformatieObjectEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakInformatieObjectEntityId']);

        // create zaakinformatieobject
        $zaakinformatieobject = new ObjectEntity($zaakInformatieObjectEntity);
        $zaakinformatieobject->setValue('informatieobject', $document->getValue('url'));
        $zaakinformatieobject->setValue('zaak', $zdsZaakObjectEntity->getValue('zgwZaak'));
        $zaakinformatieobject->setValue('aardRelatieWeergave', $zdsObject->getValue('titel'));
        $zaakinformatieobject->setValue('titel', $document->getValue('titel'));
        $zaakinformatieobject->setValue('beschrijving', $document->getValue('beschrijving'));
//        $zaakinformatieobject->setValue('registratiedatum', $document->getValue(''));

        $this->entityManager->persist($zaakinformatieobject);
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

        $zdsObject = $zdsDocument->getValue('object');
        $zdsIsRelevantVoor = $zdsObject->getValue('isRelevantVoor');
        $zaakIdentificatie = $zdsIsRelevantVoor->getValue('identificatie');

        // Let get the zaak
        $zdsZaakObjectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findByAnyId($zaakIdentificatie);
        if (!$zdsZaakObjectEntity && !$zdsZaakObjectEntity instanceof ObjectEntity) {
            // @todo fix error
            throw new ErrorException('The zaak with referentienummer: '.$zaakIdentificatie.' can\'t be found');
        }

        if (!$zdsZaakObjectEntity->getValue('zgwZaak')) {
            throw new ErrorException('The zaak with can\'t be found');
        }

        // Let get the informatieobjecttypen
        $informatieobjecttypenObjectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($informatieObjectTypeEntity, ['omschrijving' => $zdsObject->getValue('dctOmschrijving')]);
        if (count($informatieobjecttypenObjectEntity) == 0 || !$informatieobjecttypenObjectEntity[0] instanceof ObjectEntity) {
            // @todo fix error
            throw new ErrorException('The informatieobjecttypen with omschrijving: '.$zdsObject->getValue('dctOmschrijving').' can\'t be found');
        }

        // Lets start by setting up the document
        $document = new ObjectEntity($enkelvoudiginformatieobjectEntity);
        $document->setValue('identificatie', $zdsObject->getValue('identificatie'));
        $document->setValue('creatiedatum', $zdsObject->getValue('creatiedatum'));
        $document->setValue('titel', $zdsObject->getValue('titel'));
        $document->setValue('auteur', $zdsObject->getValue('auteur'));
        $document->setValue('status', $zdsObject->getValue('status'));
        $document->setValue('formaat', $zdsObject->getValue('formaat'));
        $document->setValue('taal', $zdsObject->getValue('taal'));
        $document->setValue('inhoud', $zdsObject->getValue('inhoud'));
        $document->setValue('beschrijving', $zdsObject->getValue('beschrijving'));
        $document->setValue('informatieobjecttype', $informatieobjecttypenObjectEntity[0]->getValue('url'));
        $document->setValue('vertrouwelijkheidaanduiding', $informatieobjecttypenObjectEntity[0]->getValue('vertrouwelijkheidaanduiding'));

//        $document->setValue('indicatieGebruiksrecht', $zdsObject->getValue(''));
//        $document->setValue('bestandsnaam', $zdsObject->getValue(''));
//        $document->setValue('ontvangstdatum', $zdsObject->getValue(''));
//        $document->setValue('verzenddatum', $zdsObject->getValue('')); // stuurgegevens.tijdstipBericht

        $this->createZgwZaakInformatieObject($zdsObject, $zdsZaakObjectEntity, $document);

        $zdsZaakObjectEntity->setValue('zgwDocument', $document);
        $zdsDocument->setValue('zgwDocument', $document);
        $zdsDocument->setValue('zgwZaak', $zdsZaakObjectEntity->getValue('zgwZaak'));

        $this->entityManager->persist($document);
        $this->entityManager->persist($zdsDocument);
        $this->entityManager->persist($zdsZaakObjectEntity);
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
        $zdsIsVan = new ObjectEntity($zdsObject->getEntity()->getAttributeByName('isVan')->getObject());
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
        $zdsVerblijfsadres = new ObjectEntity($zdsNatuurlijkPersoon->getEntity()->getAttributeByName('verblijfadres')->getObject());
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
        $zdsNatuurlijkPersoon = new ObjectEntity($zdsHeeftAlsInitiator->getEntity()->getAttributeByName('natuurlijkPersoon')->getObject());
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
        $zdsHeeftAlsInitiator = new ObjectEntity($zdsObject->getEntity()->getAttributeByName('heeftAlsInitiator')->getObject());
        $zdsVestiging = new ObjectEntity($zdsHeeftAlsInitiator->getEntity()->getAttributeByName('vestiging')->getObject());

        $zdsHeeftAlsInitiator->setValue('natuurlijkPersoon', $this->getNatuurlijkPersoon($zdsHeeftAlsInitiator, $rol));

        return $zdsHeeftAlsInitiator;
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
        $zdsObject = new ObjectEntity($zds->getEntity()->getAttributeByName('object')->getObject());
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

        $rollen = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($rolEntity, ['omschrijvingGeneriek' => 'initiator']);
        if ($result->getValue('object') && $result->getValue('object')->getValue('identificatie')) {
            $zaak = $this->entityManager->getRepository(ObjectEntity::class)->findByEntity($zakenEntity, ['identificatie' => $result->getValue('object')->getValue('identificatie')])[0];
            foreach ($rollen as $rol) {
                if ($rol->getValue('zaak') == $zaak) {
                    break;
                }
            }
            $result->setValue('object', $this->getZaakObject($result, $zaak, $rol));
            $data['response'] = $result->toArray();
        } elseif ($result->getValue('object') && $result->getValue('object')->getValue('heeftAlsInitiator')->getValue('natuurlijkPersoon')->getValue('inpBsn')) {
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
        if ($zaak->getValue('identificatie') !== null && $configuration['entityType'] == 'ZAK') {
            return $data;
        }
        $this->configuration = $configuration;
        if ($configuration['entityType'] == 'ZAK') {
            $messageType = 'genereerZaakIdentificatie';
        } else {
            $messageType = 'genereerDocumentIdentificatie';
        }
        $body = $this->createDi02Message($messageType, Uuid::uuid4());
        $source = $this->entityManager->getRepository(Gateway::class)->find($this->configuration['source']);
        $du02 = $this->commonGroundService->callService($source->toArray(), $source->getLocation().($this->configuration['apiSource']['location'] ?? ''), $body, [], [], false, 'POST');
        if ($configuration['entityType'] == 'ZAK') {
            $zaak->setValue('identificatie', $this->getIdentifierFromDu02($du02->getBody()->getContents()));
        }

        return $data;
    }
}
