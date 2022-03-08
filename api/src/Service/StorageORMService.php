<?php


namespace App\Service;


use App\Entity\Endpoint;
use App\Entity\Entity;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

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
//        var_dump($this->entityManager->getConnection()->getDatabasePlatform()->getName());
        var_dump($this->entityManager->getMetadataFactory()->getAllMetadata());
        var_dump('hi', $this->entityManager->getMetadataFactory()->getMetadataFor('Database\\'.$entity->getName())->getAssociationNames(), 'bye');

        try{
            var_dump($this->entityManager->getRepository('Database\\'.$entity->getName())->findBy(['id' => 'cd2acf70-69e5-466f-b45d-ff756e303da0']));
        } catch (Exception $exception) {
            echo $exception->getMessage();
            printf($exception->getTraceAsString());
        }

//        $this->entityManager->getRepository('Database/'.$entity->getName())->getClassName();

        return [];
    }
}
