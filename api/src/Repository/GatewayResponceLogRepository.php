<?php

namespace App\Repository;

use App\Entity\GatewayResponceLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method GatewayResponceLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method GatewayResponceLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method GatewayResponceLog[]    findAll()
 * @method GatewayResponceLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GatewayResponceLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GatewayResponceLog::class);
    }

    // /**
    //  * @return GatewayResponceLog[] Returns an array of GatewayResponceLog objects
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
    public function findOneBySomeField($value): ?GatewayResponceLog
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
