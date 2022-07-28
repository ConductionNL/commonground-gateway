<?php

namespace App\ActionHandler;

use App\Entity\Action;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class ZaakTypeHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getIdentifier(Request $request, $configuration): string
    {
        $xmlEncoder = new XmlEncoder();
        $data = $xmlEncoder->decode($request->getContent(), 'xml');
        $dotData = new \Adbar\Dot($data);

        // getConfiguration does nog work
        return $dotData->get($configuration['identifierPath']);
    }

    public function __run(array $data, array $configuration): array
    {
//        return $data;
        $identifier = $this->getIdentifier($data['request'], $configuration);
        var_dump($identifier);

        $entity = $this->entityManager->getRepository("App:Entity")->findOneBy(["id" => "cf294a75-6cf2-4c3d-80a5-2c9a4caaae77"]);
        $objectEntities = $this->entityManager->getRepository("App:ObjectEntity")->findByEntity($entity, ['identificatie' => $identifier]);
        var_dump(count($objectEntities));

        if(count($objectEntities) > 0 && $objectEntities[0] instanceof ObjectEntity){
            $objectEntity = $objectEntities[0];

            $url = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('url'))->getStringValue();
            $data['result'] = $url;
        }


        // find object entity with entity zaaktype
        // get omschrijving code
        // aan de hand van de code de casetype zoeken -> identificatie

        var_dump('running the ZaakTypeHandler!');

        return $data;
    }
}
