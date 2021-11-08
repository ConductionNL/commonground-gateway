<?php

namespace App\Repository;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @method ObjectEntity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ObjectEntity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ObjectEntity[]    findAll()
 * @method ObjectEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ObjectEntityRepository extends ServiceEntityRepository
{
    private SessionInterface $session;

    public function __construct(ManagerRegistry $registry, SessionInterface $session)
    {
        $this->session = $session;

        parent::__construct($registry, ObjectEntity::class);
    }

    /**
     * @param Entity $entity
     * @param array  $filters
     * @param int    $offset
     * @param int    $limit
     *
     * @return ObjectEntity[] Returns an array of ObjectEntity objects
     */
    public function findByEntity(Entity $entity, array $filters = [], int $offset = 0, int $limit = 25): array
    {
        $query = $this->createQuery($entity, $filters);

        var_dump($query->getDQL());

        return $query
            // filters toevoegen
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Entity $entity
     * @param array  $filters
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     *
     * @return int Returns an integer, for the total ObjectEntities found with this Entity and with the given filters.
     */
    public function countByEntity(Entity $entity, array $filters = []): int
    {
        $query = $this->createQuery($entity, $filters);
        $query->select('count(o.id)');

        return $query->getQuery()->getSingleScalarResult();
    }

    private function createQuery(Entity $entity, array $filters): QueryBuilder
    {
        $query = $this->createQueryBuilder('o')
            ->andWhere('o.entity = :entity')
            ->setParameters(['entity' => $entity]);

        // TODO: use this and add filter for application?
//        $query = $this->createQueryBuilder('o')
//            ->andWhere('o.entity = :entity AND o.organization IN (:organizations)')
//            ->setParameters(['entity' => $entity, 'organizations' => $this->session->get('organizations')]);

        if (!empty($filters)) {
            $filterCheck = $this->getFilterParameters($entity);
            $query->leftJoin('o.objectValues', 'value');
            $level = 0;

            foreach ($filters as $key=>$value) {
                // Symfony has the tendency to replace . with _ on query parameters
                $key = str_replace(['_'], ['.'], $key);
                // Lets see if this is an allowed filter
                if (!in_array($key, $filterCheck)) {
                    unset($filters[$key]);
                    continue;
                }

                // let not dive to deep
                if (!strpos($key, '.')) {
                    $query->andWhere('value.stringValue = :'.$key)
                        ->setParameter($key, $value);
                }
                /*@todo right now we only search on e level deep, lets make that 5 */
                else {
                    $key = explode('.', $key);
                    // only one deep right now
                    //if(count($key) == 2){
                    if ($level == 0) {
                        $level++;
                        //var_dump($key[0]);
                        //($key[1]);
                        $query->leftJoin('value.objects', 'subObjects'.$level);
                        if ($key[1] == 'id') {
                            $query->andWhere('(subObjects'.$level.'.id = :'.$key[1].' OR subObjects'.$level.'.externalId = :'.$key[1].')')->setParameter($key[1], $value);
                        } else {
                            $query->leftJoin('subObjects'.$level.'.objectValues', 'subValue'.$level);
                        }
                    }

                    if ($key[1] != 'id') {
                        $query->andWhere('subValue'.$level.'.stringValue = :'.$key[1])->setParameter($key[1], $value);
                    }
                }

                // lets suport level 1
            }
        }

        return $query;
    }

    private function getAllValues(string $atribute, string $value): array
    {
    }

    private function getFilterParameters(Entity $Entity, string $prefix = '', int $level = 1): array
    {
        $filters = [];
        $filters[] = $prefix.'id';

        foreach ($Entity->getAttributes() as $attribute) {
            if ($attribute->getType() == 'string' && $attribute->getSearchable()) {
                $filters[] = $prefix.$attribute->getName();
            } elseif ($attribute->getObject() && $level < 5 && !str_contains($prefix, $attribute->getName().'.')) {
                $filters = array_merge($filters, $this->getFilterParameters($attribute->getObject(), $prefix.$attribute->getName().'.', $level + 1));
            }
            continue;
        }

        return $filters;
    }

    // Filter functie schrijven, checken op betaande atributen, zelf looping
    // voorbeeld filter student.generaldDesription.landoforigen=NL
    //                  entity.atribute.propert['name'=landoforigen]
    //                  (objectEntity.value.objectEntity.value.name=landoforigen and
    //                  objectEntity.value.objectEntity.value.value=nl)

    /*
    public function findOneBySomeField($value): ?ObjectEntity
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
