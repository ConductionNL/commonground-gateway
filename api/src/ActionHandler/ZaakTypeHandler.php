<?php

namespace App\ActionHandler;

use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

class ZaakTypeHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        if ($entityManager instanceof EntityManagerInterface) {
            $this->entityManager = $entityManager;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
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
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $configuration['entityId']]);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['identificatie' => $identifier]);

        var_dump(count($objectEntities));

        if (count($objectEntities) > 0 && $objectEntities[0] instanceof ObjectEntity) {
            $objectEntity = $objectEntities[0];

            $url = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('url'))->getStringValue();
            $data['request'] = $this->overridePath($url, $configuration['identifierPath'], $data['request']);
        }
        return $data;
    }
}
