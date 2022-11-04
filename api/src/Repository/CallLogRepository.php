<?php

namespace App\Repository;

use App\Entity\CallLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CallLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method CallLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method CallLog[]    findAll()
 * @method CallLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CallLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CallLog::class);
    }

    // /**
    //  * @return CallLog[] Returns an array of CallLog objects
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
    public function findOneBySomeField($value): ?CallLog
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
