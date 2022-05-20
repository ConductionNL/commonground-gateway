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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ResponseService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private AuthorizationService $authorizationService;
    private SessionInterface $session;
    private TokenStorageInterface $tokenStorage;
    private CacheInterface $cache;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, AuthorizationService $authorizationService, SessionInterface $session, TokenStorageInterface $tokenStorage, CacheInterface $cache)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->authorizationService = $authorizationService;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->cache = $cache;
    }

    // todo remove responseService from the ObjectEntityService, so we can use the ObjectEntityService->checkOwner() function here
    private function checkOwner(ObjectEntity $result): bool
    {
        // TODO: what if somehow the owner of this ObjectEntity is null? because of ConvertToGateway ObjectEntities for example?
        $user = $this->tokenStorage->getToken()->getUser();

        if (!is_string($user) && $result->getOwner() === $user->getUserIdentifier()) {
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
     * @param array|null $fields
     * @param array|null $extend
     * @param string $acceptType
     * @param bool $skipAuthCheck
     * @param bool $flat todo: $flat and $acceptType = 'json' should have the same result, so remove $flat?
     * @param int $level
     *
     * @return array|string[]
     *
     * @throws CacheException|InvalidArgumentException
     */
    // Old $MaxDepth;
//    public function renderResult(ObjectEntity $result, $fields, ArrayCollection $maxDepth = null, bool $flat = false, int $level = 0): array
    public function renderResult(ObjectEntity $result, ?array $fields, ?array $extend, string $acceptType = 'jsonld', bool $skipAuthCheck = false, bool $flat = false, int $level = 0): array
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

        $item = $this->cache->getItem('object_'.md5($result->getId().$acceptType.$level.http_build_query($fields ?? [],'',',')));
        if ($item->isHit()) {
//            var_dump('FromCache: '.$result->getId().http_build_query($fields ?? [],'',','));
//            return $this->filterResult($item->get(), $result, $skipAuthCheck);
        }
        $item->tag('object_'.md5($result->getId()->toString()));

        // Make sure to break infinite render loops! ('New' MaxDepth)
        if ($level > 3) {
            return [
                '@id' => '/'.$result->getEntity()->getName().'/'.$result->getId(),
            ];
        }

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
                    return (empty($fields) || array_key_exists($propertyName, $fields)) &&
                        (empty($result->getEntity()->getAvailableProperties()) || in_array($propertyName, $result->getEntity()->getAvailableProperties()));
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        // Let overwrite the id with the gateway id
        $response['id'] = $result->getId();

        // Let get the internal results
        // Old $MaxDepth;
//        $response = array_merge($response, $this->renderValues($result, $fields, $extend, $maxDepth, $flat, $level));
        $response = array_merge($response, $this->renderValues($result, $fields, $extend, $acceptType, $skipAuthCheck, $flat, $level));

        // Lets sort the result alphabeticly
        ksort($response);

        // Lets skip the pritty styff when dealing with a flat object
        // todo: $flat and $acceptType = 'json' should have the same result, so remove $flat?
        if ($flat) {
            $item->set($response);
            $this->cache->save($item);

            return $response;
        }

        $response = $this->handleAcceptType($result, $fields, $extend, $acceptType, $level, $response);

        $item->set($response);
        $this->cache->save($item);

        return $response;
    }

    /**
     * @TODO
     *
     * @param ObjectEntity $result
     * @param array|null $fields
     * @param array|null $extend
     * @param string $acceptType
     * @param int $level
     * @param array $response
     *
     * @return array
     */
    private function handleAcceptType(ObjectEntity $result, ?array $fields, ?array $extend, string $acceptType, int $level, array $response): array
    {
        $gatewayContext = [];
        // todo: split switch into functions?
        switch ($acceptType) {
            case 'jsonld':
                $gatewayContext['@id'] = '/'.$result->getEntity()->getName().'/'.$result->getId();
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
                break;
            case 'jsonhal':
                $gatewayContext['_links']['self'] = '/'.$result->getEntity()->getName().'/'.$result->getId();
                $gatewayContext['_metadata'] = [
                    "@type"         => ucfirst($result->getEntity()->getName()),
                    "@context"      => '/contexts/'.ucfirst($result->getEntity()->getName()),
                    "@dateCreated"  => $result->getDateCreated(),
                    "@dateModified" => $result->getDateModified(),
                    "@owner"        => $result->getOwner(),
                    "@organization" => $result->getOrganization(),
                    "@application"  => $result->getApplication() !== null ? $result->getApplication()->getId() : null,
                    "@uri"          => $result->getUri(),
                    "@gateway/id"   => $result->getExternalId() ?? (array_key_exists('id', $response) ? $response['id'] : null)
                ];
                if (array_key_exists('@type', $response)) {
                    $gatewayContext['_metadata']['@gateway/type'] = $response['@type'];
                }
                if (array_key_exists('@context', $response)) {
                    $gatewayContext['_metadata']['@gateway/context'] = $response['@context'];
                }
                if (is_array($extend)) {
                    $gatewayContext['_metadata']['@extend'] = $extend;
                }
                if (is_array($fields)) {
                    $gatewayContext['_metadata']['@fields'] = $fields;
                }
                $gatewayContext['_metadata']['@level'] = $level;
                break;
            case 'json':
            default:
                break;
        }

        $gatewayContext['id'] = $result->getId();
        return $gatewayContext + $response;
    }

    /**
     * Renders the values of an ObjectEntity for the renderResult function.
     *
     * @param ObjectEntity $result
     * @param array|null $fields
     * @param array|null $extend
     * @param string $acceptType
     * @param bool $skipAuthCheck
     * @param bool $flat
     * @param int $level
     *
     * @return array
     *
     * @throws CacheException|InvalidArgumentException
     */
    // Old $MaxDepth;
//    private function renderValues(ObjectEntity $result, $fields, ?ArrayCollection $maxDepth = null, bool $flat = false, int $level = 0): array
    private function renderValues(ObjectEntity $result, ?array $fields, ?array $extend, string $acceptType, bool $skipAuthCheck = false, bool $flat = false, int $level = 0): array
    {
        $response = [];

        // Lets keep track of how deep in the three we are
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
                $response[$attribute->getName()] = $this->renderObjects($result, $valueObject, $subFields, $subExtend, $acceptType, $skipAuthCheck, $flat, $level);

                // Old $MaxDepth;
//                    // TODO: this code might cause for very slow api calls, another fix could be to always set inversedBy on both (sides) attributes so we only have to check $attribute->getInversedBy()
//                    // If this attribute has no inversedBy but the Object we are rendering has parent objects.
//                    // Check if one of the parent objects has an attribute with inversedBy -> this attribute.
//                    $parentInversedByAttribute = [];
//                    if (!$attribute->getInversedBy() && count($result->getSubresourceOf()) > 0) {
//                        // Get all parent (value) objects...
//                        $parentInversedByAttribute = $result->getSubresourceOf()->filter(function (Value $value) use ($attribute) {
//                            // ...that have getInversedBy set to $attribute
//                            $inversedByAttributes = $value->getObjectEntity()->getEntity()->getAttributes()->filter(function (Attribute $item) use ($attribute) {
//                                return $item->getInversedBy() === $attribute;
//                            });
//                            if (count($inversedByAttributes) > 0) {
//                                return true;
//                            }
//
//                            return false;
//                        });
//                    }
//                    // Only use maxDepth for subresources if inversedBy is set on this attribute or if one of the parent objects has an attribute with inversedBy this attribute.
//                    // If we do not check this, we might skip rendering of entire objects (subresources) we do want to render!!!
//                    if ($attribute->getInversedBy() || count($parentInversedByAttribute) > 0) {
//                        // Lets keep track of objects we already rendered, for inversedBy, checking maxDepth 1:
//                        $maxDepthPerValue = $maxDepth;
//                        if (is_null($maxDepth)) {
//                            $maxDepthPerValue = new ArrayCollection();
//                        }
//                        $maxDepthPerValue->add($result);
//                        $response[$attribute->getName()] = $this->renderObjects($valueObject, $subfields, $maxDepthPerValue, $flat, $level);
//                    } else {
//                        $response[$attribute->getName()] = $this->renderObjects($valueObject, $subfields, null, $flat, $level);
//                    }
//
//                    if ($response[$attribute->getName()] === ['continue' => 'continue']) {
//                        unset($response[$attribute->getName()]);
//                    }
                continue;
            } elseif ($attribute->getType() == 'file') {
                $response[$attribute->getName()] = $this->renderFiles($valueObject);
                continue;
            }
            $response[$attribute->getName()] = $valueObject->getValue();
        }

        return $response;
    }

    /**
     * Renders the objects of a value with attribute type 'object' for the renderValues function.
     *
     * @param ObjectEntity $result
     * @param Value $value
     * @param array|null $fields
     * @param array|null $extend
     * @param string $acceptType
     * @param bool $skipAuthCheck
     * @param bool $flat
     * @param int $level
     *
     * @return array|null
     *
     * @throws CacheException|InvalidArgumentException
     */
    // Old $MaxDepth;
