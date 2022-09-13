<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use ErrorException;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZdsZaakService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;
    private ObjectEntityService $objectEntityService;
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
        ObjectEntityService $objectEntityService
    ) {
        $this->entityManager = $entityManager;
        $this->synchronizationService = $synchronizationService;
        $this->objectEntityService = $objectEntityService;
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
     * @todo Zgw zaaktype en identificatie toevoegen aan het zds bericht (DataService hebben we hiervoor nodig)
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration array from the action
     *
     * @throws ErrorException
     *
     * @return array The modified data of the call with the case type and identification
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
     * This function converts a zds message to zgw.
     *
     * @todo Eigenschappen ophalen uit de zaaktype (zaaktypen uit contezza synchroniseren met de eigenschappen)
     * @todo ExtraElementen ophalen uit het zds bericht (extraElementen moeten met naam en value gemapt worden in het zds object)
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration array from the action
     *
     * @throws ErrorException
     *
     * @return array The data from the call
     */
    public function zdsToZGWHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $zds = $this->entityManager->getRepository('App:ObjectEntity')->find($this->data['response']['id']);
        $zaakEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakEntityId']);
        $eigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['eigenschapEntityId']);

        // @todo remove the check for identification and zaaktype if the dataService is implemented
        // @todo get in the zds object the values of the properties casetype and identification and store this in the case
        $zaakTypeIdentificatie = $this->getIdentifier($this->data['request']);
        if (!$zaakTypeIdentificatie) {
            // @todo fix error
            throw new ErrorException('The identificatie is not found');
        }

        // Let get the zaaktype
        $zaakTypeObjectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findByAnyId($zaakTypeIdentificatie);
        if (!$zaakTypeObjectEntity && !$zaakTypeObjectEntity instanceof ObjectEntity) {
            // @todo fix error
            throw new ErrorException('The zaakType with identificatie: '.$zaakTypeIdentificatie.' can\'t be found');
        }

//        var_dump($eigenschapEntity->getId()->toString());
//        var_dump($zaakTypeObjectEntity->getValue('url'));
        // Let get the eigenschappen
        $eigenschapObjectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($eigenschapEntity,  ['zaaktype' => $zaakTypeObjectEntity->getValue('url')]);

//        var_dump(count($eigenschapObjectEntities));

        $zdsObjectId = $zds->getValue('object');
        $zdsObject = $this->entityManager->getRepository('App:ObjectEntity')->find($zdsObjectId);


        $zdsExtraElementsEntityIds = $zdsObject->getValue('extraElementen');
        $unusedExtraElements = [
            'toelichting' => null
        ];
        foreach ($zdsExtraElementsEntityIds as $zdsExtraElementsEntityId) {
            $zdsExtraElement = $this->entityManager->getRepository('App:ObjectEntity')->find($zdsExtraElementsEntityId);
            $unusedExtraElements['toelichting'] .= " {$zdsExtraElement->getValue('@naam')}: {$zdsExtraElement->getValue('#')} ";
        }

        // so far so good (need error handling doh)
        $zaak = new ObjectEntity();

        $zaak->hydrate($zdsObject->toArray());
        $zaak->setEntity($zaakEntity);
//        $zaak->setValue('registratiedatum', $zdsObject->getValue('registratiedatum')->getStringValue());
        $zaak->setValue('toelichting', "{$zdsObject->getValue('toelichting')} {$unusedExtraElements['toelichting']}");
//        $zaak->setValue('omschrijving', $zdsObject->getValue('omschrijving')->getStringValue());
//        $zaak->setValue('einddatumGepland', $zdsObject->getValue('einddatumGepland')->getStringValue());
        $zaak->setValue('uiterlijkeEinddatumAfdoening', $zdsObject->getValue('uiterlijkeEinddatum'));
        $zaak->setValue('betalingsindicatie', $zdsObject->getValue('betalingsIndicatie'));
//        $zaak->setValue('laatsteBetaaldatum', $zdsObject->getValue('laatsteBetaaldatum')->getStringValue());
        $zaak->setValue('zaaktype', $zaakTypeObjectEntity);

