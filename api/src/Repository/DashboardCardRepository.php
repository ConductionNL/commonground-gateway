<?php

namespace App\Repository;

use App\Entity\DashboardCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DashboardCard|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardCard|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardCard[]    findAll()
 * @method DashboardCard[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DashboardCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DashboardCard::class);
    }

    // /**
    //  * @return DashboardCard[] Returns an array of DashboardCard objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DashboardCard
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
