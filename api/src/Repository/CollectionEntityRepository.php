<?php

namespace App\Repository;

use App\Entity\CollectionEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CollectionEntity|null find($id, $lockMode = null, $lockVersion = null)
 * @method CollectionEntity|null findOneBy(array $criteria, array $orderBy = null)
 * @method CollectionEntity[]    findAll()
 * @method CollectionEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollectionEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectionEntity::class);
    }

    // /**
    //  * @return CollectionEntity[] Returns an array of CollectionEntity objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CollectionEntity
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
