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
        $query->select($query->expr()->countDistinct('o'));

        return $query->getQuery()->getSingleScalarResult();
    }

    private function recursiveFilterSplit(array $key, $value, array $result): array
    {
        if (count($key) > 1) {
            $currentKey = array_shift($key);
            $result[$currentKey] = $this->recursiveFilterSplit($key, $value, $result[$currentKey] ?? []);
        } else {
            $result[array_shift($key)] = $value;
        }

        return $result;
    }

    /**
     * @param $array
     *
     * @return array
     */
    private function cleanArray(array $array, array $filterCheck): array
    {
        $result = [];
        foreach ($array as $key=>$value) {
            $key = str_replace(['_', '..'], ['.', '._'], $key);
            if (substr($key, 0, 1) == '.') {
                $key = '_'.ltrim($key, $key[0]);
            }
            if (!(substr($key, 0, 1) == '_') && in_array($key, $filterCheck)) {
                $result = $this->recursiveFilterSplit(explode('.', $key), $value, $result);
            }
        }

        return $result;
    }

    private function buildQuery(array $filters, QueryBuilder $query, int $level = 0, string $prefix = 'value', string $objectPrefix = 'o'): QueryBuilder
    {
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $query->leftJoin("$objectPrefix.objectValues", "$prefix$key");
                $query->leftJoin("$prefix$key.objects", 'subObjects'.$key.$level);
                $query->leftJoin('subObjects'.$key.$level.'.objectValues', 'subValue'.$key.$level);
                $query = $this->buildQuery(
                    $value,
                    $query,
                    $level + 1,
                    'subValue'.$key.$level,
                    'subObjects'.$key.$level
                );
            } elseif (substr($key, 0, 1) == '_' || $key == 'id') {
                $query = $this->getObjectEntityFilter($query, $key, $value, $objectPrefix);
            } else {
                $query->andWhere("$prefix.stringValue = :$key")
                    ->setParameter($key, $value);
            }
        }

        return $query;
    }

    private function createQuery(Entity $entity, array $filters): QueryBuilder
    {
        $query = $this->createQueryBuilder('o')
            ->andWhere('o.entity = :entity')
            ->setParameters(['entity' => $entity]);

        if (!empty($filters)) {
            $filterCheck = $this->getFilterParameters($entity);

            $filters = $this->cleanArray($filters, $filterCheck);

            $query->leftJoin('o.objectValues', 'value');
            $this->buildQuery($filters, $query)->distinct();
        }

        //TODO: owner check
//        $user = $this->tokenStorage->getToken()->getUser();
//
//        if (is_string($user)) {
//            $user = null;
//        } else {
//            $user = $user->getUserIdentifier();
//        }

        // TODO: This is a quick fix for taalhuizen, find a better way of showing taalhuizen for an anonymous user!
        if ($this->session->get('anonymous') === true && $query->getParameter('type')->getValue() === 'taalhuis') {
            return $query;
        }

        // TODO: owner
        // Multitenancy, only show objects this user is allowed to see.
        // Only show objects this user owns or object that have an organization this user is part of or that are inhereted down the line
        $organizations = $this->session->get('organizations', []);
        $parentOrganizations = [];
        // Make sure we only check for parentOrganizations if inherited is true in the (ObjectEntity)->entity->inherited
        if ($entity->getInherited()) {
            $parentOrganizations = $this->session->get('parentOrganizations', []);
        }

        //$query->andWhere('o.organization IN (:organizations) OR o.organization IN (:parentOrganizations) OR o.owner == :userId')
        $query->andWhere('o.organization IN (:organizations) OR o.organization IN (:parentOrganizations)')
        //    ->setParameter('userId', $userId)
            ->setParameter('organizations', $organizations)
            ->setParameter('parentOrganizations', $parentOrganizations);
        /*
        if (empty($this->session->get('organizations'))) {
            $query->andWhere('o.organization IN (:organizations)')->setParameter('organizations', []);
        } else {
            $query->andWhere('o.organization IN (:organizations)')->setParameter('organizations', $this->session->get('organizations'));
        }
        */

        return $query;
    }

    //todo: typecast?
    //todo: remove?
    private function buildFilter(QueryBuilder $query, $filters, $prefix = 'o', $level = 0): QueryBuilder
    {
        $query->leftJoin($prefix.'.objectValues', $level.'.objectValues');
        foreach ($filters as $key => $filter) {
            if (!is_array($filter) && substr($key, 0, 1) != '_') {
                $query->andWhere($level.'.objectValues'.'.stringValue = :'.$key)->setParameter($key, $filter);
            } elseif (!is_array($filter) && substr($key, 0, 1) == '_') {
                // do magic
                $query = $this->getObjectEntityFilter($query, $key, $filter, $prefix);
            } elseif (is_array($filter)) {
                $query = $this->buildFilter($query, $filters, $level++);
            } else {
                // how dit we end up here?
            }
        }

        return $query;
    }

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
        switch ($key) {
            case 'id':
                $query->andWhere('('.$prefix.'.id = :'.$prefix.$key.' OR '.$prefix.'.externalId = :'.$prefix.$key.')')->setParameter($prefix.$key, $value);
                break;
            case '_id':
                $query->andWhere($prefix.".id = :{$prefix}id")->setParameter("{$prefix}id", $value);
                break;
            case '_externalId':
                $query->andWhere($prefix.".externalId = :{$prefix}externalId")->setParameter("{$prefix}externalId", $value);
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
//                var_dump('Not supported filter for ObjectEntity: '.$key);
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
//            if ($attribute->getType() == 'string' && $attribute->getSearchable()) {
            if ($attribute->getType() == 'string') {
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
