<?php

namespace App\Repository;

use App\Entity\GatewayResponseLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method GatewayResponseLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method GatewayResponseLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method GatewayResponseLog[]    findAll()
 * @method GatewayResponseLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GatewayResponseLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GatewayResponseLog::class);
    }

    // /**
    //  * @return GatewayResponseLog[] Returns an array of GatewayResponseLog objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('g.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?GatewayResponseLog
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
