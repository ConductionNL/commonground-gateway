<?php

namespace App\Repository;

use App\Entity\Action;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Action|null find($id, $lockMode = null, $lockVersion = null)
 * @method Action|null findOneBy(array $criteria, array $orderBy = null)
 * @method Action[]    findAll()
 * @method Action[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Action::class);
    }

    /**
     * @param string $listen
     *
     * @return Action[] The resulting actions
     */
    public function findByListens(string $listen): array
    {
        // Todo: maybe add actions to MongoDB as well, so we can use better queries for this:
        // Todo: %$listen% will sometimes find actions it shouldn't find, example: action listening to test.test.123 will be found if action test.test is thrown.
        $query = $this->createQueryBuilder('a')
            ->andWhere('a.listens LIKE :listen')
            ->setParameter('listen', "%$listen%")
            ->orderBy('a.priority', 'ASC');

        return $query->getQuery()->getResult();
    }
}
