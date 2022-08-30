<?php

namespace App\Repository;

use App\Entity\EmailTrigger;
use App\Entity\Endpoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * todo: move this to an email plugin (see EmailService.php).
 *
 * @method EmailTrigger|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmailTrigger|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmailTrigger[]    findAll()
 * @method EmailTrigger[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmailTriggerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTrigger::class);
    }

    /**
     * @param Endpoint $endpoint The Endpoint to search by.
     *
     * @return array Returns a Endpoint object
     */
    public function findByEndpoint(Endpoint $endpoint): array
    {
        $query = $this->createQueryBuilder('et')
            ->leftJoin('et.endpoints', 'e')
            ->where('e.id = :endpointId')
            ->setParameter('endpointId', $endpoint->getId()->toString())
            ->distinct();

        return $query
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * @return EmailTrigger[] Returns an array of EmailTrigger objects
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
    public function findOneBySomeField($value): ?EmailTrigger
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
