<?php

namespace App\Repository;

use App\Entity\Log;
use App\Entity\ObjectEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Log|null find($id, $lockMode = null, $lockVersion = null)
 * @method Log|null findOneBy(array $criteria, array $orderBy = null)
 * @method Log[]    findAll()
 * @method Log[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Log::class);
    }

    /**
     * Returns all get item logs on the given ObjectEntity by the given user, ordered by creation date of the log.
     *
     * @param ObjectEntity $objectEntity
     * @param string $userId
     *
     * @return array
     */
    public function findDateRead(ObjectEntity $objectEntity, string $userId): array
    {
        $query = $this->createQueryBuilder('l')
            ->leftJoin('l.endpoint', 'e')
            ->where('l.entity = :entity AND l.responseStatusCode = :responseStatusCode AND l.user = :userId')
            ->andWhere('LOWER(e.method) = :method AND e.operationType = :operationType')
            ->setParameters(['entity' => $objectEntity->getEntity(), 'responseStatusCode' => 200, 'userId' => $userId, 'method' => 'get', 'operationType' => 'item'])
            ->orderBy('l.dateCreated', 'DESC')
            ->distinct();

        return $query
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * @return Log[] Returns an array of Log objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Log
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
