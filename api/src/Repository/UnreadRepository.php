<?php

namespace App\Repository;

use App\Entity\Unread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Unread|null find($id, $lockMode = null, $lockVersion = null)
 * @method Unread|null findOneBy(array $criteria, array $orderBy = null)
 * @method Unread[]    findAll()
 * @method Unread[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UnreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unread::class);
    }

    // /**
    //  * @return Unread[] Returns an array of Unread objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Unread
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
