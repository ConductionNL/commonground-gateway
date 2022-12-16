<?php

namespace App\Repository;

use App\Entity\Application;
use App\Entity\Endpoint;
use App\Entity\Entity;
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
//            ->andWhere('LOWER(e.method) = :method')
            ->andWhere('REGEXP_REPLACE(:path, e.pathRegex, :replace) LIKE :compare')
            ->setParameters([/*'method' => strtolower($method), */'path' => $path, 'replace' => 'ItsAMatch', 'compare' => 'ItsAMatch']);

        return $query
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds the get item endpoint for the given entity, if it (/only one) exists.
     *
     * @param Entity $entity
     *
     * @return array|null
     */
    public function findGetItemByEntity(Entity $entity): ?array
    {
        $query = $this->createQueryBuilder('e')
            ->leftJoin('e.handlers', 'h')
            ->where('h.entity = :entity')
            ->andWhere('LOWER(e.method) = :method AND e.operationType = :operationType')
            ->setParameters(['entity' => $entity, 'method' => 'get', 'operationType' => 'item'])
            ->distinct();

        return $query
            ->getQuery()
            ->getResult();
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

    /**
     * @param Application $application The Application.
     *
     * @return array Returns a Endpoint object
     */
    public function findByApplication(Application $application): array
    {
        $query = $this->createQueryBuilder('e')
            ->leftJoin('e.applications', 'a')
            ->where('a.id = :applicationId')
            ->setParameter('applicationId', $application->getId()->toString())
            ->distinct();

        return $query
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
