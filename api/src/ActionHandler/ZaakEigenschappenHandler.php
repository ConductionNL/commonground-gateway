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

    public function createZaakEigenschap(?ObjectEntity $objectEntity): \App\Entity\Value
    {
        if ($objectEntity instanceof ObjectEntity) {

            $eigenschap = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('naam'));

            var_dump($eigenschap);

            // eigenschap matchen met een van de velden van extra elementen
            // als we een match hebben pak url eigenschap
            // maak zaakeigenschap aan met url van eigenschap en de waarde van extra element
//            var_dump($objectEntity->toArray());
        }

        return $eigenschap;
    }

    public function __run(array $data, array $configuration): array
    {
        $identifier = $this->getIdentifier($data['request'], $configuration);
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Eigenschap']);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['zaaktype' => $identifier]);

        var_dump($entity->getId()->toString());
        var_dump(count($objectEntities));
        if (count($objectEntities) > 0) {
            // get extra element velden naam en kijk of die overeenkomt met de naam van de eigenschap
            foreach ($objectEntities as $objectEntity) {
                $objectEntity = $this->createZaakEigenschap($objectEntity);
            }
        }
        var_dump($identifier);

        return $data;
    }
}
