<?php

namespace App\Repository;

use App\Entity\Read;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Read|null find($id, $lockMode = null, $lockVersion = null)
 * @method Read|null findOneBy(array $criteria, array $orderBy = null)
 * @method Read[]    findAll()
 * @method Read[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Read::class);
    }

    // /**
    //  * @return Read[] Returns an array of Read objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Read
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