//        $zaakEigenschappen = [];
//        foreach($zds->getValue('extaElements') as $key => $value){
//            if(array_key_exists($key, $zgwZaakTypeEigenschappen)){
//                $zaakEigenschappen[] = [
//
//                ];
//                continue;
//            }
//
//            $toelichtingen = $zaak->getValue('toelichtingen');
//            $toelichtingen = $toelichtingen->getStringValue().$value;
//            $zaak->setValue('toelichtingen', $toelichtingen);
//        }
//
//        $zaak->setValue('eigenschappen', $zaakEigenschappen);

        $this->entityManager->persist($zaak);
        $this->entityManager->flush();

        return $this->data;
    }

    /**
     * This function set an identifier on the dataset.
     *
     * @param string $identifier The identifier to set
     * @param array  $data       The data from the call
     *
     * @return array
     */
    public function overridePath(string $identifier, array $data): array
    {
        // @todo in de sync service noemen we dit niet identifierPath maar locationIdField
        $path = $this->configuration['identifierPath'];
        $dotData = new \Adbar\Dot($data);
        $dotData->set($path, $identifier);

        // @todo er wordt aangegeven dat de result een array is (that makes sense) maar we geven een JSON object terug?
        return $dotData->jsonSerialize();
    }

    /**
     * Changes the request to hold the proper zaaktype url insted of given identifier.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function zaakTypeHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $identifier = $this->getIdentifier($data['request']);

        $zaakTypeEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakTypeEntityId']);
        $zaakTypeObjectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($zaakTypeEntity, ['identificatie' => $identifier]);

        if (count($zaakTypeObjectEntities) > 0 && $zaakTypeObjectEntities[0] instanceof ObjectEntity) {
            // @todo bij meer dan één zaak hebben we gewoon een probleem en willen we een error
            $zaakTypeObjectEntity = $zaakTypeObjectEntities[0];
            // Ok dus dat is de url van de aangemaakte zaak en dan
            $url = $zaakTypeObjectEntity->getValueByAttribute($zaakTypeObjectEntity->getEntity()->getAttributeByName('url'))->getStringValue();
            // deze functie verhaspeld het overwriten van het paht en muteren van object (naar json)
            $data['request'] = $this->overridePath($url, $data['request']);
        }

        return $data;
    }

    // @todo waarom is dit een functie?

    /**
     * This function returns the eigenschappen field from the configuration array.
     *
     * @param array $data The data from the call
     *
     * @return array The eigenschappen in the action configuration
     */
    public function getExtraElements(array $data): array
    {
        $dotData = new \Adbar\Dot($data);

        return $dotData->get($this->configuration['eigenschappen']);
    }

    /**
     * @todo wat doet dit?
     *
     * @param ObjectEntity $objectEntity The object entity that relates to the entity Eigenschap
     * @param array        $data         The data array
     *
     * @return Synchronization
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
     * This function creates a zaak eigenschap.
     *
     * @param array             $eigenschap   The eigenschap array with zaak, eigenschap and waarde as keys
     * @param ObjectEntity|null $objectEntity The object entity that relates to the entity Eigenschap
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return ObjectEntity Creates a zaakeigenschap
     */
    public function createObject(array $eigenschap, ObjectEntity $objectEntity, array $data): ObjectEntity
    {
        $object = new ObjectEntity();
        $object->setEntity($objectEntity->getEntity());

        $this->createSynchronization($objectEntity, $data);

        // @todo populate is geen gangabre term hydrate wel
        return $this->synchronizationService->populateObject($eigenschap, $object, 'POST');
    }

    /**
     * This function returns the zaak, eigenschap and waarde when matched with the element in de action configuration file.
     *
     * @param ObjectEntity|null $objectEntity  The object entity that relates to the entity Eigenschap
     * @param array             $extraElements The extra elements that are taken from the action configuration eigenschappen path
     * @param string            $eigenschap    The naam of the eigenschap that has to be matched
     * @param string            $zaakUrl       The zaakurl the eigenschap is related to
     *
     * @return array|null
     */
    public function getEigenschapValues(ObjectEntity $objectEntity, array $extraElements, string $eigenschap, string $zaakUrl): ?array
    {
        foreach ($extraElements['ns1:extraElement'] as $element) {
            if ($eigenschap == $element['@naam']) {
                $this->usedValues[] = $element['@naam'];

                return [
                    'zaak'       => $zaakUrl,
                    'eigenschap' => $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('url'))->getStringValue(),
                    'waarde'     => $element['#'],
                    'zaaktype'   => $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('zaaktype'))->getStringValue(),
                    'definitie'  => $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('definitie'))->getStringValue(),
                    'naam'       => $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('naam'))->getStringValue(),
                ];
            }
        }

        return null;
    }

    /**
     * This function returns updates the zaak with the unused elements under 'toelichting'.
     *
     * @param array        $extraElements The extra elements that are taken from the action configuration eigenschappen path
     * @param array        $data          The data from the call
     * @param ObjectEntity $zaakObject    The zaak object entity that relates to the entity Zaak
     * @param array        $eigenschappen The eigenschappen @ids
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return ObjectEntity
     */
    public function updateZaak(array $extraElements, array $data, ObjectEntity $zaakObject, array $eigenschappen): ObjectEntity
    {
        $unusedElements = [
            'toelichting'                  => '',
            'zaaktype'                     => $data['zaaktype'],
            'startdatum'                   => $data['startdatum'],
            'bronorganisatie'              => $data['bronorganisatie'],
            'verantwoordelijkeOrganisatie' => $data['verantwoordelijkeOrganisatie'],
            'eigenschappen'                => $eigenschappen,
        ];

        foreach ($extraElements['ns1:extraElement'] as $element) {
            if (in_array($element['@naam'], $this->usedValues)) {
                continue;
            }
            $unusedElements['toelichting'] .= "{$element['@naam']}: {$element['#']}";
        }

        return $this->synchronizationService->populateObject($unusedElements, $zaakObject, 'PUT');
    }

    /**
     * This function gets the name of the eigenschap and returns the getEigenschapValues functie.
     *
     * @param ObjectEntity|null $objectEntity  The object entity that relates to the entity Eigenschap
     * @param array             $extraElements The extra elements that are taken from the action configuration eigenschappen path
     * @param string            $zaakUrl       The zaakurl the eigenschap is related to
     *
     * @return array|null
     */
    public function getEigenschap(?ObjectEntity $objectEntity, array $extraElements, string $zaakUrl): ?array
    {
        if ($objectEntity instanceof ObjectEntity) {
            $eigenschap = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('naam'));

            return $this->getEigenschapValues($objectEntity, $extraElements, $eigenschap->getStringValue(), $zaakUrl);
        }

        return null;
    }

    /**
     * This function gets the name of the eigenschap and returns the getEigenschapValues functie.
     *
     * @param array $data          The data from the call
     * @param array $extraElements The extra elements that are taken from the action configuration eigenschappen path
     * @param array $eigenschappen The eigenschappen @ids
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return void
     */
    public function getZaak(array $data, array $extraElements, array $eigenschappen): void
    {
        $zaakEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['zaakEntityId']);
        $zaakObject = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($zaakEntity, ['url' => $data['response']['url']]);
        $this->updateZaak($extraElements, $data['response'], $zaakObject[0], $eigenschappen);
    }

    /**
     * This function gets the name of the eigenschap and returns the getEigenschapValues functie.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the zaakeigenschap action
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return array|null
     */
    public function zaakEigenschappenHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

        $eigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['eigenschapEntityId']);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($eigenschapEntity, ['zaaktype' => $this->getIdentifier($data['request'])]);
        $extraElements = $this->getExtraElements($data['request']);

        $eigenschappen = [];
        if (count($objectEntities) > 0) {
            foreach ($objectEntities as $objectEntity) {
                $eigenschap = $this->getEigenschap($objectEntity, $extraElements, $data['response']['url']);
                $eigenschap !== null && $eigenschappen[] = $this->createObject($eigenschap, $objectEntity, $data)->getSelf();
            }
        }
        $this->getZaak($data, $extraElements, $eigenschappen);

        return $data;
    }
}
