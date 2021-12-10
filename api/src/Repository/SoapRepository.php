<?php

namespace App\Repository;

use App\Entity\Soap;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Soap|null find($id, $lockMode = null, $lockVersion = null)
 * @method Soap|null findOneBy(array $criteria, array $orderBy = null)
 * @method Soap[]    findAll()
 * @method Soap[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SoapRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Soap::class);
    }

    // /**
    //  * @return Soap[] Returns an array of Soap objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Soap
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
