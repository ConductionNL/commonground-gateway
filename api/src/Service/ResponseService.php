<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\File;
use App\Entity\ObjectEntity;
use App\Entity\RequestLog;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
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

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, AuthorizationService $authorizationService, SessionInterface $session, Security $security, CacheInterface $cache)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->authorizationService = $authorizationService;
        $this->session = $session;
        $this->security = $security;
        $this->cache = $cache;
    }

    // todo remove responseService from the ObjectEntityService, so we can use the ObjectEntityService->checkOwner() function here
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

        if (
            $result->getEntity()->getGateway() !== null &&
            ($result->getEntity()->getGateway()->getType() == 'soap' ||
                $result->getEntity()->getGateway()->getType() == 'xml' ||
                $result->getEntity()->getGateway()->getAuth() == 'vrijbrp-jwt')
        ) {
            return $response;
        }

        $item = $this->cache->getItem(
            'object_'
            .md5(
                $result->getId()
                .$acceptType
                .$level
                .http_build_query($fields ?? [], '', ',')
                .http_build_query($extend ?? [], '', ',')
            )
        );
        if ($item->isHit()) {
//            var_dump('FromCache: '.$result->getId().http_build_query($fields ?? [],'',','));
            return $this->filterResult($item->get(), $result, $skipAuthCheck);
        }
        $item->tag('object_'.md5($result->getId()->toString()));

        // Make sure to break infinite render loops! ('New' MaxDepth)
        if ($level > 3) {
            if ($acceptType === 'jsonld') {
                return [
                    '@id' => $result->getSelf() ?? '/api/'.($result->getEntity()->getRoute() ?? $result->getEntity()->getName()).'/'.$result->getId(),
                ];
            }

            return $result->getSelf() ?? '/api/'.($result->getEntity()->getRoute() ?? $result->getEntity()->getName()).'/'.$result->getId();
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
                        (!$attTypeObject || $attribute->getExtend() || (!empty($extend) && array_key_exists($propertyName, $extend))) &&
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

        $item->set($response);
        $this->cache->save($item);

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
                // todo: do we want to use embedded here? or just always show all objects instead? see include on attribute...
                $embedded['embedded'] = $embedded;
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
        $gatewayContext['@id'] = $result->getSelf() ?? '/api/'.($result->getEntity()->getRoute() ?? $result->getEntity()->getName()).'/'.$result->getId();
        $gatewayContext['@type'] = ucfirst($result->getEntity()->getName());
        $gatewayContext['@context'] = '/contexts/'.ucfirst($result->getEntity()->getName());
        $gatewayContext['@dateCreated'] = $result->getDateCreated();
        $gatewayContext['@dateModified'] = $result->getDateModified();
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
        $gatewayContext['_links']['self']['href'] = $result->getSelf() ?? '/api/'.($result->getEntity()->getRoute() ?? $result->getEntity()->getName()).'/'.$result->getId();
        $gatewayContext['_metadata'] = [
            '_type'         => ucfirst($result->getEntity()->getName()),
            '_context'      => '/contexts/'.ucfirst($result->getEntity()->getName()),
            '_dateCreated'  => $result->getDateCreated(),
            '_dateModified' => $result->getDateModified(),
            '_owner'        => $result->getOwner(),
            '_organization' => $result->getOrganization(),
            '_application'  => $result->getApplication() !== null ? $result->getApplication()->getId() : null,
            '_uri'          => $result->getUri(),
            '_gateway/id'   => $result->getExternalId() ?? (array_key_exists('id', $response) ? $response['id'] : null),
        ];
        if (array_key_exists('@type', $response)) {
            $gatewayContext['_metadata']['_gateway/type'] = $response['@type'];
        }
        if (array_key_exists('@context', $response)) {
            $gatewayContext['_metadata']['_gateway/context'] = $response['@context'];
        }
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
                if ($attribute->getExtend() !== true && (!is_array($extend) || !array_key_exists($attribute->getName(), $extend))) {
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
                $subExtend = null;
                if (is_array($extend) && array_key_exists($attribute->getName(), $extend)) {
                    if (is_array($extend[$attribute->getName()])) {
                        $subExtend = $extend[$attribute->getName()];
                    } elseif ($extend[$attribute->getName()] == false) {
                        continue;
                    }
                }

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
     * Renders the objects of a value with attribute type 'object' for the renderValues function.
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
            return null;
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
                if ($acceptType === 'jsonld') {
                    return [
                        'renderObjectsObjectsArray' => [
                            '@id' => $object->getSelf() ?? '/api/'.($object->getEntity()->getRoute() ?? $object->getEntity()->getName()).'/'.$object->getId(),
                        ],
                        'renderObjectsEmbedded' => $embedded,
                    ];
                }

                return [
                    'renderObjectsObjectsArray' => $object->getSelf() ?? '/api/'.($object->getEntity()->getRoute() ?? $object->getEntity()->getName()).'/'.$object->getId(),
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

                if ($acceptType === 'jsonld') {
                    $objectsArray[] = [
                        '@id' => $object->getSelf() ?? '/api/'.($object->getEntity()->getRoute() ?? $object->getEntity()->getName()).'/'.$object->getId(),
                    ];
                } else {
                    $objectsArray[] = $object->getSelf() ?? '/api/'.($object->getEntity()->getRoute() ?? $object->getEntity()->getName()).'/'.$object->getId();
                }
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
     * @param $responseType
     *
     * @return bool
     */
    public function checkForErrorResponse(array $result, $responseType = Response::HTTP_BAD_REQUEST): bool
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
