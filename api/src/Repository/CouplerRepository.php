<?php

namespace App\Repository;

use App\Entity\Coupler;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Coupler|null find($id, $lockMode = null, $lockVersion = null)
 * @method Coupler|null findOneBy(array $criteria, array $orderBy = null)
 * @method Coupler[]    findAll()
 * @method Coupler[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CouplerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Coupler::class);
    }

    // /**
    //  * @return Coupler[] Returns an array of Coupler objects
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
    public function findOneBySomeField($value): ?Coupler
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
