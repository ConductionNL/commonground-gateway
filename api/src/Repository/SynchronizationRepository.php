<?php

namespace App\Repository;

use App\Entity\Synchronization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Synchronization|null find($id, $lockMode = null, $lockVersion = null)
 * @method Synchronization|null findOneBy(array $criteria, array $orderBy = null)
 * @method Synchronization[]    findAll()
 * @method Synchronization[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SynchronizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Synchronization::class);
    }

    /**
     * @return Synchronization[] Returns an array of Synchronization objects where lastSync is NULL
     */
    public function findByLastSyncIsNull()
    {
        return $this->createQueryBuilder('s')
            ->where('s.lastSynced is NULL')
            ->getQuery()
            ->getResult();
    }


    /*
    public function findOneBySomeField($value): ?Synchronization
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
