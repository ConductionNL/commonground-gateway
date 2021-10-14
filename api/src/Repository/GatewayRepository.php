<?php

namespace App\Repository;

use App\Entity\Gateway;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Gateway|null find($id, $lockMode = null, $lockVersion = null)
 * @method Gateway|null findOneBy(array $criteria, array $orderBy = null)
 * @method Gateway[]    findAll()
 * @method Gateway[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GatewayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gateway::class);
    }
}
