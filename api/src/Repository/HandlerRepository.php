<?php

namespace App\Repository;

use App\Entity\Handler;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Handler|null find($id, $lockMode = null, $lockVersion = null)
 * @method Handler|null findOneBy(array $criteria, array $orderBy = null)
 * @method Handler[]    findAll()
 * @method Handler[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HandlerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Handler::class);
    }

    // /**
    //  * @return Handler[] Returns an array of Handler objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('h.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Handler
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */


}
