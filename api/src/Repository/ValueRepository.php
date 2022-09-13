<?php

namespace App\Repository;

use App\Entity\Value;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Value|null find($id, $lockMode = null, $lockVersion = null)
 * @method Value|null findOneBy(array $criteria, array $orderBy = null)
 * @method Value[]    findAll()
 * @method Value[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Value::class);
    }

    /**
     * An sql function used in the AddCommaToArrayValuesCommand to update all (multiple=true) values with a stringValue that doesn't start with a comma.
     *
     * @return array Returns an array of value objects
     */
    public function findMultipleValues(): array
    {
        $query = $this->createQueryBuilder('v')
            ->leftJoin('v.attribute', 'a')
            ->where('a.multiple = true AND v.stringValue NOT LIKE :startWithComma')
            ->setParameter('startWithComma', ',%')
            ->distinct();

        return $query
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * @return Value[] Returns an array of Value objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('v.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Value
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
