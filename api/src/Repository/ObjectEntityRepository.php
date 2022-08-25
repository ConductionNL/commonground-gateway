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
use Symfony\Component\Security\Core\Security;

/**
 * @method ObjectEntity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ObjectEntity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ObjectEntity[]    findAll()
 * @method ObjectEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ObjectEntityRepository extends ServiceEntityRepository
{
    private SessionInterface $session;
    private Security $security;

    public function __construct(ManagerRegistry $registry, SessionInterface $session, Security $security)
    {
        $this->session = $session;
        $this->security = $security;

        parent::__construct($registry, ObjectEntity::class);
    }

    /**
     * Does the same as findByEntity(), but also returns an integer representing the total amount of results using the input to create a sql statement. $entity is required.
     *
     * @param Entity $entity  The Entity
     * @param array  $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param array  $order   An array with a key and value (asc/desc) used for ordering/sorting the result. See: getOrderParameters() for how to check for allowed fields to order.
     * @param int    $offset  Pagination, the first result. 'offset' of the returned ObjectEntities.
     * @param int    $limit   Pagination, the max amount of results. 'limit' of the returned ObjectEntities.
     *
     * @throws NoResultException|NonUniqueResultException
     *
     * @return array With a key 'objects' containing the actual objects found and a key 'total' with an integer representing the total amount of results found.
     */
    public function findAndCountByEntity(Entity $entity, array $filters = [], array $order = [], int $offset = 0, int $limit = 25): array
    {
        // Only use createQuery once, because doing it for both findByEntity & countByEntity will take longer! Do not use $order here.
        $baseQuery = $this->createQuery($entity, $filters);
        // Clone the baseQuery into a new QueryBuilder.
        // (because OrderBy or setFirstResult($offset) on the $baseQuery in findByEntity function will break countByEntity if $baseQuery is re-used)
        $countQuery = clone $baseQuery;

        // If we have to order do it only for the findByEntity QueryBuilder.
        $baseQuery = $this->addOrderBy($baseQuery, $entity, $order);

        return [
            'objects' => $this->findByEntity($entity, [], [], $offset, $limit, $baseQuery),
            'total'   => $this->countByEntity($entity, [], $countQuery),
        ];
    }

    /**
     * Finds ObjectEntities using the given Entity and $filters array as filters. Can be ordered and allows pagination. Only $entity is required. Always use getFilterParameters() to check for allowed filters before using this function!
     *
     * @param Entity            $entity  The Entity
     * @param array             $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param array             $order   An array with a key and value (asc/desc) used for ordering/sorting the result. See: getOrderParameters() for how to check for allowed fields to order.
     * @param int               $offset  Pagination, the first result. 'offset' of the returned ObjectEntities.
     * @param int               $limit   Pagination, the max amount of results. 'limit' of the returned ObjectEntities.
     * @param QueryBuilder|null $query   An already existing QueryBuilder created with createQuery() to use instead of creating a new one.
     *
     * @throws Exception
     *
     * @return array Returns an array of ObjectEntity objects
     */
    public function findByEntity(Entity $entity, array $filters = [], array $order = [], int $offset = 0, int $limit = 25, QueryBuilder $query = null): array
    {
        $query = $query ?? $this->createQuery($entity, $filters, $order);

        return $query
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // todo: see findAndCountByEntity() this function can be removed, but here in case we ever want to use this separately from findByEntity()
    /**
     * Returns an integer representing the total amount of results using the input to create a sql statement. $entity is required.
     *
     * @param Entity            $entity  The Entity
     * @param array             $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param QueryBuilder|null $query   An already existing QueryBuilder created with createQuery() to use instead of creating a new one.
     *
     * @throws NoResultException|NonUniqueResultException
     *
     * @return int Returns an integer, for the total ObjectEntities found with this Entity and with the given filters.
     */
    public function countByEntity(Entity $entity, array $filters = [], QueryBuilder $query = null): int
    {
        $query = $query ?? $this->createQuery($entity, $filters);
        $query->select($query->expr()->countDistinct('o'));

        return $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Main function for creating a ObjectEntity (get collection) query, with (required) $entity as filter. And optional extra filters and/or order.
     *
     * @param Entity $entity  The Entity.
     * @param array  $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param array  $order   An array with a key and value (asc/desc) used for ordering/sorting the result. See: getOrderParameters() for how to check for allowed fields to order.
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

        if (array_key_exists('search', $filters)) {
            $search = $filters['search'];
            unset($filters['search']);
            $this->buildSearchQuery($search, $query)->distinct();
        }

        if (!empty($filters)) {
            $filterCheck = $this->getFilterParameters($entity);
            $multipleAttributes = $this->getMultipleAttributes($entity);

            $filters = $this->cleanFiltersArray($filters, $filterCheck, $multipleAttributes);

            $this->buildQuery($filters, $query)->distinct();
        }

        // TODO: This is a quick fix for taalhuizen, find a better way of showing taalhuizen and teams for an anonymous user!
        if ($this->session->get('anonymous') === true && !empty($query->getParameter('type')) && in_array($query->getParameter('type')->getValue(), ['taalhuis', 'team'])) {
            return $query;
        }

        $user = $this->security->getUser();
        if (!$user) {
            $userId = 'anonymousUser'; // Do not use null here!
        } else {
            $userId = $user->getUserIdentifier();
        }

        // Multitenancy, only show objects this user is allowed to see.
        // Only show objects this user owns or object that have an organization this user is part of or that are inhereted down the line
        $organizations = $this->session->get('organizations', []);
        $parentOrganizations = [];
        // Make sure we only check for parentOrganizations if inherited is true in the (ObjectEntity)->entity->inherited
        if ($entity->getInherited()) {
            $parentOrganizations = $this->session->get('parentOrganizations', []);
        }

        $query->andWhere('o.organization IN (:organizations) OR o.organization IN (:parentOrganizations) OR o.organization = :defaultOrganization OR o.owner = :userId')
            ->setParameter('userId', $userId)
            ->setParameter('organizations', $organizations)
            ->setParameter('parentOrganizations', $parentOrganizations)
            ->setParameter('defaultOrganization', 'http://testdata-organization');

        return $this->addOrderBy($query, $entity, $order);
    }

    /**
     * Gets and returns an array with all attributes names (as array key, dot notation) that have multiple set to true.
     * Works for subresources as well.
     *
     * @param Entity $entity
     * @param string $prefix
     * @param int    $level
     *
     * @return array An array with all attributes that have multiple set to true.
     */
    private function getMultipleAttributes(Entity $entity, string $prefix = '', int $level = 1): array
    {
        $multipleAttributes = [];
        foreach ($entity->getAttributes() as $attribute) {
            if ($attribute->getMultiple()) {
                $multipleAttributes[$prefix.$attribute->getName()] = true;
            }
            if ($attribute->getObject() && $level < 3 && !str_contains($prefix, $attribute->getName().'.')) {
                $multipleAttributes = array_merge($multipleAttributes, $this->getMultipleAttributes($attribute->getObject(), $prefix.$attribute->getName().'.', $level + 1));
            }
        }

        return $multipleAttributes;
    }

    /**
     * Handle valueScopeFilter, replace dot filters _ into . (symfony query param thing) and transform dot filters into an array with recursiveFilterSplit().
     * Also checks for filters used on multiple attributes and handles these.
     *
     * @param array $filters       The array of query params / filters.
     * @param array $filterCheck The allowed filters. See: getFilterParameters().
     *
     * @return array A 'clean' array. And transformed array.
     */
    private function cleanFiltersArray(array $filters, array $filterCheck, array $multipleAttributes): array
    {
        $result = [];

        // Handles valueScopeFilters. This will prevent duplicate filters and makes sure the user can not bypass authorization by using filters!
        $filters = $this->handleValueScopeFilters($filters);

        foreach ($filters as $key => $value) {
            $key = str_replace(['__', '_', '..'], ['__', '.', '._'], $key);
            if (substr($key, 0, 1) == '.') {
                $key = '_'.ltrim($key, $key[0]);
            }
            if (in_array($key, $filterCheck) || str_ends_with($key, '|valueScopeFilter')) {
                $key = str_replace('|valueScopeFilter', '', $key);
                $key = array_key_exists($key, $multipleAttributes) ? $key.'|multiple' : $key;
                $result = $this->recursiveFilterSplit(explode('.', $key), $value, $result);
            }
        }

        return $result;
    }

    /**
     * Check which filters should remain if user wants to filter on one of the valueScopeFilters. This will prevent duplicate filters and makes sure the user can not bypass authorization by using filters!
     *
     * @param array $array The input array.
     *
     * @return array The updated array.
     */
    private function handleValueScopeFilters(array $array): array
    {
        foreach ($array as $key => $value) {
            if (str_ends_with($key, '|valueScopeFilter')) {
                $key = str_replace('|valueScopeFilter', '', $key);
                // If a filter is added because of scopes & the user wants to filter on the same $key, make sure we give prio to the user input,
                // but only allow the filter if the value used as input is present in the valueScopesFilter values.
                if (in_array($key, array_keys($array))) { //todo this does not yet work for $key (/valueScopes with) subresources: emails.email, because at this point they will have _ instead of . (emails_email)
                    if (in_array($array[$key], $value)) {
                        unset($array[$key.'|valueScopeFilter']);
                        continue;
                    }
                    unset($array[$key]);
                }
            }
        }

        return $array;
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
            $newKey = array_shift($key);
            if (is_array($value)) {
                $newKey = $newKey.'|arrayValue';
                // todo: remove from & till after gateway refactor
                if (!empty(array_intersect_key($value, array_flip(['from', 'till', 'after', 'before', 'strictly_after', 'strictly_before'])))) {
                    $newKey = $newKey.'|compareDateTime';
                }
            }
            $result[$newKey] = $value;
        }

        return $result;
    }

    /**
     * Expands a QueryBuilder in the case that the search query is used.
     *
     * @param string       $search
     * @param QueryBuilder $query
     *
     * @throws Exception
     *
     * @return QueryBuilder
     */
    private function buildSearchQuery(string $search, QueryBuilder $query): QueryBuilder
    {
        $query
            ->leftJoin('o.objectValues', 'valueSearch')
            ->leftJoin('valueSearch.attribute', 'valueSearchAttribute')
            ->andWhere('LOWER(valueSearch.stringValue) LIKE :search AND valueSearchAttribute.searchPartial IS NOT NULL')
            ->setParameter('search', '%'.strtolower($search).'%');

        return $query;
    }

    /**
     * Expands a QueryBuilder in the case that filters are used in createQuery().
     *
     * @param array        $filters      An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param QueryBuilder $query        The existing QueryBuilder.
     * @param int          $level        The depth level, if we are filtering on subresource.subresource etc.
     * @param string       $prefix       The prefix of the value for the filter we are adding, default = 'value'.
     * @param string       $objectPrefix The prefix of the objectEntity for the filter we are adding, default = 'o'. ('o'= the main ObjectEntity, not a subresource)
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function buildQuery(array $filters, QueryBuilder $query, int $level = 0, string $prefix = 'value', string $objectPrefix = 'o'): QueryBuilder
    {
        foreach ($filters as $key => $value) {
            $filterKey = $this->clearFilterKey($key);

            $query->leftJoin("$objectPrefix.objectValues", $prefix.$filterKey['key']);

            if (substr($filterKey['key'], 0, 1) == '_' || $filterKey['key'] == 'id') {
                // If the filter starts with _ or == id we need to handle this filter differently
                $query = $this->getObjectEntityFilter($query, $filterKey['key'], $value, $objectPrefix);
            } elseif (is_array($value) && !$filterKey['arrayValue']) {
                // If $value is an array we need to check filters on a subresource (example: subresource.key = something)
                $query = $this->buildSubresourceQuery($query, $filterKey['key'], $value, $level, $prefix);
            } else {
                $query = $this->buildFilterQuery($query, $filterKey, $value, $prefix);
            }
        }

        return $query;
    }

    /**
     * Clears any |example strings from the $key and returns the cleared key and booleans instead.
     *
     * @param string $key The key
     *
     * @return array An array containing the clear key and arrayValue & compareDateTime boolean.
     */
    private function clearFilterKey(string $key): array
    {
        if (str_contains($key, '|arrayValue')) {
            $arrayValue = true;
            $key = str_replace('|arrayValue', '', $key);
            if (str_ends_with($key, '|compareDateTime')) {
                $compareDateTime = true;
                $key = str_replace('|compareDateTime', '', $key);
            }
        }
        if (str_contains($key, '|multiple')) {
            $multiple = true;
            $key = str_replace('|multiple', '', $key);
        }

        return [
            'key'             => $key,
            'arrayValue'      => $arrayValue ?? false,
            'compareDateTime' => $compareDateTime ?? false,
            'multiple'        => $multiple ?? false,
        ];
    }

    /**
     * Function that handles special filters starting with _ or the 'id' filter. Adds to an existing QueryBuilder.
     *
     * @param QueryBuilder $query  The existing QueryBuilder.
     * @param string       $key    The key of the filter.
     * @param array        $value  The value of the filter.
     * @param string       $prefix The prefix of the value for the filter we are adding.
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
                throw new Exception('Not supported filter for ObjectEntity: '.$key);
        }

        return $query;
    }

    /**
     * Expands a QueryBuilder in the case a filter for a subresource is used (example: subresource.key = something).
     *
     * @param QueryBuilder $query  The existing QueryBuilder.
     * @param string       $key    The key of the filter.
     * @param array        $value  The value of the filter.
     * @param int          $level  The depth level, if we are filtering on subresource.subresource etc.
     * @param string       $prefix The prefix of the value for the filter we are adding.
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function buildSubresourceQuery(QueryBuilder $query, string $key, array $value, int $level, string $prefix): QueryBuilder
    {
        // If $value is an array we need to check filters on a subresource (example: subresource.key = something)
        $query->leftJoin("$prefix$key.objects", 'subObjects'.$key.$level);
        $query->leftJoin('subObjects'.$key.$level.'.objectValues', 'subValue'.$key.$level);
        $query = $this->buildQuery(
            $value,
            $query,
            $level + 1,
            'subValue'.$key.$level,
            'subObjects'.$key.$level
        );

        return $query;
    }

    /**
     * Expands a QueryBuilder with the correct filters, using the given input and prefix.
     *
     * @param QueryBuilder $query     The existing QueryBuilder.
     * @param array        $filterKey Array containing the key of the filter and some info about it, see clearFilterKey().
     * @param string|array $value     The value of the filter.
     * @param string       $prefix    The prefix of the value for the filter we are adding.
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function buildFilterQuery(QueryBuilder $query, array $filterKey, $value, string $prefix): QueryBuilder
    {
        $key = $filterKey['key'];

        // Make sure we only check the values of the correct attribute
        $query->leftJoin("$prefix$key.attribute", $prefix.$key.'Attribute');
        $query->andWhere($prefix.$key."Attribute.name = :Key$key")
            ->setParameter("Key$key", $key);

        // Check if this filter has an array of values (example1: key = value,value2) (example2: key[a] = value, key[b] = value2)
        if (is_array($value) && $filterKey['arrayValue']) {
            $query = $this->getArrayValueFilter($query, $filterKey, $value, $prefix);
        } else {
            $query = $this->getNormalValueFilter($query, $filterKey, $value, $prefix);
        }

        return $query;
    }

    /**
     * Expands a QueryBuilder with an OR filter.
     * Example: &platforms[]=android&platforms[]=linux. In this case platforms must be android or linux.
     *
     * @param QueryBuilder $query
     * @param array        $filterKey
     * @param $value
     * @param string $prefix
     *
     * @throws Exception
     *
     * @return QueryBuilder
     */
    private function getArrayValueFilter(QueryBuilder $query, array $filterKey, $value, string $prefix): QueryBuilder
    {
        $key = $filterKey['key'];

        // Check if this is an dateTime after/before filter (example: endDate[after] = "2022-04-11 00:00:00")
        if ($filterKey['compareDateTime']) {
            $query = $this->getDateTimeFilter($query, $key, $value, $prefix.$key);
        } elseif ($filterKey['multiple']) {
            // If the attribute we filter on is multiple=true
            $query = $this->getArrayValueMultipleFilter($query, $key, $value, $prefix);
        } else {
            $query->andWhere("LOWER($prefix$key.stringValue) IN (:$key)")
                ->setParameter($key, array_map('strtolower', $value));
        }

        return $query;
    }

    /**
     * Function that handles after and/or before dateTime filters. Adds to an existing QueryBuilder.
     *
     * @TODO: remove from & till as valid options, needed for 'old' gateway used by BISC. no longer after refactor!
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
        $subPrefix = 'dateTimeValue';
        if (in_array($key, ['dateCreated', 'dateModified'])) {
            $subPrefix = $key;
        }
        // todo: remove from
        if (!empty(array_intersect_key($value, array_flip(['from', 'after', 'strictly_after'])))) {
            $after = array_key_exists('from', $value) ? 'from' : (array_key_exists('strictly_after', $value) ? 'strictly_after' : 'after');
            $date = new DateTime($value[$after]);
            $operator = array_key_exists('strictly_after', $value) ? '>' : '>=';
            $query->andWhere($prefix.'.'.$subPrefix.' '.$operator.' :'.$key.'After')->setParameter($key.'After', $date->format('Y-m-d H:i:s'));
        }
        // todo: remove till
        if (!empty(array_intersect_key($value, array_flip(['till', 'before', 'strictly_before'])))) {
            $before = array_key_exists('till', $value) ? 'till' : (array_key_exists('strictly_before', $value) ? 'strictly_before' : 'before');
            $date = new DateTime($value[$before]);
            $operator = array_key_exists('strictly_before', $value) ? '<' : '<=';
            $query->andWhere($prefix.'.'.$subPrefix.' '.$operator.' :'.$key.'Before')->setParameter($key.'Before', $date->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    /**
     * Expands a QueryBuilder with an OR filter for an attribute with multiple=true.
     * Example: &platforms[]=android&platforms[]=linux. In this case platforms must be android or linux.
     * And attribute platforms is an array (multiple=true).
     *
     * @param QueryBuilder $query
     * @param string       $key
     * @param $value
     * @param string $prefix
     *
     * @return QueryBuilder
     */
    private function getArrayValueMultipleFilter(QueryBuilder $query, string $key, $value, string $prefix): QueryBuilder
    {
        $andWhere = '(';
        foreach ($value as $i => $item) {
            $andWhere .= "LOWER($prefix$key.stringValue) LIKE LOWER(:$key$i)";
            if ($i !== array_key_last($value)) {
                $andWhere .= ' OR ';
            }
        }
        $query->andWhere($andWhere.')');
        foreach ($value as $i => $item) {
            $query->setParameter("$key$i", "%$item%");
        }

        return $query;
    }

    /**
     * Expands a QueryBuilder with a 'normal' filter. This can be a filter for example a string or a datetime Attribute/Value.
     * Example query/filter: ?name=anExampleName.
     *
     * @param QueryBuilder $query
     * @param array        $filterKey
     * @param $value
     * @param string $prefix
     *
     * @return QueryBuilder
     */
    private function getNormalValueFilter(QueryBuilder $query, array $filterKey, $value, string $prefix): QueryBuilder
    {
        $key = $filterKey['key'];

        // If a date value is given, make sure we transform it into a dateTime string
        if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $value)) {
            $value = $value.' 00:00:00';
        }
        // Check the actual value (example: key = value)
        // NOTE: we always use stringValue to compare, but this works for other type of values, as long as we always set the stringValue in Value.php
        if ($filterKey['multiple']) {
            // If the attribute we filter on is multiple=true
            $query->andWhere("LOWER($prefix$key.stringValue) LIKE LOWER(:$key)")
                ->setParameter($key, "%$value%");
        } else {
            // Use LIKE here to allow %sometext% in query param filters (from front-end or through postman for example)
            $query->andWhere("LOWER($prefix$key.stringValue) LIKE LOWER(:$key)")
                ->setParameter($key, "$value");
        }

        return $query;
    }

    /**
     * Checks if we need to add orderBy to an existing QueryBuilder and if so actually adds orderBy to the query.
     * This depends on the given $order array and the allowed attributes to order on for the given Entity.
     *
     * @param QueryBuilder $query The existing QueryBuilder.
     * @param Entity $entity The Entity to check for allowed attributes to order on.
     * @param array $order The order array with the order query param used with the current get collection api-call.
     *
     * @return QueryBuilder
     */
    private function addOrderBy(QueryBuilder $query, Entity $entity, array $order): QueryBuilder
    {
        if (!empty($order)) {
            $orderCheck = $this->getOrderParameters($entity);

            if (in_array(array_keys($order)[0], $orderCheck) && in_array(array_values($order)[0], ['desc', 'asc'])) {
                $query = $this->getObjectEntityOrder($query, array_keys($order)[0], array_values($order)[0]);
            }
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
        //todo: we only check for the allowed keys/attributes to filter on, if this attribute is a dateTime (or date), we should also check if the value is a valid dateTime string?
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
            if (in_array($attribute->getType(), ['string', 'date', 'datetime', 'integer']) && $attribute->getSearchable()) {
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

        foreach ($Entity->getAttributes() as $attribute) {
            if (in_array($attribute->getType(), ['string', 'date', 'datetime', 'integer']) && $attribute->getSortable()) {
                $sortable[] = $prefix.$attribute->getName();
            }
            // todo: lets not allow ordering on subresources for now...
//            elseif ($attribute->getObject() && $level < 3 && !str_contains($prefix, $attribute->getName().'.')) {
//                $sortable = array_merge($sortable, $this->getOrderParameters($attribute->getObject(), $prefix.$attribute->getName().'.', $level + 1));
//            }
        }

        return $sortable;
    }
}