//    private function renderObjects(Value $value, $fields, ?ArrayCollection $maxDepth, bool $flat = false, int $level = 0): ?array
    private function renderObjects(ObjectEntity $result, Value $value, ?array $fields, ?array $extend, string $acceptType, bool $skipAuthCheck = false, bool $flat = false, int $level = 0): ?array
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
                }
                $object = $value->getValue();
                return [
                    '@id' => '/'.$object->getEntity()->getName().'/'.$object->getId(),
                ];

                // Old $MaxDepth;
//                // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
//                if ($maxDepth) {
//                    if (!$maxDepth->contains($value->getValue())) {
//                        return $this->renderResult($value->getValue(), $fields, $extend, $maxDepth, $flat, $level);
//                    }
//
//                    return ['continue' => 'continue']; //TODO NOTE: We want this here
//                }
//
//                return $this->renderResult($value->getValue(), $fields, $extend, null, $flat, $level);
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
                } else {
                    $objectsArray[] = [
                        '@id' => '/'.$object->getEntity()->getName().'/'.$object->getId(),
                    ];
                }

                // Old $MaxDepth;
//            // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
//            if ($maxDepth) {
//                if (!$maxDepth->contains($object)) {
//                    $objectsArray[] = $this->renderResult($object, $fields, $extend, $maxDepth, $flat, $level);
//                    continue;
//                }
//                // If multiple = true and a subresource contains an inversedby list of resources that contains this resource ($result), only show the @id
//                $objectsArray[] = ['@id' => ucfirst($object->getEntity()->getName()).'/'.$object->getId()];
//                continue;
//            }
//            $objectsArray[] = $this->renderResult($object, $fields, $extend, null, $flat, $level);
            } catch (AccessDeniedException $exception) {
                continue;
            }
        }

        return $objectsArray;
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
        $requestLog->setUser(!is_string($this->tokenStorage->getToken()->getUser()) ? $this->tokenStorage->getToken()->getUser()->getUserIdentifier() : $this->tokenStorage->getToken()->getUser());

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
