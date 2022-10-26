<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\File;
use App\Entity\Log;
use App\Entity\ObjectEntity;
use App\Entity\RequestLog;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class ResponseService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private AuthorizationService $authorizationService;
    private SessionInterface $session;
    private Security $security;
    private CacheInterface $cache;
    // todo: maybe start using one or more array properties to save data in, so we don't have to pass down all this...
    // todo: ...data in RenderResult (and other function after that, untill we come back to RenderResult again, because of 'recursion')
    // todo: other examples we could use this for when we cleanup this service: $fields, $extend, $acceptType, $skipAuthCheck
    // todo: use this as example (checking for $level when setting this data only once is very important):
    private array $xCommongatewayMetadata;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, AuthorizationService $authorizationService, SessionInterface $session, Security $security, CacheInterface $cache)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->authorizationService = $authorizationService;
        $this->session = $session;
        $this->security = $security;
        $this->cache = $cache;
    }

    // todo remove responseService from the ObjectEntityService, so we can use the ObjectEntityService->createSelf() function here
    /**
     * Returns the string used for {at sign}id or self->href for the given objectEntity. This function will use the ObjectEntity->Entity
     * to first look for the get item endpoint and else use the Entity route or name to generate the correct string.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return string
     */
    public function createSelf(ObjectEntity $objectEntity): string
    {
        // We need to persist if this is a new ObjectEntity in order to set and getId to generate the self...
        $this->em->persist($objectEntity);
        $endpoints = $this->em->getRepository('App:Endpoint')->findGetItemByEntity($objectEntity->getEntity());
        if (count($endpoints) > 0 && $endpoints[0] instanceof Endpoint) {
            $pathArray = $endpoints[0]->getPath();
            $foundId = in_array('{id}', $pathArray) ? $pathArray[array_search('{id}', $pathArray)] = $objectEntity->getId() :
                (in_array('{uuid}', $pathArray) ? $pathArray[array_search('{uuid}', $pathArray)] = $objectEntity->getId() : false);
            if ($foundId !== false) {
                $path = implode('/', $pathArray);

                return '/api/'.$path;
            }
        }

        return '/api'.($objectEntity->getEntity()->getRoute() ?? $objectEntity->getEntity()->getName()).'/'.$objectEntity->getId();
    }

    // todo remove responseService from the ObjectEntityService, so we can use the ObjectEntityService->checkOwner() function here
    // todo if we do this^ maybe move the getDateRead function to ObjectEntityService as well
    private function checkOwner(ObjectEntity $result): bool
    {
        // TODO: what if somehow the owner of this ObjectEntity is null? because of ConvertToGateway ObjectEntities for example?
        $user = $this->security->getUser();

        if ($user && $result->getOwner() === $user->getUserIdentifier()) {
            return true;
        }

        return false;
    }

    /**
     * Get the last date read for the given ObjectEntity, for the current user. (uses sql to search in logs).
     *
     * @param ObjectEntity $objectEntity
     *
     * @return DateTimeInterface|null
     */
    private function getDateRead(ObjectEntity $objectEntity): ?DateTimeInterface
    {
        $user = $this->security->getUser();
        if ($user === null) {
            return null;
        }

        // First, check if there is an Unread object for this Object+User. If so, return null.
        $unreads = $this->em->getRepository('App:Unread')->findBy(['object' => $objectEntity, 'userId' => $user->getUserIdentifier()]);
        if (!empty($unreads)) {
            return null;
        }

        // Use sql to find last get item log of the current user for the given object.
        $logs = $this->em->getRepository('App:Log')->findDateRead($objectEntity->getId()->toString(), $user->getUserIdentifier());

        if (!empty($logs) and $logs[0] instanceof Log) {
            return $logs[0]->getDateCreated();
        }

        return null;
    }

    /**
     * Filters fields that should not be displayed.
     *
     * @param array        $response      The full response
     * @param ObjectEntity $result        The objectEntity that contains the results
     * @param bool         $skipAuthCheck Whether the authorization should be checked
     *
     * @return array The resulting response
     */
    public function filterResult(array $response, ObjectEntity $result, bool $skipAuthCheck): array
    {
        return array_filter($response, function ($value, $key) use ($result, $skipAuthCheck) {
            if (str_starts_with($key, '@') || $key == 'id') {
                return true;
            }
            $attribute = $this->em->getRepository('App:Attribute')->findOneBy(['name' => $key, 'entity' => $result->getEntity()]);
            if (!$skipAuthCheck && !empty($attribute)) {
                try {
                    if (!$this->checkOwner($result)) {
                        $this->authorizationService->checkAuthorization(['attribute' => $attribute, 'value' => $value]);
                    }
                } catch (AccessDeniedException $exception) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Renders the result for a ObjectEntity that will be used for the response after a successful api call.
     *
     * @param ObjectEntity $result
     * @param array|null   $fields
     * @param array|null   $extend
     * @param string       $acceptType
     * @param bool         $skipAuthCheck
     * @param bool         $flat          todo: $flat and $acceptType = 'json' should have the same result, so remove $flat?
     * @param int          $level
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array|string[]|string Only returns a string if $level is higher than 3 and acceptType is not jsonld.
     */
    public function renderResult(ObjectEntity $result, ?array $fields, ?array $extend, string $acceptType = 'jsonld', bool $skipAuthCheck = false, bool $flat = false, int $level = 0)
    {
        $response = [];
        if ($level === 0) {
            $this->xCommongatewayMetadata = [];
            if (is_array($extend) && array_key_exists('x-commongateway-metadata', $extend)) {
                $this->xCommongatewayMetadata = $extend['x-commongateway-metadata'];
                unset($extend['x-commongateway-metadata']);
                if (empty($extend)) {
                    $extend = null;
                }
            }
        }

        if (
            $result->getEntity()->getGateway() !== null &&
            ($result->getEntity()->getGateway()->getType() == 'soap' ||
                $result->getEntity()->getGateway()->getType() == 'xml' ||
                $result->getEntity()->getGateway()->getAuth() == 'vrijbrp-jwt')
        ) {
            return $response;
        }

        $user = $this->security->getUser();
        $userId = $user !== null ? $user->getUserIdentifier() : 'anonymous';
        $item = $this->cache->getItem(
            'object_'
            .base64_encode(
                $result->getId()
                .'userId_'.$userId
                .'acceptType_'.$acceptType
                .'level_'.$level
                .'fields_'.http_build_query($fields ?? [], '', ',')
                .'extend_'.http_build_query($extend ?? [], '', ',')
                .'xCommongatewayMetadata_'.http_build_query($this->xCommongatewayMetadata ?? [], '', ',')
            )
        );
        // Todo: what to do with dateRead and caching... this works for now:
        if ($item->isHit() && !isset($this->xCommongatewayMetadata['dateRead']) && !isset($this->xCommongatewayMetadata['all'])) {
//            var_dump('FromCache: '.$result->getId().'userId_'.$userId.'acceptType_'.$acceptType.'level_'.$level.'fields_'.http_build_query($fields ?? [], '', ',').'extend_'.http_build_query($extend ?? [], '', ',').'xCommongatewayMetadata_'.http_build_query($this->xCommongatewayMetadata ?? [], '', ','));
            return $this->filterResult($item->get(), $result, $skipAuthCheck);
        }
        $item->tag('object_'.base64_encode($result->getId()->toString()));
        $item->tag('object_userId_'.base64_encode($userId));

        // Make sure to break infinite render loops! ('New' MaxDepth)
        if ($level > 3) {
            if ($acceptType === 'jsonld') {
                return [
                    '@id' => $result->getSelf() ?? '/api'.($result->getEntity()->getRoute() ?? $result->getEntity()->getName()).'/'.$result->getId(),
                ];
            }

            return $result->getSelf() ?? '/api'.($result->getEntity()->getRoute() ?? $result->getEntity()->getName()).'/'.$result->getId();
        }

        // todo: do we still want to do this if we have BL for syncing objects?
        // Lets start with the external result
        if ($result->getEntity()->getGateway() && $result->getEntity()->getEndpoint()) {
            if (!empty($result->getExternalResult())) {
                $response = array_merge($response, $result->getExternalResult());
            } elseif (!$result->getExternalResult() === [] && $this->commonGroundService->isResource($result->getExternalResult())) {
                $response = array_merge($response, $this->commonGroundService->getResource($result->getExternalResult()));
            } elseif ($this->commonGroundService->isResource($result->getUri())) {
                $response = array_merge($response, $this->commonGroundService->getResource($result->getUri()));
            }

            // Only render the attributes that are available for this Entity (filters out unwanted properties from external results)
            if (!is_null($result->getEntity()->getAvailableProperties() || !empty($fields))) {
                $response = array_filter($response, function ($propertyName) use ($result, $fields) {
                    $attTypeObject = false;
                    if ($attribute = $result->getEntity()->getAttributeByName($propertyName)) {
                        $attTypeObject = $attribute->getType() === 'object';
                    }

                    return
                        (empty($fields) || array_key_exists($propertyName, $fields)) &&
                        (!$attTypeObject || $attribute->getExtend() || (!empty($extend) && (array_key_exists('all', $extend) || array_key_exists($propertyName, $extend)))) &&
                        (empty($result->getEntity()->getAvailableProperties()) || in_array($propertyName, $result->getEntity()->getAvailableProperties()));
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        // Let overwrite the id with the gateway id
        $response['id'] = $result->getId()->toString(); // todo: remove this line of code if $flat is removed

        // Let get the internal results
        $renderValues = $this->renderValues($result, $fields, $extend, $acceptType, $skipAuthCheck, $flat, $level);
        $response = array_merge($response, $renderValues['renderValuesResponse']);

        // Lets sort the result alphabeticly
        ksort($response);

        // Lets skip the pritty styff when dealing with a flat object
        // todo: $flat and $acceptType = 'json' should have the same result, so remove $flat?
        if ($flat) {
            $item->set($response);
            $this->cache->save($item);

            return $response;
        }

        $response = $this->handleAcceptType($result, $fields, $extend, $acceptType, $level, $response, $renderValues['renderValuesEmbedded']);

        // Todo: what to do with dateRead and caching... this works for now:
        if (!isset($this->xCommongatewayMetadata['dateRead']) && !isset($this->xCommongatewayMetadata['all'])) {
            $item->set($response);
            $this->cache->save($item);
        }

        return $response;
    }

    /**
     * Returns a response array for renderResult function. This response is different depending on the acceptType.
     *
     * @param ObjectEntity $result
     * @param array|null   $fields
     * @param array|null   $extend
     * @param string       $acceptType
     * @param int          $level
     * @param array        $response
     * @param array        $embedded
     *
     * @return array
     */
    private function handleAcceptType(ObjectEntity $result, ?array $fields, ?array $extend, string $acceptType, int $level, array $response, array $embedded): array
    {
        $gatewayContext = [];
        switch ($acceptType) {
            case 'jsonld':
                $jsonLd = $this->handleJsonLd($result, $fields, $extend, $level, $response, $embedded);
                $gatewayContext = $jsonLd['gatewayContext'];
                $embedded = $jsonLd['embedded'];
                break;
            case 'jsonhal':
                $jsonHal = $this->handleJsonHal($result, $fields, $extend, $level, $response, $embedded);
                $gatewayContext = $jsonHal['gatewayContext'];
                $embedded = $jsonHal['embedded'];
                break;
            case 'json':
            default:
                if ($this->xCommongatewayMetadata !== []) {
                    $gatewayContext['x-commongateway-metadata'] = $this->handleXCommongatewayMetadata($result, $fields, $extend, $level, $response);
                }
                if (!empty($embedded)) {
                    $embedded['embedded'] = $embedded;
                }
                break;
        }

        $gatewayContext['id'] = $result->getId();

        return $gatewayContext + $response + $embedded;
    }

    /**
     * Returns a response array for renderResult function. This response conforms to the acceptType jsonLd.
     *
     * @param ObjectEntity $result
     * @param array|null   $fields
     * @param array|null   $extend
     * @param int          $level
     * @param array        $response
     * @param array        $embedded
     *
     * @return array
     */
    private function handleJsonLd(ObjectEntity $result, ?array $fields, ?array $extend, int $level, array $response, array $embedded): array
    {
        $gatewayContext['@id'] = $result->getSelf() ?? '/api'.($result->getEntity()->getRoute() ?? $result->getEntity()->getName()).'/'.$result->getId();
        $gatewayContext['@type'] = ucfirst($result->getEntity()->getName());
        $gatewayContext['@context'] = '/contexts/'.ucfirst($result->getEntity()->getName());
        $gatewayContext['@dateCreated'] = $result->getDateCreated();
        $gatewayContext['@dateModified'] = $result->getDateModified();
        if ($level === 0) {
            $this->addToMetadata($gatewayContext, 'dateRead', $result, '@dateRead');
        }
        $gatewayContext['@owner'] = $result->getOwner();
        $gatewayContext['@organization'] = $result->getOrganization();
        $gatewayContext['@application'] = $result->getApplication() !== null ? $result->getApplication()->getId() : null;
        $gatewayContext['@uri'] = $result->getUri();
        $gatewayContext['@gateway/id'] = $result->getExternalId() ?? (array_key_exists('id', $response) ? $response['id'] : null);
        if (array_key_exists('@type', $response)) {
            $gatewayContext['@gateway/type'] = $response['@type'];
        }
        if (array_key_exists('@context', $response)) {
            $gatewayContext['@gateway/context'] = $response['@context'];
        }
        $gatewayContext['@synchronizations'] = $this->addObjectSyncsData($result);
        if (is_array($extend)) {
            $gatewayContext['@extend'] = $extend;
        }
        if (is_array($fields)) {
            $gatewayContext['@fields'] = $fields;
        }
        $gatewayContext['@level'] = $level;
        if (!empty($embedded)) {
            $embedded['@embedded'] = $embedded;
        }

        return [
            'gatewayContext' => $gatewayContext,
            'embedded'       => $embedded,
        ];
    }

    /**
     * Returns a response array for renderResult function. This response conforms to the acceptType jsonHal.
     *
     * @param ObjectEntity $result
     * @param array|null   $fields
     * @param array|null   $extend
     * @param int          $level
     * @param array        $response
     * @param array        $embedded
     *
     * @return array
     */
    private function handleJsonHal(ObjectEntity $result, ?array $fields, ?array $extend, int $level, array $response, array $embedded): array
    {
        $gatewayContext['_links']['self']['href'] = $result->getSelf() ?? '/api'.($result->getEntity()->getRoute() ?? $result->getEntity()->getName()).'/'.$result->getId();
        $gatewayContext['_metadata'] = [
            '_type'         => ucfirst($result->getEntity()->getName()),
            '_context'      => '/contexts/'.ucfirst($result->getEntity()->getName()),
            '_dateCreated'  => $result->getDateCreated(),
            '_dateModified' => $result->getDateModified(),
        ];
        if ($level === 0) {
            $this->addToMetadata($gatewayContext['_metadata'], 'dateRead', $result, '_dateRead');
        }
        $gatewayContext['_metadata'] = array_merge($gatewayContext['_metadata'], [
            '_owner'        => $result->getOwner(),
            '_organization' => $result->getOrganization(),
            '_application'  => $result->getApplication() !== null ? $result->getApplication()->getId() : null,
            '_uri'          => $result->getUri(),
            '_gateway/id'   => $result->getExternalId() ?? (array_key_exists('id', $response) ? $response['id'] : null),
        ]);
        if (array_key_exists('@type', $response)) {
            $gatewayContext['_metadata']['_gateway/type'] = $response['@type'];
        }
        if (array_key_exists('@context', $response)) {
            $gatewayContext['_metadata']['_gateway/context'] = $response['@context'];
        }
        $gatewayContext['_metadata']['_synchronizations'] = $this->addObjectSyncsData($result);
        if (is_array($extend)) {
            $gatewayContext['_metadata']['_extend'] = $extend;
        }
        if (is_array($fields)) {
            $gatewayContext['_metadata']['_fields'] = $fields;
        }
        $gatewayContext['_metadata']['_level'] = $level;
        if (!empty($embedded)) {
            $embedded['_embedded'] = $embedded;
        }

        return [
            'gatewayContext' => $gatewayContext,
            'embedded'       => $embedded,
        ];
    }

    /**
     * Returns a response array for renderResult function.
     * This function is called and used to show metadata for AcceptTypes json or 'default' when the extend query param contains x-commongateway-metadata.
     *
     * @param ObjectEntity $result
     * @param array|null   $fields
     * @param array|null   $extend
     * @param int          $level
     * @param array        $response
     *
     * @return array
     */
    private function handleXCommongatewayMetadata(ObjectEntity $result, ?array $fields, ?array $extend, int $level, array $response): array
    {
        $metadata = [];
        $this->addToMetadata(
            $metadata,
            'self',
            $result->getSelf() ?? $result->setSelf($this->createSelf($result))->getSelf()
        );
        $this->addToMetadata($metadata, 'type', ucfirst($result->getEntity()->getName()));
        $this->addToMetadata($metadata, 'context', '/contexts/'.ucfirst($result->getEntity()->getName()));
        $this->addToMetadata($metadata, 'dateCreated', $result->getDateCreated());
        $this->addToMetadata($metadata, 'dateModified', $result->getDateModified());
        if ($level === 0) {
            $this->addToMetadata($metadata, 'dateRead', $result);
        }
        $this->addToMetadata($metadata, 'owner', $result->getOwner());
        $this->addToMetadata($metadata, 'organization', $result->getOrganization());
        $this->addToMetadata(
            $metadata,
            'application',
            $result->getApplication() !== null ? $result->getApplication()->getId() : null
        );
        $this->addToMetadata($metadata, 'uri', $result->getUri());
        $this->addToMetadata(
            $metadata,
            'gateway/id',
            $result->getExternalId() ?? (array_key_exists('id', $response) ? $response['id'] : null)
        );
        if (array_key_exists('@type', $response)) {
            $this->addToMetadata($metadata, 'gateway/type', $response['@type']);
        }
        if (array_key_exists('@context', $response)) {
            $this->addToMetadata($metadata, 'gateway/context', $response['@context']);
        }
        $this->addToMetadata($metadata, 'synchronizations', $this->addObjectSyncsData($result));
        if (is_array($extend)) {
            $this->addToMetadata($metadata, 'extend', $extend);
        }
        if (is_array($fields)) {
            $this->addToMetadata($metadata, 'fields', $fields);
        }
        $this->addToMetadata($metadata, 'level', $level);

        return $metadata;
    }

    /**
     * Adds a key and value to the given $metadata array. But only if all or the $key is present in $this->xCommongatewayMetadata.
     * If $key contains dateRead this will also trigger some specific BL we only want to do if specifically asked for.
     *
     * @param array  $metadata
     * @param string $key
     * @param $value
     * @param string|null $overwriteKey Default = null, if a string is given this will be used instead of $key, for the key to add to the $metadata array.
     *
     * @return void
     */
    private function addToMetadata(array &$metadata, string $key, $value, ?string $overwriteKey = null)
    {
        if (array_key_exists('all', $this->xCommongatewayMetadata) || array_key_exists($key, $this->xCommongatewayMetadata)) {
            // Make sure we only do getDateRead function when it is present in $this->xCommongatewayMetadata
            if ($key === 'dateRead') {
                // If the api-call is an getItem call show NOW instead!
                $value = isset($this->xCommongatewayMetadata['dateRead']) && $this->xCommongatewayMetadata['dateRead'] === 'getItem'
                    ? new DateTime() : $this->getDateRead($value);
            }
            $metadata[$overwriteKey ?? $key] = $value;
        }
    }

    /**
     * Adds the most important data of all synchronizations an ObjectEntity has to an array and returns this array or null if it has no Synchronizations.
     *
     * @param ObjectEntity $object
     *
     * @return array|null
     */
    private function addObjectSyncsData(ObjectEntity $object): ?array
    {
        if (!empty($object->getSynchronizations()) && is_countable($object->getSynchronizations()) && count($object->getSynchronizations()) > 0) {
            $synchronizations = [];
            foreach ($object->getSynchronizations() as $synchronization) {
                $synchronizations[] = [
                    'id'      => $synchronization->getId()->toString(),
                    'gateway' => [
                        'id'       => $synchronization->getGateway()->getId()->toString(),
                        'name'     => $synchronization->getGateway()->getName(),
                        'location' => $synchronization->getGateway()->getLocation(),
                    ],
                    'endpoint'          => $synchronization->getEndpoint(),
                    'sourceId'          => $synchronization->getSourceId(),
                    'dateCreated'       => $synchronization->getDateCreated(),
                    'dateModified'      => $synchronization->getDateModified(),
                    'lastChecked'       => $synchronization->getLastChecked(),
                    'lastSynced'        => $synchronization->getLastSynced(),
                    'sourceLastChanged' => $synchronization->getSourceLastChanged(),
                ];
            }

            return $synchronizations;
        }

        return null;
    }

    /**
     * Renders the values of an ObjectEntity for the renderResult function.
     *
     * @param ObjectEntity $result
     * @param array|null   $fields
     * @param array|null   $extend
     * @param string       $acceptType
     * @param bool         $skipAuthCheck
     * @param bool         $flat
     * @param int          $level
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     */
    private function renderValues(ObjectEntity $result, ?array $fields, ?array $extend, string $acceptType, bool $skipAuthCheck = false, bool $flat = false, int $level = 0): array
    {
        $response = [];
        $embedded = [];

        // Lets keep track of how deep in the tree we are
        $level++;

        $entity = $result->getEntity();
        foreach ($entity->getAttributes() as $attribute) {
            // Only render the attributes that are used && don't render attributes that are writeOnly
            if ((!is_null($entity->getUsedProperties()) && !in_array($attribute->getName(), $entity->getUsedProperties()))
                || $attribute->getWriteOnly()
            ) {
                continue;
            }

            // Lets deal with fields filtering
            if (is_array($fields) and !array_key_exists($attribute->getName(), $fields)) {
                continue;
            }

            // Check if user is allowed to see this
            try {
                if (!$skipAuthCheck && !$this->checkOwner($result)) {
                    $this->authorizationService->checkAuthorization(['attribute' => $attribute, 'object' => $result]);
                }
            } catch (AccessDeniedException $exception) {
                continue;
            }

            $valueObject = $result->getValueByAttribute($attribute);
            if ($attribute->getType() == 'object') {
                // Lets deal with extending
                if (!$this->checkExtendAttribute($response, $attribute, $valueObject, $extend, $acceptType)) {
                    continue;
                }

                // Let's deal with subFields filtering
                $subFields = null;
                if (is_array($fields) && array_key_exists($attribute->getName(), $fields)) {
                    if (is_array($fields[$attribute->getName()])) {
                        $subFields = $fields[$attribute->getName()];
                    } elseif ($fields[$attribute->getName()] == false) {
                        continue;
                    }
                }

                // Let's deal with subExtend extending
                $subExtend = is_array($extend) ? $this->attributeSubExtend($extend, $attribute) : null;

                $renderObjects = $this->renderObjects($result, $embedded, $valueObject, $subFields, $subExtend, $acceptType, $skipAuthCheck, $flat, $level);
                $response[$attribute->getName()] = is_array($renderObjects) && array_key_exists('renderObjectsObjectsArray', $renderObjects) ? $renderObjects['renderObjectsObjectsArray'] : $renderObjects;
                if (is_array($renderObjects) && array_key_exists('renderObjectsEmbedded', $renderObjects)) {
                    $embedded = $renderObjects['renderObjectsEmbedded'];
                }

                continue;
            } elseif ($attribute->getType() == 'file') {
                $response[$attribute->getName()] = $this->renderFiles($valueObject);
                continue;
            }
            $response[$attribute->getName()] = $valueObject->getValue();
        }

        return [
            'renderValuesResponse' => $response,
            'renderValuesEmbedded' => isset($renderObjects) && is_array($renderObjects) && array_key_exists('renderObjectsEmbedded', $renderObjects) ? $renderObjects['renderObjectsEmbedded'] : [],
        ];
    }

    /**
     * Checks if a given attribute should be extended. Will return true if it should be extended and false if not.
     * Will also add a reference to an object to the response if the attribute should not be extended. Or null if there is no value.
     *
     * @param array      $response    The response array we will be adding object references to, if needed.
     * @param Attribute  $attribute   The attribute we are checking if it needs extending.
     * @param Value      $valueObject The value(Object) of the objectEntity for the attribute we are rendering.
     * @param array|null $extend      The extend array used in the api-call.
     * @param string     $acceptType  The acceptType used in the api-call.
     *
     * @return bool Will return true if the attribute should be extended and false if not.
     */
    private function checkExtendAttribute(array &$response, Attribute $attribute, Value $valueObject, ?array $extend, string $acceptType): bool
    {
        if ($attribute->getExtend() !== true &&
            (!is_array($extend) || (!array_key_exists('all', $extend) && !array_key_exists($attribute->getName(), $extend)))
        ) {
            $attribute->getMultiple() ?
                $this->renderObjectReferences($response, $attribute, $valueObject, $acceptType) :
                $this->renderObjectReference($response, $attribute, $valueObject, $acceptType);

            return false;
        }

        return true;
    }

    /**
     * For a multiple=false attribute, add a reference to a single object to the response if that attribute should not be extended.
     * Or adds null instead if there is no value at all.
     *
     * @param array     $response    The response array we will be adding an object references (or null) to.
     * @param Attribute $attribute   The attribute that does not need to be extended for the current result we are rendering.
     * @param Value     $valueObject The value(Object) of the objectEntity for the attribute we are rendering.
     * @param string    $acceptType  The acceptType used in the api-call.
     *
     * @return void
     */
    private function renderObjectReference(array &$response, Attribute $attribute, Value $valueObject, string $acceptType)
    {
        $object = $valueObject->getValue();
        if (!$object instanceof ObjectEntity) {
            $response[$attribute->getName()] = null;

            return;
        }
        $response[$attribute->getName()] = $this->renderObjectSelf($object, $acceptType);
    }

    /**
     * For a multiple=true attribute, add one or more references to one or more objects to the response if that attribute should not be extended.
     * Or adds null instead if there is no value at all.
     *
     * @param array     $response    The response array we will be adding one or more object references (or null) to.
     * @param Attribute $attribute   The attribute that does not need to be extended for the current result we are rendering.
     * @param Value     $valueObject The value(Object) of the objectEntity for the attribute we are rendering.
     * @param string    $acceptType  The acceptType used in the api-call.
     *
     * @return void
     */
    private function renderObjectReferences(array &$response, Attribute $attribute, Value $valueObject, string $acceptType)
    {
        $objects = $valueObject->getValue();
        if (!is_countable($objects)) {
            $response[$attribute->getName()] = [];

            return;
        }
        foreach ($objects as $object) {
            $response[$attribute->getName()][] = $this->renderObjectSelf($object, $acceptType);
        }
    }

    /**
     * Renders the 'self' of a given object, result will differ depending on the acceptType.
     *
     * @param ObjectEntity $object     The object to render 'self' for.
     * @param string       $acceptType The acceptType that will influence the way this 'self' is rendered.
     *
     * @return string|string[] The 'self' string or array with this string in it, depending on acceptType.
     */
    private function renderObjectSelf(ObjectEntity $object, string $acceptType)
    {
        $objectSelf = $object->getSelf() ?? '/api'.($object->getEntity()->getRoute() ?? $object->getEntity()->getName()).'/'.$object->getId();
        // todo: if we add more different acceptTypes to this list, use a switch:
        if ($acceptType === 'jsonld') {
            return ['@id' => $objectSelf];
        }

        return $objectSelf;
    }

    /**
     * Checks if a given attribute is present in the extend array and the value/object for this attribute should be extended.
     * This function will decide how the subExtend array for this attribute should look like.
     *
     * @param array     $extend    The extend array used in the api-call.
     * @param Attribute $attribute The attribute we are checking if it needs extending.
     *
     * @return array|null Will return the subExtend array for rendering the subresources if they should be extended. Will return empty array if attribute should not be extended.
     */
    private function attributeSubExtend(array $extend, Attribute $attribute): ?array
    {
        if (array_key_exists('all', $extend)) {
            return $extend;
        } elseif (array_key_exists($attribute->getName(), $extend) && is_array($extend[$attribute->getName()])) {
            return $extend[$attribute->getName()];
        }

        return null;
    }

    /**
     * Renders the objects of a value with attribute type 'object' for the renderValues function. If attribute is extended.
     *
     * @param ObjectEntity $result
     * @param array        $embedded
     * @param Value        $value
     * @param array|null   $fields
     * @param array|null   $extend
     * @param string       $acceptType
     * @param bool         $skipAuthCheck
     * @param bool         $flat
     * @param int          $level
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     *
     * @return string|array|null
     */
    private function renderObjects(ObjectEntity $result, array $embedded, Value $value, ?array $fields, ?array $extend, string $acceptType, bool $skipAuthCheck = false, bool $flat = false, int $level = 0)
    {
        $attribute = $value->getAttribute();

        if ($value->getValue() == null) {
            return [
                'renderObjectsObjectsArray' => $attribute->getMultiple() ? [] : null,
                'renderObjectsEmbedded'     => $embedded,
            ];
        }

        // If we have only one Object (because multiple = false)
        if (!$attribute->getMultiple()) {
            try {
                // if you have permission to see the entire parent object, you are allowed to see it's attributes, but you might not have permission to see that property if it is an object
                if (!$skipAuthCheck && !$this->checkOwner($result)) {
                    $this->authorizationService->checkAuthorization(['entity' => $attribute->getObject(), 'object' => $value->getValue()]);
                }

                if ($attribute->getInclude()) {
                    return $this->renderResult($value->getValue(), $fields, $extend, $acceptType, $skipAuthCheck, $flat, $level);
                } else {
                    $embedded[$attribute->getName()] = $this->renderResult($value->getValue(), $fields, $extend, $acceptType, $skipAuthCheck, $flat, $level);
                }

                $object = $value->getValue();

                return [
                    'renderObjectsObjectsArray' => $this->renderObjectSelf($object, $acceptType),
                    'renderObjectsEmbedded'     => $embedded,
                ];
            } catch (AccessDeniedException $exception) {
                return null;
            }
        }

        // If we can have multiple Objects (because multiple = true)
        $objects = $value->getValue();
        $objectsArray = [];
        foreach ($objects as $object) {
            try {
                // if you have permission to see the entire parent object, you are allowed to see it's attributes, but you might not have permission to see that property if it is an object
                if (!$skipAuthCheck && !$this->checkOwner($result)) {
                    $this->authorizationService->checkAuthorization(['entity' => $attribute->getObject(), 'object' => $object]);
                }
                if ($attribute->getInclude()) {
                    $objectsArray[] = $this->renderResult($object, $fields, $extend, $acceptType, $skipAuthCheck, $flat, $level);
                    continue;
                } else {
                    $embedded[$attribute->getName()][] = $this->renderResult($object, $fields, $extend, $acceptType, $skipAuthCheck, $flat, $level);
                }

                $objectsArray[] = $this->renderObjectSelf($object, $acceptType);
            } catch (AccessDeniedException $exception) {
                continue;
            }
        }

        return [
            'renderObjectsObjectsArray' => $objectsArray,
            'renderObjectsEmbedded'     => $embedded,
        ];
    }

    /**
     * Renders the files of a value with attribute type 'file' for the renderValues function.
     *
     * @param Value $value
     *
     * @return array|null
     */
    private function renderFiles(Value $value): ?array
    {
        $attribute = $value->getAttribute();

        if ($value->getValue() == null) {
            return null;
        }
        if (!$attribute->getMultiple()) {
            return $this->renderFileResult($value->getValue());
        }
        $files = $value->getValue();
        $filesArray = [];
        foreach ($files as $file) {
            $filesArray[] = $this->renderFileResult($file);
        }

        return $filesArray;
    }

    /**
     * Renders the result for a File that will be used (in renderFiles) for the response after a successful api call.
     *
     * @param File $file
     *
     * @return array
     */
    private function renderFileResult(File $file): array
    {
        return [
            'id'        => $file->getId()->toString(),
            'name'      => $file->getName(),
            'extension' => $file->getExtension(),
            'mimeType'  => $file->getMimeType(),
            'size'      => $file->getSize(),
            'base64'    => $file->getBase64(),
        ];
    }

    /**
     * @TODO
     *
     * @param array $result
     * @param int   $responseType
     *
     * @return bool
     */
    public function checkForErrorResponse(array $result, int $responseType = Response::HTTP_BAD_REQUEST): bool
    {
        if (
            $responseType != Response::HTTP_OK && $responseType != Response::HTTP_CREATED && $responseType != Response::HTTP_NO_CONTENT
            && array_key_exists('message', $result) && array_key_exists('type', $result)
            && array_key_exists('path', $result)
        ) { //We also have key data, but this one is not always required and can be empty as well
            return true;
        }

        return false;
    }

    /**
     * @TODO
     *
     * @param Request           $request
     * @param Entity|null       $entity
     * @param array             $result
     * @param Response          $response
     * @param ObjectEntity|null $object
     *
     * @return RequestLog
     */
    public function createRequestLog(Request $request, ?Entity $entity, array $result, Response $response, ?ObjectEntity $object = null): RequestLog
    {
        // TODO: REMOVE THIS WHEN ENDPOINTS BL IS ADDED
        $endpoint = $this->em->getRepository('App:Endpoint')->findOneBy(['name' => 'TempRequestLogEndpointWIP']);
        if (empty($endpoint)) {
            $endpoint = new Endpoint();
            $endpoint->setName('TempRequestLogEndpointWIP');
//            $endpoint->setType('gateway-endpoint');
//            $endpoint->setPath('not a real endpoint');
            $endpoint->setDescription('This is a endpoint added to use the default loggingConfig of endpoints');
            $this->em->persist($endpoint);
            $this->em->flush();
        }

        //TODO: Find a clean and nice way to not set properties on RequestLog if they are present in the $endpoint->getLoggingConfig() array!
        $requestLog = new RequestLog();
        //        $requestLog->setEndpoint($entity ? $entity->getEndpoint());
        $requestLog->setEndpoint($endpoint); // todo this^ make Entity Endpoint an object instead of string

//        if ($request->getMethod() == 'POST' && $object) {
//            $this->em->persist($object);
//        }
//        $requestLog->setObjectEntity($object);
        if ($entity) {
            $entity = $this->em->getRepository('App:Entity')->findOneBy(['id' => $entity->getId()->toString()]);
        }
        $requestLog->setEntity($entity ?? null);
        $requestLog->setDocument(null); // todo
        $requestLog->setFile(null); // todo
        $requestLog->setGateway($requestLog->getEntity() ? $requestLog->getEntity()->getGateway() : null);

        if ($this->session->has('application') && Uuid::isValid($this->session->get('application'))) {
            $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
        } else {
            $application = null;
        }
        $requestLog->setApplication($application);
        $requestLog->setOrganization($this->session->get('activeOrganization'));
        $requestLog->setUser($this->security->getUser() ? $this->security->getUser()->getUserIdentifier() : 'anonymousUser');

        $requestLog->setStatusCode($response->getStatusCode());
        $requestLog->setStatus($this->getStatusWithCode($response->getStatusCode()) ?? $result['type']);
        $requestLog->setRequestBody($request->getContent() ? $request->toArray() : null);
        $requestLog->setResponseBody($result);

        $requestLog->setMethod($request->getMethod());
        $requestLog->setHeaders($this->filterRequestLogHeaders($requestLog->getEndpoint(), $request->headers->all()));
        //todo use eavService->realRequestQueryAll(), maybe replace this function to another service than eavService?
        $requestLog->setQueryParams($request->query->all());

        $this->em->persist($requestLog);
        $this->em->flush();

        return $requestLog;
    }

    /**
     * @TODO
     *
     * @param int $statusCode
     *
     * @return string|null
     */
    private function getStatusWithCode(int $statusCode): ?string
    {
        $reflectionClass = new ReflectionClass(Response::class);
        $constants = $reflectionClass->getConstants();

        foreach ($constants as $status => $value) {
            if ($value == $statusCode) {
                return $status;
            }
        }

        return null;
    }

    /**
     * @TODO
     *
     * @param Endpoint $endpoint
     * @param array    $headers
     * @param int      $level
     *
     * @return array
     */
    private function filterRequestLogHeaders(Endpoint $endpoint, array $headers, int $level = 1): array
    {
        foreach ($headers as $header => &$headerValue) {
            // Filter out headers we do not want to log on this endpoint
            if (
                $level == 1 && $endpoint->getLoggingConfig() && array_key_exists('headers', $endpoint->getLoggingConfig()) &&
                in_array($header, $endpoint->getLoggingConfig()['headers'])
            ) {
                unset($headers[$header]);
            }
            if (is_string($headerValue) && strlen($headerValue) > 250) {
                $headers[$header] = substr($headerValue, 0, 250).'...';
            } elseif (is_array($headerValue)) {
                $headerValue = $this->filterRequestLogHeaders($endpoint, $headerValue, $level + 1);
            } elseif (!is_string($headerValue)) {
                //todo?
                $headers[$header] = 'Couldn\'t log this headers value because it is of type '.gettype($headerValue);
            }
        }

        return $headers;
    }
}
