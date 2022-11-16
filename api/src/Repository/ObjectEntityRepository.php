<?php

namespace App\Repository;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Ramsey\Uuid\Uuid;
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
     * @param Entity $entity  The Entity we are currently doing a get collection on.
     * @param array  $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param array  $order   An array with a key and value (ASC/DESC) used for ordering/sorting the result. See: getOrderParameters() for how to check for allowed fields to order.
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
        if (!empty($order)) {
            $order = $this->cleanOrderArray($order, $entity);
            $baseQuery = $this->addOrderBy($baseQuery, $order);
        }

        return [
            'objects' => $this->findByEntity($entity, [], [], $offset, $limit, $baseQuery),
            'total'   => $this->countByEntity($entity, [], $countQuery),
        ];
    }

    /**
     * Finds ObjectEntities using the given Entity and $filters array as filters. Can be ordered and allows pagination. Only $entity is required. Always use getFilterParameters() to check for allowed filters before using this function!
     *
     * @param Entity            $entity  The Entity we are currently doing a get collection on.
     * @param array             $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param array             $order   An array with a key and value (ASC/DESC) used for ordering/sorting the result. See: getOrderParameters() for how to check for allowed fields to order.
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

    /**
     * Returns an integer representing the total amount of results using the input to create a sql statement. $entity is required.
     *
     * @param Entity            $entity  The Entity we are currently doing a get collection on.
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
     * @param Entity $entity  The Entity we are currently doing a get collection on.
     * @param array  $filters An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param array  $order   An array with a key and value (ASC/DESC) used for ordering/sorting the result. See: getOrderParameters() for how to check for allowed fields to order.
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

        if (array_key_exists('search', $filters) || array_key_exists('_search', $filters)) {
            $search = array_key_exists('search', $filters) ? $filters['search'] : $filters['_search'];
            unset($filters['search']);
            unset($filters['_search']);
            $this->addSearchQuery($query, $entity, $search)->distinct();
        }

        if (!empty($filters)) {
            $filters = $this->cleanFiltersArray($filters, $entity);
            $this->buildQuery($query, $filters)->distinct();
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

//        $query->andWhere('o.organization IN (:organizations) OR o.organization IN (:parentOrganizations) OR o.organization = :defaultOrganization OR o.owner = :userId')
//            ->setParameter('userId', $userId)
//            ->setParameter('organizations', $organizations)
//            ->setParameter('parentOrganizations', $parentOrganizations)
//            ->setParameter('defaultOrganization', 'http://testdata-organization');

        if (!empty($order)) {
            $order = $this->cleanOrderArray($order, $entity);
            $this->addOrderBy($query, $order);
        }

        return $query;
    }

    /**
     * Gets and returns an array with all attributes names (as array key, dot notation) that have multiple set to true.
     * Works for subresources as well.
     *
     * @param Entity $entity The Entity we are currently doing a get collection on.
     * @param string $prefix The prefix of multiple attribute, used if we are getting multiple attributes of subresources.
     * @param int    $level  The depth level, if we are getting multiple attributes of subresource.subresource etc.
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
     * @param array  $filters The array of query params / filters.
     * @param Entity $entity  The Entity we are currently doing a get collection on.
     *
     * @return array A 'clean' array. And transformed array.
     */
    private function cleanFiltersArray(array $filters, Entity $entity): array
    {
        $result = [];
        $filterCheck = $this->getFilterParameters($entity);
        $multipleAttributes = $this->getMultipleAttributes($entity);

        // Handles valueScopeFilters. This will prevent duplicate filters and makes sure the user can not bypass authorization by using filters!
        $filters = $this->handleValueScopeFilters($filters);

        foreach ($filters as $key => $value) {
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
                if (!empty(array_intersect_key($value, array_flip(['after', 'before', 'strictly_after', 'strictly_before'])))) {
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
     * @param QueryBuilder $query  The existing QueryBuilder.
     * @param Entity       $entity The Entity we are currently doing a get collection on.
     * @param string       $search The value of the search Query.
     *
     * @return QueryBuilder
     */
    private function addSearchQuery(QueryBuilder $query, Entity $entity, string $search): QueryBuilder
    {
        if (count($entity->getSearchPartial()) !== 0) {
            $searchQueryOrWhere = $this->buildSearchQuery($query, $entity, $search);
            if ($searchQueryOrWhere === []) {
                return $query;
            }

            $searchQuery = '';
            foreach ($searchQueryOrWhere as $key => $searchQueryValue) {
                $searchQuery = array_key_first($searchQueryOrWhere) === $key ? $searchQueryValue : "$searchQuery OR $searchQueryValue";
            }

            $query->andWhere("($searchQuery)");
            $query->setParameter('search', '%'.strtolower($search).'%');
        }

        return $query;
    }

    /**
     * Expands a QueryBuilder with the correct left joins if the search query is used and search on subresources is configured.
     * Also makes sure we only check the values of the correct attribute when building the search query sql.
     * And lastly but most importantly builds and returns an array of orWhere statements to add to the query in addSearchQuery().
     *
     * @param QueryBuilder $query        The existing QueryBuilder.
     * @param Entity       $entity       The Entity we are currently doing a get collection on.
     * @param string       $search       The value of the search Query.
     * @param int          $level        The depth level, if we are doing a search on subresource.subresource etc.
     * @param string       $prefix       The prefix of the value for the search query we are adding.
     * @param string       $objectPrefix The prefix of the objectEntity for the search query we are adding, default = 'o'. ('o'= the main ObjectEntity, not a subresource)
     *
     * @return array returns an array of orWhere statements to add to the query in addSearchQuery().
     */
    private function buildSearchQuery(QueryBuilder $query, Entity $entity, string $search, int $level = 0, string $prefix = 'valueSearch', string $objectPrefix = 'o'): array
    {
        $searchQueryOrWhere = [];

        foreach ($entity->getSearchPartial() as $attribute) {
            $sqlFriendlyKey = $this->makeKeySqlFriendly($attribute->getName());
            $query->leftJoin("$objectPrefix.objectValues", $prefix.$sqlFriendlyKey);
            // If attribute type is object (we have $attribute->object) AND if that Entity has searchPartial attributes.
            if (!empty($attribute->getObject()) && count($attribute->getObject()->getSearchPartial()) !== 0) {
                $searchQueryOrWhere = array_merge($searchQueryOrWhere, $this->addSubresourceSearchQuery($query, $attribute, $sqlFriendlyKey, $search, $level, $prefix));
            } elseif (empty($attribute->getObject())) {
                // Only do this if type is not object!
                $searchQueryOrWhere[] = $this->addNormalSearchQuery($query, $attribute, $sqlFriendlyKey, $prefix);
            }
        }

        return $searchQueryOrWhere;
    }

    /**
     * Expands a QueryBuilder in the case the search query is used and search on subresources is configured.
     *
     * @param QueryBuilder $query          The existing QueryBuilder.
     * @param Attribute    $attribute      The Attribute configured to be searchable.
     * @param string       $sqlFriendlyKey The Attribute name. But made sql friendly for left joins with the makeKeySqlFriendly() function.
     * @param string       $search         The value of the search Query.
     * @param int          $level          The depth level, if we are doing a search on subresource.subresource etc.
     * @param string       $prefix         The prefix of the value for the search query we are adding.
     *
     * @return array returns an array of orWhere statements for a subresource to add to the already existing $searchQueryOrWhere array in buildSearchQuery().
     */
    private function addSubresourceSearchQuery(QueryBuilder $query, Attribute $attribute, string $sqlFriendlyKey, string $search, int $level, string $prefix): array
    {
        $query->leftJoin("$prefix$sqlFriendlyKey.objects", 'subObjectsSearch'.$sqlFriendlyKey.$level);
        $query->leftJoin('subObjectsSearch'.$sqlFriendlyKey.$level.'.objectValues', 'subValueSearch'.$sqlFriendlyKey.$level);

        return $this->buildSearchQuery(
            $query,
            $attribute->getObject(),
            $search,
            $level + 1,
            'subValueSearch'.$sqlFriendlyKey.$level,
            'subObjectsSearch'.$sqlFriendlyKey.$level
        );
    }

    /**
     * Expands a QueryBuilder so that we only check the values of the correct attribute when building the search query sql.
     *
     * @param QueryBuilder $query          The existing QueryBuilder.
     * @param Attribute    $attribute      The Attribute configured to be searchable.
     * @param string       $sqlFriendlyKey The attribute name. But made sql friendly for left joins with the makeKeySqlFriendly() function.
     * @param string       $prefix         The prefix of the value for the search query we are adding.
     *
     * @return string returns a single orWhere statements to add to the already existing $searchQueryOrWhere array in buildSearchQuery().
     */
    private function addNormalSearchQuery(QueryBuilder $query, Attribute $attribute, string $sqlFriendlyKey, string $prefix): string
    {
        // Make sure we only check the values of the correct attribute
        $query->leftJoin("$prefix$sqlFriendlyKey.attribute", $prefix.$sqlFriendlyKey.'Attribute');
        $query->andWhere($prefix.$sqlFriendlyKey."Attribute.name = :Key$sqlFriendlyKey")
            ->setParameter("Key$sqlFriendlyKey", $attribute->getName());

        return "LOWER($prefix$sqlFriendlyKey.stringValue) LIKE :search";
    }

    /**
     * Expands a QueryBuilder in the case that filters are used in createQuery().
     *
     * @param QueryBuilder $query        The existing QueryBuilder.
     * @param array        $filters      An array of filters, see: getFilterParameters() for how to check if filters are allowed and will work.
     * @param int          $level        The depth level, if we are filtering on subresource.subresource etc.
     * @param string       $prefix       The prefix of the value for the filter we are adding, default = 'value'.
     * @param string       $objectPrefix The prefix of the objectEntity for the filter we are adding, default = 'o'. ('o'= the main ObjectEntity, not a subresource)
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function buildQuery(QueryBuilder $query, array $filters, int $level = 0, string $prefix = 'value', string $objectPrefix = 'o'): QueryBuilder
    {
        foreach ($filters as $key => $value) {
            $filterKey = $this->clearFilterKey($key);

            if (substr($filterKey['key'], 0, 1) == '_' || $filterKey['key'] == 'id') {
                // If the filter starts with _ or == id we need to handle this filter differently
                $query = $this->getObjectEntityFilter($query, $filterKey['key'], $value, $objectPrefix);
            } else {
                $query->leftJoin("$objectPrefix.objectValues", $prefix.$filterKey['sqlFriendlyKey']);
                if (is_array($value) && !$filterKey['arrayValue']) {
                    // If $value is an array we need to check filters on a subresource (example: subresource.key = something)
                    $query = $this->buildSubresourceQuery($query, $filterKey['sqlFriendlyKey'], $value, $level, $prefix);
                } else {
                    $query = $this->buildFilterQuery($query, $filterKey, $value, $prefix);
                }
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
            'sqlFriendlyKey'  => $this->makeKeySqlFriendly($key),
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
        // todo implement LIKE operator for (some of) these filters? just like we do for normal filters? see getNormalValueFilter() function.
        $operator = '=';
        $allowedCompareNullKeys = ['_externalId', '_uri', '_self', '_organization', '_application'];
        if (is_string($value) && in_array($key, $allowedCompareNullKeys) && (strtoupper($value) === 'IS NULL' || strtoupper($value) === 'IS NOT NULL')) {
            // todo IS NULL does not work for subresources... only for the parent/main object
            $operator = '';
            $value = strtoupper($value);
        }
        switch ($key) {
            case 'id':
                $query->andWhere("($prefix.id $operator :$prefix$key OR $prefix.externalId $operator :$prefix$key)")->setParameter("$prefix$key", $value);
                break;
            case '_id':
                $query->andWhere("$prefix.id $operator :{$prefix}id")->setParameter("{$prefix}id", $value);
                break;
            case '_externalId':
                !empty($operator) ?
                    $query->andWhere("$prefix.externalId $operator :{$prefix}externalId")->setParameter("{$prefix}externalId", $value) :
                    $query->andWhere("$prefix.externalId $value");
                break;
            case '_uri':
                !empty($operator) ?
                    $query->andWhere("$prefix.uri $operator :{$prefix}uri")->setParameter("{$prefix}uri", $value) :
                    $query->andWhere("$prefix.uri $value");
                break;
            case '_self':
                !empty($operator) ?
                    $query->andWhere("$prefix.self $operator :{$prefix}self")->setParameter("{$prefix}self", $value) :
                    $query->andWhere("$prefix.self $value");
                break;
            case '_organization':
                !empty($operator) ?
                    $query->andWhere("$prefix.organization $operator :{$prefix}organization")->setParameter("{$prefix}organization", $value) :
                    $query->andWhere("$prefix.organization $value");
                break;
            case '_application':
                !empty($operator) ?
                    $query->andWhere("$prefix.application $operator :{$prefix}application")->setParameter("{$prefix}application", $value) :
                    $query->andWhere("$prefix.application $value");
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
     * @param QueryBuilder $query          The existing QueryBuilder.
     * @param string       $sqlFriendlyKey The key of the filter. But one that is sql friendly for using in left joins. see makeKeySqlFriendly() function.
     * @param array        $value          The value of the filter.
     * @param int          $level          The depth level, if we are filtering on subresource.subresource etc.
     * @param string       $prefix         The prefix of the value for the filter we are adding.
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function buildSubresourceQuery(QueryBuilder $query, string $sqlFriendlyKey, array $value, int $level, string $prefix): QueryBuilder
    {
        // If $value is an array we need to check filters on a subresource (example: subresource.key = something)
        $query->leftJoin("$prefix$sqlFriendlyKey.objects", 'subObjects'.$sqlFriendlyKey.$level);
        $query->leftJoin('subObjects'.$sqlFriendlyKey.$level.'.objectValues', 'subValue'.$sqlFriendlyKey.$level);

        return $this->buildQuery(
            $query,
            $value,
            $level + 1,
            'subValue'.$sqlFriendlyKey.$level,
            'subObjects'.$sqlFriendlyKey.$level
        );
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
        $sqlFriendlyKey = $filterKey['sqlFriendlyKey'];

        // Make sure we only check the values of the correct attribute
        $query->leftJoin("$prefix$sqlFriendlyKey.attribute", $prefix.$sqlFriendlyKey.'Attribute');
        $query->andWhere($prefix.$sqlFriendlyKey."Attribute.name = :Key$sqlFriendlyKey")
            ->setParameter("Key$sqlFriendlyKey", $key);

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
        $sqlFriendlyKey = $filterKey['sqlFriendlyKey'];

        // Check if this is an dateTime after/before filter (example: endDate[after] = "2022-04-11 00:00:00")
        if ($filterKey['compareDateTime']) {
            $query = $this->getDateTimeFilter($query, $sqlFriendlyKey, $value, $prefix.$sqlFriendlyKey);
        } elseif ($filterKey['multiple']) {
            // If the attribute we filter on is multiple=true
            $query = $this->getArrayValueMultipleFilter($query, $sqlFriendlyKey, $value, $prefix);
        } else {
            $query->andWhere("LOWER($prefix$sqlFriendlyKey.stringValue) IN (:$sqlFriendlyKey)")
                ->setParameter($sqlFriendlyKey, array_map('strtolower', $value));
        }

        return $query;
    }

    /**
     * Function that handles after and/or before dateTime filters. Adds to an existing QueryBuilder.
     *
     * @param QueryBuilder $query          The existing QueryBuilder.
     * @param string       $sqlFriendlyKey
     * @param $value
     * @param string $prefix
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function getDateTimeFilter(QueryBuilder $query, string $sqlFriendlyKey, $value, string $prefix = 'o'): QueryBuilder
    {
        $subPrefix = 'dateTimeValue';
        if (in_array($sqlFriendlyKey, ['dateCreated', 'dateModified'])) {
            $subPrefix = $sqlFriendlyKey;
        }
        if (!empty(array_intersect_key($value, array_flip(['after', 'strictly_after'])))) {
            $after = array_key_exists('strictly_after', $value) ? 'strictly_after' : 'after';
            $date = new DateTime($value[$after]);
            $operator = array_key_exists('strictly_after', $value) ? '>' : '>=';
            $query->andWhere("$prefix.$subPrefix $operator :$prefix$subPrefix{$sqlFriendlyKey}After")->setParameter("$prefix$subPrefix{$sqlFriendlyKey}After", $date->format('Y-m-d H:i:s'));
        }
        if (!empty(array_intersect_key($value, array_flip(['before', 'strictly_before'])))) {
            $before = array_key_exists('strictly_before', $value) ? 'strictly_before' : 'before';
            $date = new DateTime($value[$before]);
            $operator = array_key_exists('strictly_before', $value) ? '<' : '<=';
            $query->andWhere("$prefix.$subPrefix $operator :$prefix$subPrefix{$sqlFriendlyKey}Before")->setParameter("$prefix$subPrefix{$sqlFriendlyKey}Before", $date->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    /**
     * Expands a QueryBuilder with an OR filter for an attribute with multiple=true.
     * Example: &platforms[]=android&platforms[]=linux. In this case platforms must be android or linux.
     * And attribute platforms is an array (multiple=true).
     *
     * @param QueryBuilder $query
     * @param string       $sqlFriendlyKey
     * @param $value
     * @param string $prefix
     *
     * @return QueryBuilder
     */
    private function getArrayValueMultipleFilter(QueryBuilder $query, string $sqlFriendlyKey, $value, string $prefix): QueryBuilder
    {
        $andWhere = '(';
        foreach ($value as $i => $item) {
            $andWhere .= "LOWER($prefix$sqlFriendlyKey.stringValue) LIKE LOWER(:$sqlFriendlyKey$i)";
            if ($i !== array_key_last($value)) {
                $andWhere .= ' OR ';
            }
        }
        $query->andWhere($andWhere.')');
        foreach ($value as $i => $item) {
            $query->setParameter("$sqlFriendlyKey$i", "%,$item%");
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
        $sqlFriendlyKey = $filterKey['sqlFriendlyKey'];

        // If a date value is given, make sure we transform it into a dateTime string
        if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $value)) {
            $value = $value.' 00:00:00';
        }
        // Check the actual value (example: key = value)
        // NOTE: we always use stringValue to compare, but this works for other type of values, as long as we always set the stringValue in Value.php
        if ($filterKey['multiple']) {
            // If the attribute we filter on is multiple=true
            $query->andWhere("LOWER($prefix$sqlFriendlyKey.stringValue) LIKE LOWER(:$sqlFriendlyKey)")
                ->setParameter($sqlFriendlyKey, "%,$value%");
        } elseif ($value === 'IS NULL' || $value === 'IS NOT NULL') {
            // Allow to filter on IS NULL and IS NOT NULL
            $query->andWhere("$prefix$sqlFriendlyKey.stringValue $value");
        } else {
            // Use LIKE here to allow %sometext% in query param filters (from front-end or through postman for example)
            $query->andWhere("LOWER($prefix$sqlFriendlyKey.stringValue) LIKE LOWER(:$sqlFriendlyKey)")
                ->setParameter($sqlFriendlyKey, "$value");
        }

        return $query;
    }

    /**
     * Replace dot order _ into . (symfony query param thing) and transform dot order into an array with recursiveFilterSplit().
     *
     * @param array  $order  The order array with the order query param used with the current get collection api-call.
     * @param Entity $entity The Entity we are currently doing a get collection on.
     *
     * @return array A 'clean' array. And transformed array.
     */
    private function cleanOrderArray(array $order, Entity $entity): array
    {
        $result = [];
        $orderCheck = $this->getOrderParameters($entity);

        $key = array_keys($order)[0];
        $value = strtoupper(array_values($order)[0]);

        if (in_array($key, $orderCheck) && in_array($value, ['DESC', 'ASC'])) {
            $result = $this->recursiveFilterSplit(explode('.', $key), $value, $result);
        }

        return $result;
    }

    /**
     * Checks if we need to add orderBy to an existing QueryBuilder and if so actually adds orderBy to the query.
     * This depends on the given $order array and the allowed attributes to order on for the given Entity.
     *
     * @param QueryBuilder $query        The existing QueryBuilder.
     * @param array        $order        The order array with the order query param used with the current get collection api-call.
     * @param int          $level        The depth level, if we are ordering on subresource.subresource etc.
     * @param string       $prefix       The prefix of the value for the order we are adding.
     * @param string       $objectPrefix The prefix of the objectEntity for the order we are adding, default = 'o'. ('o'= the main ObjectEntity, not a subresource)
     *
     * @throws Exception
     *
     * @return QueryBuilder
     */
    private function addOrderBy(QueryBuilder $query, array $order, int $level = 0, string $prefix = 'valueOrderBy', string $objectPrefix = 'o'): QueryBuilder
    {
        // Important note: $prefix here = 'valueOrderBy', for filters we use $prefix = 'value'. This is to make sure the leftJoins do not clash.
        $key = array_keys($order)[0];
        $value = array_values($order)[0];

        if (substr($key, 0, 1) == '_') {
            // If order starts with _ we need to handle this order differently
            $query = $this->getObjectEntityOrder($query, $key, $value, $objectPrefix);
        } else {
            $sqlFriendlyKey = $this->makeKeySqlFriendly($key);
            $query->leftJoin("$objectPrefix.objectValues", $prefix.$sqlFriendlyKey);
            if (is_array($value)) {
                // If $value is an array we need to order on a subresource (example: subresource.key = something)
                $query = $this->addSubresourceOrderBy($query, $sqlFriendlyKey, $value, $level, $prefix);
            } else {
                $query = $this->buildOrderQuery($query, $sqlFriendlyKey, $key, $value, $prefix);
            }
        }

        return $query;
    }

    /**
     * Function that handles special orderBy options starting with _
     * Adds to an existing QueryBuilder.
     *
     * @param QueryBuilder $query  The existing QueryBuilder.
     * @param string       $key    The order[$key] used in the order query param.
     * @param string       $value  The value used with the query param, DESC or ASC.
     * @param string       $prefix The prefix of the value for the order we are adding.
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function getObjectEntityOrder(QueryBuilder $query, string $key, string $value, string $prefix = 'o'): QueryBuilder
    {
        switch ($key) {
            case '_dateCreated':
                $query->orderBy("$prefix.dateCreated", $value);
                break;
            case '_dateModified':
                $query->orderBy("$prefix.dateModified", $value);
                break;
            default:
                $query->orderBy("$prefix.$key", $value);
                break;
        }

        return $query;
    }

    /**
     * Expands a QueryBuilder in the case order on a subresource is used in the order query (example: subresource.key = something).
     *
     * @param QueryBuilder $query          The existing QueryBuilder.
     * @param string       $sqlFriendlyKey The key of the order. But made sql friendly for left joins with the makeKeySqlFriendly() function.
     * @param array        $value          The value of the order.
     * @param int          $level          The depth level, if we are ordering on subresource.subresource etc.
     * @param string       $prefix         The prefix of the value for the order we are adding.
     *
     * @throws Exception
     *
     * @return QueryBuilder The QueryBuilder.
     */
    private function addSubresourceOrderBy(QueryBuilder $query, string $sqlFriendlyKey, array $value, int $level, string $prefix): QueryBuilder
    {
        // If $value is an array we need to order on a subresource (example: subresource.key = something)
        $query->leftJoin("$prefix$sqlFriendlyKey.objects", 'subObjectsOrderBy'.$sqlFriendlyKey.$level);
        $query->leftJoin('subObjectsOrderBy'.$sqlFriendlyKey.$level.'.objectValues', 'subValueOrderBy'.$sqlFriendlyKey.$level);

        return $this->addOrderBy(
            $query,
            $value,
            $level + 1,
            'subValueOrderBy'.$sqlFriendlyKey.$level,
            'subObjectsOrderBy'.$sqlFriendlyKey.$level
        );
    }

    /**
     * Expands a QueryBuilder with the correct orderBy, using the given input and prefix.
     *
     * @param QueryBuilder $query          The existing QueryBuilder.
     * @param string       $sqlFriendlyKey The order[$key] used in the order query param. But made sql friendly for left joins with the makeKeySqlFriendly() function.
     * @param string       $key            The order[$key] used in the order query param.
     * @param string       $value          The value used with the query param, DESC or ASC.
     * @param string       $prefix         The prefix of the value for the order we are adding.
     *
     * @return QueryBuilder
     */
    private function buildOrderQuery(QueryBuilder $query, string $sqlFriendlyKey, string $key, string $value, string $prefix): QueryBuilder
    {
        // Make sure this attribute is in the select, or we are not allowed to order on it.
        $query->addSelect($prefix.$sqlFriendlyKey.'.stringValue');

        // Make sure we only check the values of the correct attribute
        $query->leftJoin("$prefix$sqlFriendlyKey.attribute", $prefix.$sqlFriendlyKey.'Attribute');
        $query->andWhere($prefix.$sqlFriendlyKey."Attribute.name = :Key$sqlFriendlyKey")
            ->setParameter("Key$sqlFriendlyKey", $key);

        $query->orderBy("$prefix$sqlFriendlyKey.stringValue", $value);

        return $query;
    }

    /**
     * A function that replaces special characters that would otherwise break sql left joins.
     *
     * @param string $key
     *
     * @return void
     */
    private function makeKeySqlFriendly(string $key): string
    {
        // todo, probably add more special characters to replace...
        return str_replace('-', 'Dash', $key);
    }

    /**
     * Gets and returns an array with the allowed filters on an Entity (including its subEntities / sub-filters).
     *
     * @param Entity $Entity The Entity we are currently doing a get collection on.
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
            $prefix.'id', $prefix.'_id', $prefix.'_externalId', $prefix.'_uri', $prefix.'_self', $prefix.'_organization',
            $prefix.'_application', $prefix.'_dateCreated', $prefix.'_dateModified', $prefix.'_mapping',
        ];

        foreach ($Entity->getAttributes() as $attribute) {
            if (in_array($attribute->getType(), ['string', 'date', 'datetime', 'integer', 'float', 'number', 'boolean']) && $attribute->getSearchable()) {
                $filters[] = $prefix.$attribute->getName();
            } elseif ($attribute->getObject() && $level < 3 && !str_contains($prefix, $attribute->getName().'.')) {
                $attribute->getSearchable() && $filters[] = $prefix.$attribute->getName();
                $filters = array_merge($filters, $this->getFilterParameters($attribute->getObject(), $prefix.$attribute->getName().'.', $level + 1));
            }
        }

        return $filters;
    }

    /**
     * Gets and returns an array with the allowed sortable attributes on an Entity (including its subEntities).
     *
     * @param Entity $Entity The Entity we are currently doing a get collection on.
     * @param string $prefix
     * @param int    $level
     *
     * @return array The array with allowed attributes to sort by.
     */
    public function getOrderParameters(Entity $Entity, string $prefix = '', int $level = 1): array
    {
        // defaults
        $sortable = [$prefix.'_dateCreated', $prefix.'_dateModified'];

        foreach ($Entity->getAttributes() as $attribute) {
            if (in_array($attribute->getType(), ['string', 'date', 'datetime', 'integer', 'float', 'number']) && $attribute->getSortable()) {
                $sortable[] = $prefix.$attribute->getName();
            } elseif ($attribute->getObject() && $level < 3 && !str_contains($prefix, $attribute->getName().'.')) {
                $sortable = array_merge($sortable, $this->getOrderParameters($attribute->getObject(), $prefix.$attribute->getName().'.', $level + 1));
            }
        }

        return $sortable;
    }

    /**
     * Finds object entities on there external or internal id.
     *
     * @param string $identifier
     *
     * @throws NonUniqueResultException
     *
     * @return ObjectEntity The found object entity
     */
    public function findByAnyId(string $identifier): ?ObjectEntity
    {
        $query = $this->createQueryBuilder('o')
            ->leftJoin('o.synchronizations', 's')
            ->where('s.sourceId = :identifier')
            ->orWhere('o.externalId = :identifier')
            ->setParameter('identifier', $identifier);

        if (Uuid::isValid($identifier)) {
            $query->orWhere('o.id = :identifier');
        }

        return $query->getQuery()->getOneOrNullResult();
    }
}
