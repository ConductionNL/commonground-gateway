<?php

namespace App\Repository;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @method ObjectEntity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ObjectEntity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ObjectEntity[]    findAll()
 * @method ObjectEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ObjectEntityRepository extends ServiceEntityRepository
{
    private SessionInterface $session;
    private TokenStorageInterface $tokenStorage;

    public function __construct(ManagerRegistry $registry, SessionInterface $session, TokenStorageInterface $tokenStorage)
    {
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;

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

//        var_dump('Query findByEntity:');
//        var_dump($query->getDQL());

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
        $query->select('count(o)');

//        var_dump('Query countByEntity:');
//        var_dump($query->getDQL());

        return $query->getQuery()->getSingleScalarResult();
    }

    private function createQuery(Entity $entity, array $filters): QueryBuilder
    {
        $query = $this->createQueryBuilder('o')
            ->andWhere('o.entity = :entity')
            ->setParameters(['entity' => $entity]);

        if (!empty($filters)) {
            $filterCheck = $this->getFilterParameters($entity);
            $query->leftJoin('o.objectValues', 'value');
            $level = 0;

            foreach ($filters as $key=>$value) {
                // Symfony has the tendency to replace . with _ on query parameters
                $key = str_replace(['_'], ['.'], $key);
                $key = str_replace(['..'], ['._'], $key);
                if (substr($key, 0, 1) == '.') {
                    $key = '_'.ltrim($key, $key[0]);
                }

                // We want to use custom logic for _ filters, because they will be used directly on the ObjectEntities themselves.
                if (substr($key, 0, 1) == '_') {
                    $query = $this->getObjectEntityFilter($query, $key, $value);
                    unset($filters[$key]); //todo: why unset if we never use filters after this?
                    continue;
                }
                // Lets see if this is an allowed filter
                if (!in_array($key, $filterCheck)) {
                    unset($filters[$key]); //todo: why unset if we never use filters after this?
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

                        // Deal with _ filters for subresources
                        if (substr($key[1], 0, 1) == '_' || $key[1] == 'id') {
                            $query = $this->getObjectEntityFilter($query, $key[1], $value, 'subObjects'.$level);
                            continue;
                        }
                        $query->leftJoin('subObjects'.$level.'.objectValues', 'subValue'.$level);
                    }
                    // Deal with _ filters for subresources
                    if (substr($key[1], 0, 1) == '_' || $key[1] == 'id') {
                        $query = $this->getObjectEntityFilter($query, $key[1], $value, 'subObjects'.$level);
                        continue;
                    }
                    $query->andWhere('subValue'.$level.'.stringValue = :'.$key[1])->setParameter($key[1], $value);
                }

                // lets suport level 1
            }
        }

        //TODO: owner check
//        $user = $this->tokenStorage->getToken()->getUser();
//
//        if (is_string($user)) {
//            $user = null;
//        } else {
//            $user = $user->getUserIdentifier();
//        }

        // TODO: organizations or owner
        // Multitenancy, only show objects this user is allowed to see.
        // Only show objects this user owns or object that have an organization this user is part of or that are inhereted down the line
        $organizations = $this->session->get('organizations', []);
        $parentOrganizations = $this->session->get('parentOrganizations', []);

        $query->andWhere('o.organization IN (:organizations) OR (o.organization IN (:parentOrganizations) and o.entity.inherited == true) ')
            ->setParameter('organizations', $organizations)
            ->setParameter('parentOrganizations', $parentOrganizations);
        /*
        if (empty($this->session->get('organizations'))) {
            $query->andWhere('o.organization IN (:organizations)')->setParameter('organizations', []);
        } else {
            $query->andWhere('o.organization IN (:organizations)')->setParameter('organizations', $this->session->get('organizations'));
        }
        */

//        var_dump($query->getDQL());

        return $query;
    }

    //todo: typecast?

    /**
     * @param QueryBuilder $query
     * @param $key
     * @param $value
     * @param string $prefix
     *
     * @throws Exception
     *
     * @return QueryBuilder
     */
    private function getObjectEntityFilter(QueryBuilder $query, $key, $value, string $prefix = 'o'): QueryBuilder
    {
//        var_dump('filter :');
//        var_dump($key);
//        var_dump($value);
        switch ($key) {
            case 'id':
                $query->andWhere('('.$prefix.'.id = :'.$key.' OR '.$prefix.'.externalId = :'.$key.')')->setParameter($key, $value);
                break;
            case '_id':
                $query->andWhere($prefix.'.id = :id')->setParameter('id', $value);
                break;
            case '_externalId':
                $query->andWhere($prefix.'.externalId = :externalId')->setParameter('externalId', $value);
                break;
            case '_uri':
                $query->andWhere($prefix.'.uri = :uri')->setParameter('uri', $value);
                break;
            case '_organization':
                $query->andWhere($prefix.'.organization = :organization')->setParameter('organization', $value);
                break;
            case '_application':
                $query->andWhere($prefix.'.application = :application')->setParameter('application', $value);
                break;
            case '_dateCreated':
                if (array_key_exists('from', $value)) {
                    $date = new DateTime($value['from']);
                    $query->andWhere($prefix.'.dateCreated >= :dateCreatedFrom')->setParameter('dateCreatedFrom', $date->format('Y-m-d H:i:s'));
                }
                if (array_key_exists('till', $value)) {
                    $date = new DateTime($value['till']);
                    $query->andWhere($prefix.'.dateCreated <= :dateCreatedTill')->setParameter('dateCreatedTill', $date->format('Y-m-d H:i:s'));
                }
                break;
            case '_dateModified':
                if (array_key_exists('from', $value)) {
                    $date = new DateTime($value['from']);
                    $query->andWhere($prefix.'.dateModified >= :dateModifiedFrom')->setParameter('dateModifiedFrom', $date->format('Y-m-d H:i:s'));
                }
                if (array_key_exists('till', $value)) {
                    $date = new DateTime($value['till']);
                    $query->andWhere($prefix.'.dateModified <= :dateModifiedTill')->setParameter('dateModifiedTill', $date->format('Y-m-d H:i:s'));
                }
                break;
            default:
                //todo: error?
//                var_dump('Not supported filter for ObjectEntity');
                break;
        }

        return $query;
    }

    private function getAllValues(string $atribute, string $value): array
    {
    }

    public function getFilterParameters(Entity $Entity, string $prefix = '', int $level = 1): array
    {
        // NOTE:
        // Filter id looks for ObjectEntity id and externalId
        // Filter _id looks specifically/only for ObjectEntity id
        // Filter _externalId looks specifically/only for ObjectEntity externalId
        if ($level != 1) {
            // For level 1 we should not allow filter id, because this is just a get Item call (not needed for a get collection)
            // Maybe we should do the same for _id & _externalId if we allow to use _ filters on subresources?
            $filters = [$prefix.'id'];
        }

        // defaults
        $filters = array_merge($filters ?? [], [
            $prefix.'_id', $prefix.'_externalId', $prefix.'_uri', $prefix.'_organization', $prefix.'_application',
            $prefix.'_dateCreated', $prefix.'_dateModified', $prefix.'_mapping',
        ]);

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
