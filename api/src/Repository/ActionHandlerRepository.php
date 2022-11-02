<?php

namespace App\Repository;

use App\Entity\ActionHandler;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ActionHandler|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActionHandler|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActionHandler[]    findAll()
 * @method ActionHandler[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionHandlerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionHandler::class);
    }

    // /**
    //  * @return ActionHandler[] Returns an array of ActionHandler objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ActionHandler
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
