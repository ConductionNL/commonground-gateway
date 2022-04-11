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
     * Finds ObjectEntities using the given Entity and $filters array as filters. Can be ordered and allows pagination. Only $entity is required.
     *
     * @param Entity $entity The Entity
     * @param array  $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param array  $order An array with a key and value (asc/desc) used for ordering/sorting the result. See: getOrderParameters() for how to check for allowed fields to order.
     * @param int    $offset Pagination, the first result. 'offset' of the returned ObjectEntities.
     * @param int    $limit Pagination, the max amount of results. 'limit' of the returned ObjectEntities.
     *
     * @throws Exception
     *
     * @return array Returns an array of ObjectEntity objects
     */
    public function findByEntity(Entity $entity, array $filters = [], array $order = [], int $offset = 0, int $limit = 25): array
    {
        $query = $this->createQuery($entity, $filters, $order);

        return $query
            // filters toevoegen
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns an integer representing the total amount of results using the input to create a sql statement. $entity is required.
     *
     * @param Entity $entity The Entity
     * @param array  $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     *
     * @throws NoResultException|NonUniqueResultException
     *
     * @return int Returns an integer, for the total ObjectEntities found with this Entity and with the given filters.
     */
    public function countByEntity(Entity $entity, array $filters = []): int
    {
        $query = $this->createQuery($entity, $filters);
        $query->select($query->expr()->countDistinct('o'));

        return $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Transform dot filters (learningNeed.student.id = "uuid") into an array ['learningNeed' => ['student' => ['id' => "uuid"]]].
     *
     * @param array $key
     * @param $value
     * @param array $result
     *
     * @return array The transformed array.
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
     * Replace dot filters _ into . (symfony query param thing) and transform dot filters into an array with recursiveFilterSplit().
     *
     * @param array $array The array of query params / filters.
     * @param array $filterCheck The allowed filters. See: getFilterParameters().
     *
     * @return array A 'clean' array. And transformed array.
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
     * Main function for creating a ObjectEntity (get collection) query, with (required) $entity as filter. And optional extra filters and/or order.
     *
     * @param Entity $entity The Entity.
     * @param array  $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param array  $order An array with a key and value (asc/desc) used for ordering/sorting the result. See: getOrderParameters() for how to check for allowed fields to order.
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function createQuery(Entity $entity, array $filters = [], array $order = []): QueryBuilder
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
            $orderCheck = $this->getOrderParameters($entity);

            if (in_array(array_keys($order)[0], $orderCheck) && in_array(array_values($order)[0], ['desc', 'asc'])) {
                $query = $this->getObjectEntityOrder($query, array_keys($order)[0], array_values($order)[0]);
            }
        }

        return $query;
    }

    /**
     * Expands a QueryBuilder in the case that filters are used in createQuery()
     *
     * @param array        $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param QueryBuilder $query The existing QueryBuilder.
     * @param int          $level The depth level, if we are filtering on subresource.subresource etc.
     * @param string       $prefix The prefix of the value for the filter we are adding, default = 'value'.
     * @param string       $objectPrefix The prefix of the objectEntity for the filter we are adding, default = 'o'. ('o'= the main ObjectEntity, not a subresource)
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function buildQuery(array $filters, QueryBuilder $query, int $level = 0, string $prefix = 'value', string $objectPrefix = 'o'): QueryBuilder
    {
        foreach ($filters as $key => $value) {
            if (substr($key, 0, 1) == '_' || $key == 'id') {
                // If the filter starts with _ or == id we need to handle this filter differently
                $query = $this->getObjectEntityFilter($query, $key, $value, $objectPrefix);
            } elseif (is_array($value) && !array_key_exists('from', $value) && !array_key_exists('till', $value)) {
                // If $value is an array we need to check filters on a subresource (example: subresource.key = something)
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
            } else {
                // Make sure we only check the values of the correct attribute
                $query->leftJoin("$prefix.attribute", $key.$prefix.'Attribute');
                $query->andWhere($key.$prefix."Attribute.name = :Key$key")
                    ->setParameter("Key$key", $key);

                // Check if this is an dateTime from/till filter (example: endDate[from] = "2022-04-11 00:00:00")
                if (is_array($value) && (array_key_exists('from', $value) || array_key_exists('till', $value))) {
                    $query = $this->getDateTimeFilter($query, $key, $value, $prefix);
                } else {
                    // Check te actual value (example: key = value)
                    try {
                        // todo: find a way to detect if we need to do this? instead of always trying it (or just always use stringValue?)
                        // If we are comparing a date Y-m-d $value to database value datetime, we need a string with format Y-m-d H:i:s
                        $date = new DateTime($value);
                        $value = $date->format('Y-m-d H:i:s');
                    } catch (Exception $exception) {
                    }
                    // todo: find a way to detect which value type we need to check? or just always use stringValue, as long as we always set the stringValue in Value.php
                    $query->andWhere("$prefix.stringValue = :$key OR $prefix.dateTimeValue = :$key") // Both values work, even if stringValue is not set and dateTimeValue is
                    ->setParameter($key, $value);
                }
            }
        }

        return $query;
    }

    /**
     * Function that handles special filters starting with _ or the 'id' filter. Adds to an existing QueryBuilder.
     *
     * @param QueryBuilder $query The existing QueryBuilder.
     * @param $key
     * @param $value
     * @param string $prefix
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
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
                break;
            case '_dateModified':
                $query = $this->getDateTimeFilter($query, 'dateModified', $value, $prefix);
                break;
            default:
                //todo: error?
//                var_dump('Not supported filter for ObjectEntity: '.$key);
                break;
        }

        return $query;
    }

    /**
     * Function that handles from and/or till dateTime filters. Adds to an existing QueryBuilder.
     *
     * @param QueryBuilder $query The existing QueryBuilder.
     * @param $key
     * @param $value
     * @param string $prefix
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function getDateTimeFilter(QueryBuilder $query, $key, $value, string $prefix = 'o'): QueryBuilder
    {
        $prefixKey = 'dateTimeValue';
        if (in_array($key, ['dateCreated', 'dateModified'])) {
            $prefixKey = $key;
        }
        if (array_key_exists('from', $value)) {
            $date = new DateTime($value['from']);
            $query->andWhere($prefix.'.'.$prefixKey.' >= :'.$key.'From')->setParameter($key.'From', $date->format('Y-m-d H:i:s'));
        }
        if (array_key_exists('till', $value)) {
            $date = new DateTime($value['till']);
            $query->andWhere($prefix.'.'.$prefixKey.' <= :'.$key.'Till')->setParameter($key.'Till', $date->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    /**
     * Function that handles the order by filter. Adds to an existing QueryBuilder.
     *
     * @param QueryBuilder $query The existing QueryBuilder.
     * @param $key
     * @param $value
     * @param string $prefix
     *
     * @return QueryBuilder The QueryBuilder.
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

    /**
     * Gets and returns an array with the allowed filters on an Entity (including its subEntities / sub-filters).
     *
     * @param Entity $Entity
     * @param string $prefix
     * @param int    $level
     *
     * @return array The array with allowed filters.
     */
    public function getFilterParameters(Entity $Entity, string $prefix = '', int $level = 1): array
    {
        //todo: we only check for the allowed keys/attributes to filter on, if this attribute is an dateTime (or date), we should also check if the value is an dateTime string?

        // NOTE:
        // Filter id looks for ObjectEntity id and externalId
        // Filter _id looks specifically/only for ObjectEntity id
        // Filter _externalId looks specifically/only for ObjectEntity externalId

        // defaults
        $filters = [
            $prefix.'id', $prefix.'_id', $prefix.'_externalId', $prefix.'_uri', $prefix.'_organization',
            $prefix.'_application', $prefix.'_dateCreated', $prefix.'_dateModified', $prefix.'_mapping',
        ];

        foreach ($Entity->getAttributes() as $attribute) {
//            if ($attribute->getType() == 'string' && $attribute->getSearchable()) {
            if (in_array($attribute->getType(), ['string', 'date', 'datetime'])) {
                $filters[] = $prefix.$attribute->getName();
            } elseif ($attribute->getObject() && $level < 3 && !str_contains($prefix, $attribute->getName().'.')) {
                $filters = array_merge($filters, $this->getFilterParameters($attribute->getObject(), $prefix.$attribute->getName().'.', $level + 1));
            }
        }

        return $filters;
    }

    /**
     * Gets and returns an array with the allowed sortable attributes on an Entity (including its subEntities).
     *
     * @param Entity $Entity
     * @param string $prefix
     * @param int    $level
     *
     * @return array The array with allowed attributes to sort by.
     */
    public function getOrderParameters(Entity $Entity, string $prefix = '', int $level = 1): array
    {
        // defaults
        $sortable = ['_dateCreated', '_dateModified'];

        // todo: add something to ObjectEntities just like bool searchable, use that to check for fields allowed to be used for ordering.
        // todo: sortable?
//        foreach ($Entity->getAttributes() as $attribute) {
//            if (in_array($attribute->getType(), ['date', 'datetime']) && $attribute->getSortable()) {
//                $sortable[] = $prefix.$attribute->getName();
//            } elseif ($attribute->getObject() && $level < 3 && !str_contains($prefix, $attribute->getName().'.')) {
//                $sortable = array_merge($sortable, $this->getOrderParameters($attribute->getObject(), $prefix.$attribute->getName().'.', $level + 1));
//            }
//        }

        return $sortable;
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

    //todo: remove?
    private function getAllValues(string $atribute, string $value): array
    {
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
