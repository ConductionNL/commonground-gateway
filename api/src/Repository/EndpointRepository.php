<?php

namespace App\Repository;

use App\Entity\Endpoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Endpoint|null find($id, $lockMode = null, $lockVersion = null)
 * @method Endpoint|null findOneBy(array $criteria, array $orderBy = null)
 * @method Endpoint[]    findAll()
 * @method Endpoint[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EndpointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Endpoint::class);
    }

    /**
     * @TODO
     *
     * @param string $method
     * @param string $path
     *
     * @throws NonUniqueResultException
     *
     * @return Endpoint|null
     */
    public function findByMethodRegex(string $method, string $path): ?Endpoint
    {
        $query = $this->createQueryBuilder('e')
            ->andWhere('e.method = :method')
            ->andWhere('REGEXP_REPLACE(:path, e.pathRegex, :replace) LIKE :compare')
            ->setParameters(['method' => $method, 'path' => $path, 'replace' => 'ItsAMatch', 'compare' => 'ItsAMatch']);

        return $query
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Endpoint Returns a Endpoint object
     */
    public function findOneByPartOfPath(string $path)
    {
        return $this->createQueryBuilder('e')
          ->andWhere('e.path = :path')
          ->setParameter('path', $path)
          ->setMaxResults(1)
          ->getQuery()
          ->getResult();
    }

    // /**
    //  * @return Endpoint[] Returns an array of Endpoint objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Endpoint
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
