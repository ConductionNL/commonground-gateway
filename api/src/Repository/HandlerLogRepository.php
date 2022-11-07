<?php

namespace App\Repository;

use App\Entity\HandlerLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method HandlerLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method HandlerLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method HandlerLog[]    findAll()
 * @method HandlerLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HandlerLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HandlerLog::class);
    }

    // /**
    //  * @return HandlerLog[] Returns an array of HandlerLog objects
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
    public function findOneBySomeField($value): ?HandlerLog
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
