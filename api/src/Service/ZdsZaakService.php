<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZdsZaakService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $synchronizationService
    ) {
        $this->entityManager = $entityManager;
        $this->synchronizationService = $synchronizationService;
    }

    /**
     * This function returns the identifierPath field from the configuration array.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return string The identifierPath in the action configuration
     */
    public function getIdentifier(array $data, array $configuration): string
    {
        $dotData = new \Adbar\Dot($data);

        return $dotData->get($configuration['identifierPath']);
    }

    /**
     * @param string $value
     * @param string $path
     * @param array  $data  The data from the call
     *
     * @return array
     */
    public function overridePath(string $value, string $path, array $data): array
    {
        $dotData = new \Adbar\Dot($data);
        $dotData->set($path, $value);

        return $dotData->jsonSerialize();
    }

    /**
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function zaakTypeHandler(array $data, array $configuration): array
    {
        $identifier = $this->getIdentifier($data['request'], $configuration);
        $entity = $this->entityManager->getRepository('App:Entity')->find($configuration['entityId']);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['identificatie' => $identifier]);

        if (count($objectEntities) > 0 && $objectEntities[0] instanceof ObjectEntity) {
            $objectEntity = $objectEntities[0];

            $url = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('url'))->getStringValue();
            $data['request'] = $this->overridePath($url, $configuration['identifierPath'], $data['request']);
        }

        return $data;
    }

    /**
     * This function returns the eigenschappen field from the configuration array.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array The eigenschappen in the action configuration
     */
    public function getExtraElements(array $data, array $configuration): array
    {
        $dotData = new \Adbar\Dot($data);

        return $dotData->get($configuration['eigenschappen']);
    }

    /**
     * @param ObjectEntity $objectEntity
     * @param array $data
     * @return Synchronization
     */
    public function createSynchronization(ObjectEntity $objectEntity, array $data): Synchronization
    {
        $synchronization = new Synchronization();
        $synchronization->setObject($objectEntity);
        $synchronization->setEntity($objectEntity->getEntity());
        $synchronization->setGateway(); //todo

        //TODO: is this right this way? Feels very hardcoded
        //TODO: use twig parser on this instead
        $synchronization->setEndpoint("/zaken/{$data['response']['uuid']}/zaakeigenschappen");

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
     * @param array $configuration The configuration of the zaakeigenschap action
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
    public function getZaak(array $data, array $configuration, array $extraElements, array $eigenschappen): void
    {
        $zaakEntity = $this->entityManager->getRepository('App:Entity')->find($configuration['zaakEntityId']);
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
        $identifier = $this->getIdentifier($data['request'], $configuration);
        $eigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($configuration['eigenschapEntityId']);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($eigenschapEntity, ['zaaktype' => $identifier]);
        $extraElements = $this->getExtraElements($data['request'], $configuration);

        $eigenschappen = [];
        if (count($objectEntities) > 0) {
            foreach ($objectEntities as $objectEntity) {
                $eigenschap = $this->getEigenschap($objectEntity, $extraElements, $data['response']['url']);
                $eigenschap !== null && $eigenschappen[] = $this->createObject($eigenschap, $objectEntity, $data)->getSelf();
            }
        }
        $this->getZaak($data, $configuration, $extraElements, $eigenschappen);

        return $data;
    }
}
