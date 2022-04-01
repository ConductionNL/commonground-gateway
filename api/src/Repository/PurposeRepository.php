<?php

namespace App\Repository;

use App\Entity\Purpose;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Purpose|null find($id, $lockMode = null, $lockVersion = null)
 * @method Purpose|null findOneBy(array $criteria, array $orderBy = null)
 * @method Purpose[]    findAll()
 * @method Purpose[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurposeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Purpose::class);
    }

    // /**
    //  * @return Purpose[] Returns an array of Purpose objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Purpose
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
