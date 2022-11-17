<?php

namespace App\Repository;

use App\Entity\Cronjob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Cronjob|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cronjob|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cronjob[]    findAll()
 * @method Cronjob[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CronjobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cronjob::class);
    }

    // /**
    //  * @return Cronjob[] Returns an array of Cronjob objects
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
    public function findOneBySomeField($value): ?Cronjob
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function getRunnableCronjobs()
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.nextRun <= :val OR c.nextRun is NULL')
            ->andWhere('c.isActive = true')
            ->setParameter('val', new \DateTime('now'))
            ->getQuery()
            ->getResult();
    }
}
