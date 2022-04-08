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
     * @TODO
     *
     * @param Entity $entity
     * @param array $filters
     * @param array $order
     * @param int $offset
     * @param int $limit
     *
     * @return array Returns an array of ObjectEntity objects
     * @throws Exception
     */
    public function findByEntity(Entity $entity, array $filters = [], array $order = [], int $offset = 0, int $limit = 25): array
    {
        $query = $this->createQuery($entity, $filters, $order);
        var_dump($query->getQuery()->getSQL());

        return $query
            // filters toevoegen
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @TODO
     *
     * @param Entity $entity
     * @param array $filters
     *
     * @return int Returns an integer, for the total ObjectEntities found with this Entity and with the given filters.
     * @throws NoResultException|NonUniqueResultException
     */
    public function countByEntity(Entity $entity, array $filters = []): int
    {
        $query = $this->createQuery($entity, $filters);
        $query->select($query->expr()->countDistinct('o'));

        return $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Transform dot filters (learningNeed.student.id = "uuid") into an array ['learningNeed' => ['student' => ['id' => "uuid"]]]
     *
     * @param array $key
     * @param $value
     * @param array $result
     *
     * @return array
     */
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
     * Replace dot filters _ into . (symfony query param thing) and transform dot filters into array with recursiveFilterSplit()
     *
     * @param array $array
     * @param array $filterCheck
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
            if (in_array($key, $filterCheck)) {
                $result = $this->recursiveFilterSplit(explode('.', $key), $value, $result);
            }
        }

        return $result;
    }

    /**
     * @TODO
     *
     * @param array $filters
     * @param QueryBuilder $query
     * @param int $level
     * @param string $prefix
     * @param string $objectPrefix
     *
     * @return QueryBuilder
     * @throws Exception
     */
    private function buildQuery(array $filters, QueryBuilder $query, int $level = 0, string $prefix = 'value', string $objectPrefix = 'o'): QueryBuilder
    {
        foreach ($filters as $key => $value) {
            var_dump($key);
            if (substr($key, 0, 1) == '_' || $key == 'id') {
                $query = $this->getObjectEntityFilter($query, $key, $value, $objectPrefix);
            } elseif (is_array($value)) {
                if (array_key_exists('from', $value) || array_key_exists('till', $value)) {
                    $query = $this->getDateTimeFilter($query, $key, $value, $objectPrefix); //todo this needs to be somewhere else so the prefix is correct
                } else {
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
                }
            } else {
                var_dump($key);
                try {
                    // If we are comparing a date Y-m-d $value to database value datetime, we need a string with format Y-m-d H:i:s
                    $date = new DateTime($value);
                    $value = $date->format('Y-m-d H:i:s');
                } catch (Exception $exception) {
                    //todo something
                }
                var_dump($value);
                $query->andWhere("$prefix.stringValue = :$key") // todo: we should check for dateTimeValue here? instead of setting stringValue for datetimes and checking that way
                    ->setParameter($key, $value);
            }
        }

        return $query;
    }

    /**
     * @TODO
     *
     * @param Entity $entity
     * @param array $filters
     * @param array $order
     *
     * @return QueryBuilder
     * @throws Exception
     */
    private function createQuery(Entity $entity, array $filters, array $order = []): QueryBuilder
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

        // TODO: This is a quick fix for taalhuizen, find a better way of showing taalhuizen and teams for an anonymous user!
        if ($this->session->get('anonymous') === true && in_array($query->getParameter('type')->getValue(), ['taalhuis', 'team'])) {
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

        if (!empty($order)) {
            $query = $this->getObjectEntityOrder($query, array_keys($order)[0], array_values($order)[0]);
        }

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
     * @TODO
     *
     * @param QueryBuilder $query
     * @param $key
     * @param $value
     * @param string $prefix
     *
     * @return QueryBuilder
     * @throws Exception
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
                $query = $this->getDateTimeFilter($query, 'dateCreated', $value, $prefix);
//                if (array_key_exists('from', $value)) {
//                    $date = new DateTime($value['from']);
//                    $query->andWhere($prefix.'.dateCreated >= :dateCreatedFrom')->setParameter('dateCreatedFrom', $date->format('Y-m-d H:i:s'));
//                }
//                if (array_key_exists('till', $value)) {
//                    $date = new DateTime($value['till']);
//                    $query->andWhere($prefix.'.dateCreated <= :dateCreatedTill')->setParameter('dateCreatedTill', $date->format('Y-m-d H:i:s'));
//                }
                break;
            case '_dateModified':
                $query = $this->getDateTimeFilter($query, 'dateModified', $value, $prefix);
//                if (array_key_exists('from', $value)) {
//                    $date = new DateTime($value['from']);
//                    $query->andWhere($prefix.'.dateModified >= :dateModifiedFrom')->setParameter('dateModifiedFrom', $date->format('Y-m-d H:i:s'));
//                }
//                if (array_key_exists('till', $value)) {
//                    $date = new DateTime($value['till']);
//                    $query->andWhere($prefix.'.dateModified <= :dateModifiedTill')->setParameter('dateModifiedTill', $date->format('Y-m-d H:i:s'));
//                }
                break;
            default:
                //todo: error?
//                var_dump('Not supported filter for ObjectEntity: '.$key);
                break;
        }

        return $query;
    }

    /**
     * @TODO
     *
     * @param QueryBuilder $query
     * @param $key
     * @param $value
     * @param string $prefix
     *
     * @return QueryBuilder
     * @throws Exception
     */
    private function getDateTimeFilter(QueryBuilder $query, $key, $value, string $prefix = 'o'): QueryBuilder
    {
        if (array_key_exists('from', $value)) {
            $date = new DateTime($value['from']);
            $query->andWhere($prefix.'.'.$key.' >= :'.$key.'From')->setParameter($key.'From', $date->format('Y-m-d H:i:s'));
        }
        if (array_key_exists('till', $value)) {
            $date = new DateTime($value['till']);
            $query->andWhere($prefix.'.'.$key.' <= :'.$key.'Till')->setParameter($key.'Till', $date->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    /**
     * @TODO
     *
     * @param QueryBuilder $query
     * @param $key
     * @param $value
     * @param string $prefix
     *
     * @return QueryBuilder
     */
    private function getObjectEntityOrder(QueryBuilder $query, $key, $value, string $prefix = 'o'): QueryBuilder
    {
        switch ($key) {
            case '_dateCreated':
                $query->orderBy($prefix.'.dateCreated', $value);
                break;
            case '_dateModified':
                $query->orderBy($prefix.'.dateModified', $value);
                break;
            default:
                $query->orderBy($prefix.'.'.$key, $value);
                break;
        }

        return $query;
    }

    //todo: remove?
    private function getAllValues(string $atribute, string $value): array
    {
    }

    /**
     * @TODO
     *
     * @param Entity $Entity
     * @param string $prefix
     * @param int $level
     *
     * @return array
     */
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
            if (in_array($attribute->getType(), ['string', 'date', 'datetime'])) {
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
