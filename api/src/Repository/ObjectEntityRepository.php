<?php

namespace App\Repository;

use App\Entity\ObjectEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ObjectEntity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ObjectEntity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ObjectEntity[]    findAll()
 * @method ObjectEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ObjectEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObjectEntity::class);
    }

   /**
    * @return ObjectEntity[] Returns an array of ObjectEntity objects
    */

   // typecast deze shizle
    public function findByEntity($entity, $filters = [],  $offset = 0, $limit = 25 )
    {
        $query = $this->createQueryBuilder('o')
            ->andWhere('o.entity = :entity')
            ->setParameter('entity', $entity);

        if(!empty($filters)){
            $filterCheck = $this->getFilterParameters($entity);

            foreach($filters as $key=>$value){
                if(!in_array($key,$filterCheck)){
                    unset($filters[$key]);
                    continue;
                }

                // lets suport level 1
            }
        }


        return $query
            // filters toevoegen
            ->setFirstResult( $offset )
            ->setMaxResults( $limit )
            ->getQuery()
            ->getResult()
        ;

    }


    private function getFilterParameters(ObjectEntity $Entity, string $prefix = '', int $level = 1): array
    {
        $filters = [];

        foreach($Entity->getAttributes() as $attribute){
            if($attribute->getType() == 'string'){
                $filters[]= $prefix.$attribute->getName();
            }
            elseif($attribute->getObject()  && $level < 5){
                $filters = array_merge($filters, $this->getFilterParameters($attribute->getObject(), $attribute->getName().'.',  $level+1));
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
