<?php

namespace App\Repository;

use App\Entity\AuditTrail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AuditTrail|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuditTrail|null findOneBy(array $criteria, array $orderBy = null)
 * @method AuditTrail[]    findAll()
 * @method AuditTrail[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AuditTrailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditTrail::class);
    }

    /**
     * Returns all get item audit trails on the given ObjectEntity by the given user, ordered by creation date of the audit trail. (and only audit trails where response was a 200).
     *
     * @param string $objectId The id of an ObjectEntity.
     * @param string $userId The id of a User.
     *
     * @return array An array of audit trails found, or empty array.
     */
    public function findDateRead(string $objectId, string $userId): array
    {
        $query = $this->createQueryBuilder('a')
            ->where('a.resource = :objectId')
            ->andWhere('a.userId = :userId')
            ->andWhere('a.result = :responseStatusCode')
            ->andWhere('LOWER(a.action) = :method')
            ->setParameters([
                'objectId'           => $objectId,
                'userId'             => $userId,
                'responseStatusCode' => 200,
                'method'             => 'retrieve',
            ])
            ->orderBy('a.creationDate', 'DESC')
            ->distinct();

        return $query
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * @return AuditTrail[] Returns an array of AuditTrail objects
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
    public function findOneBySomeField($value): ?AuditTrail
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
