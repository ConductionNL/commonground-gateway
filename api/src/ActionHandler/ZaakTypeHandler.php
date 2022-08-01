<?php

namespace App\ActionHandler;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class ZaakTypeHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getIdentifier(array $data, $configuration): string
    {
        $dotData = new \Adbar\Dot($data);

        return $dotData->get($configuration['identifierPath']);
    }

    public function __run(array $data, array $configuration): array
    {
        $identifier = $this->getIdentifier($data['request'], $configuration);
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $configuration['entityId']]);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['identificatie' => $identifier]);

        if (count($objectEntities) > 0 && $objectEntities[0] instanceof ObjectEntity) {
            $objectEntity = $objectEntities[0];

            $url = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('url'))->getStringValue();
            $data['result'] = $url;
        }

        return $data;
    }
}
