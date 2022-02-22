<?php


namespace App\Service;


use App\Entity\Endpoint;
use App\Entity\Entity;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class StorageORMService
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $doctrine)
    {
        $manager = $doctrine->getManager('data_warehouse');
        if($manager instanceof EntityManagerInterface)
            $this->entityManager = $manager;
        else
            throw new \InvalidArgumentException('An object manager of type '. get_class($manager) . 'is not supported here');
    }


    public function createResource(Entity $entity, array $data): array
    {
        var_dump($this->entityManager->getMetadataFactory()->getMetadataFor($entity->getName()));

//        $this->entityManager->getRepository('Database/'.$entity->getName())->getClassName();

        return [];
    }
}
