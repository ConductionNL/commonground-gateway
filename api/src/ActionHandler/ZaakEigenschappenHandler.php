<?php

namespace App\ActionHandler;

use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;
use App\Service\ValidatorService;
use Doctrine\ORM\EntityManagerInterface;

class ZaakEigenschappenHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private ValidatorService $validatorService;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * This function returns the identifierPath field from the configuration array.
     *
     * @param array $data
     * @param $configuration
     *
     * @return array
     */
    public function getIdentifier(array $data, $configuration): string
    {
        $dotData = new \Adbar\Dot($data);

        return $dotData->get($configuration['identifierPath']);
    }

    /**
     * This function returns the eigenschappen field from the configuration array.
     *
     * @param array $data
     * @param $configuration
     *
     * @return array
     */
    public function getExtraElements(array $data, $configuration): array
    {
        $dotData = new \Adbar\Dot($data);

        return $dotData->get($configuration['eigenschappen']);
    }

    /**
     * This function adds the data to an object entity.
     *
     * @param array             $data
     * @param ObjectEntity|null $objectEntity
     *
     * @throws \App\Exception\GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return ObjectEntity
     */
    private function populateObject(array $data, ObjectEntity $objectEntity): ObjectEntity
    {
        $owner = $this->objectEntityService->checkAndUnsetOwner($data);
        if ($this->validatorService->validateData($data, $objectEntity->getEntity(), 'POST')) {
            exit;
        }
        $data = $this->objectEntityService->createOrUpdateCase($data, $objectEntity, $owner, 'POST', 'application/ld+json');

        return $objectEntity;
    }

    /**
     * This function creates a zaak eigenschap.
     *
     * @param array             $eigenschap
     * @param ObjectEntity|null $objectEntity
     *
     * @throws \App\Exception\GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return ObjectEntity
     */
    public function createZaakEigenschap(array $eigenschap, ObjectEntity $objectEntity): ObjectEntity
    {
        $object = new ObjectEntity();
        $object->setEntity($objectEntity->getEntity());

        return $this->populateObject($eigenschap, $object);
    }

    /**
     * This function returns the url and value of the eigenschap.
     *
     * @param ObjectEntity|null $objectEntity
     * @param array             $extraElements
     * @param string            $eigenschap
     * @param string            $zaakUrl
     *
     * @return array|null
     */
    public function getEigenschapValues(ObjectEntity $objectEntity, array $extraElements, string $eigenschap, string $zaakUrl): ?array
    {
        foreach ($extraElements['ns1:extraElement'] as $element) {
            if ($eigenschap == $element['@naam']) {
                return [
                    'zaak'       => $zaakUrl,
                    'eigenschap' => $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('url'))->getStringValue(),
                    'waarde'     => $element['#'],
                ];
            }
        }

        return null;
    }

    /**
     * This function returns the name of the eigenschap.
     *
     * @param ObjectEntity|null $objectEntity
     * @param array             $extraElements
     * @param string            $zaakUrl
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
     * This function runs the zaakeigenschappen plugin.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws \App\Exception\GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return array|null
     */
    public function __run(array $data, array $configuration): array
    {
        $identifier = $this->getIdentifier($data['request'], $configuration);
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Eigenschap']);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['zaaktype' => $identifier]);

        if (count($objectEntities) > 0) {
            foreach ($objectEntities as $objectEntity) {
                $extraElements = $this->getExtraElements($data['request'], $configuration);
                $eigenschap = $this->getEigenschap($objectEntity, $extraElements, $data['response']['url']);
                $eigenschap !== null && $this->createZaakEigenschap($eigenschap, $objectEntity);
            }
        }

        return $data;
    }
}
