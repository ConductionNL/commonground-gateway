<?php

namespace App\ActionHandler;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class ZaakEigenschappenHandler implements ActionHandlerInterface
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

    public function overridePath(string $value, string $path, array $data): array
    {
        $dotData = new \Adbar\Dot($data);
        $dotData->set($path, $value);

        return $dotData->jsonSerialize();
    }

    public function __run(array $data, array $configuration): array
    {
        $identifier = $this->getIdentifier($data['request'], $configuration);
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Eigenschap']);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['identificatie' => $identifier]);

        if (count($objectEntities) > 0 ) {

//            foreach ($objectEntities as $objectEntity) {
//                if ($objectEntity instanceof ObjectEntity) {
//                    var_dump($objectEntity->toArray());
//                }
//            }
        }
        var_dump($identifier);
        var_dump($entity->getName());

        return $data;
    }
}
