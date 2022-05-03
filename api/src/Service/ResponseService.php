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
     * @param array|null   $fields        The fields that have to be displayed
     * @param ObjectEntity $result        The objectEntity that contains the results
     * @param bool         $skipAuthCheck Whether the authorization should be checked
     *
     * @return array The resulting response
     */
    public function filterResult(array $response, ?array $fields, ObjectEntity $result, bool $skipAuthCheck): array
    {
        return array_filter($response, function ($value, $key) use ($fields, $result, $skipAuthCheck) {
            if (str_starts_with($key, '@') || $key == 'id') {
                return true;
            }
            if (is_array($fields) && !array_key_exists($key, $fields)) {
                return false;
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
     * @param $fields
     * //     * @param ArrayCollection|null $maxDepth
     * @param bool $flat
     * @param int  $level
     *
     * @return array
     */
    // Old $MaxDepth;
//    public function renderResult(ObjectEntity $result, $fields, ArrayCollection $maxDepth = null, bool $flat = false, int $level = 0): array
    public function renderResult(ObjectEntity $result, $fields, bool $skipAuthCheck = false, bool $flat = false, int $level = 0): array
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

        $item = $this->cache->getItem('object_'.md5($result->getId()));
        if ($item->isHit()) {
            return $this->filterResult($item->get(), $fields, $result, $skipAuthCheck);
        }

        // Make sure to break infinite render loops! ('New' MaxDepth)
        if ($level > 2) {
            return [
                '@id' => ucfirst($result->getEntity()->getName()).'/'.$result->getId(),
            ];
        }

        if ($result->getEntity()->getGateway() && $result->getEntity()->getEndpoint()) {
            // Lets start with the external results
            if (!empty($result->getExternalResult())) {
                $response = array_merge($response, $result->getExternalResult());
            } elseif (!$result->getExternalResult() === [] && $this->commonGroundService->isResource($result->getExternalResult())) {
                $response = array_merge($response, $this->commonGroundService->getResource($result->getExternalResult()));
            } elseif ($this->commonGroundService->isResource($result->getUri())) {
                $response = array_merge($response, $this->commonGroundService->getResource($result->getUri()));
            }

            // Only render the attributes that are available for this Entity (filters out unwanted properties from external results)
            if (!is_null($result->getEntity()->getAvailableProperties())) {
                $response = array_filter($response, function ($propertyName) use ($result) {
                    return in_array($propertyName, $result->getEntity()->getAvailableProperties());
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        // Let overwrite the id with the gateway id
        $response['id'] = $result->getId();

        // Let get the internal results
        // Old $MaxDepth;
//        $response = array_merge($response, $this->renderValues($result, $fields, $maxDepth, $flat, $level));
        $response = array_merge($response, $this->renderValues($result, $fields, $skipAuthCheck, $flat, $level));

        // Lets sort the result alphabeticly

        // Lets skip the pritty styff when dealing with a flat object
        if ($flat) {
            ksort($response);
            $item->set($response);
            $this->cache->save($item);

            return $this->filterResult($response, $fields, $result, $skipAuthCheck);
        }

        // Lets make it personal
        $gatewayContext = [];
        $gatewayContext['@id'] = ucfirst($result->getEntity()->getName()).'/'.$result->getId();
        $gatewayContext['@type'] = ucfirst($result->getEntity()->getName());
        $gatewayContext['@context'] = '/contexts/'.ucfirst($result->getEntity()->getName());
        $gatewayContext['@dateCreated'] = $result->getDateCreated();
        $gatewayContext['@dateModified'] = $result->getDateModified();
        $gatewayContext['@organization'] = $result->getOrganization();
        if ($result->getApplication() !== null) {
            $gatewayContext['@application'] = $result->getApplication()->getId();
        }
        $gatewayContext['@owner'] = $result->getOwner();
        if ($result->getUri()) {
            $gatewayContext['@uri'] = $result->getUri();
        }
        // Lets move some stuff out of the way
        if (array_key_exists('@context', $response)) {
            $gatewayContext['@gateway/context'] = $response['@context'];
        }
        if ($result->getExternalId()) {
            $gatewayContext['@gateway/id'] = $result->getExternalId();
        } elseif (array_key_exists('id', $response)) {
            $gatewayContext['@gateway/id'] = $response['id'];
        }
        if (array_key_exists('@type', $response)) {
            $gatewayContext['@gateway/type'] = $response['@type'];
        }
        if (is_array($fields)) {
            $gatewayContext['@fields'] = $fields;
        }
        $gatewayContext['@level'] = $level;
        $gatewayContext['id'] = $result->getId();

        ksort($response);
        $response = $gatewayContext + $response;

        $item->set($response);
        $this->cache->save($item);

        return $this->filterResult($response, $fields, $result, $skipAuthCheck);
    }

    /**
     * Renders the values of an ObjectEntity for the renderResult function.
     *
     * @param ObjectEntity $result
     * @param $fields
     * //     * @param ArrayCollection|null $maxDepth
     * @param bool $flat
     * @param int  $level
     *
     * @return array
     */
    // Old $MaxDepth;
//    private function renderValues(ObjectEntity $result, $fields, ?ArrayCollection $maxDepth = null, bool $flat = false, int $level = 0): array
    private function renderValues(ObjectEntity $result, $fields, bool $skipAuthCheck = false, bool $flat = false, int $level = 0): array
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

            // Lets deal with subfields filtering
            $subfields = false;
            if (is_array($fields) and array_key_exists($attribute->getName(), $fields)) {
                $subfields = $fields[$attribute->getName()];
            }
            if (!$subfields) {
                $subfields = $fields;
            }

            $valueObject = $result->getValueByAttribute($attribute);
            if ($attribute->getType() == 'object') {
                $response[$attribute->getName()] = $this->renderObjects($result, $valueObject, $subfields, $skipAuthCheck, $flat, $level);

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
     * @param Value $value
     * @param $fields
     * //     * @param ArrayCollection|null $maxDepth
     * @param bool $flat
     * @param int  $level
     *
     * @return array|null
     */
    // Old $MaxDepth;
//    private function renderObjects(Value $value, $fields, ?ArrayCollection $maxDepth, bool $flat = false, int $level = 0): ?array
    private function renderObjects(ObjectEntity $result, Value $value, $fields, bool $skipAuthCheck = false, bool $flat = false, int $level = 0): ?array
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

                return $this->renderResult($value->getValue(), $fields, $skipAuthCheck, $flat, $level);

                // Old $MaxDepth;
//                // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
//                if ($maxDepth) {
//                    if (!$maxDepth->contains($value->getValue())) {
//                        return $this->renderResult($value->getValue(), $fields, $maxDepth, $flat, $level);
//                    }
//
//                    return ['continue' => 'continue']; //TODO NOTE: We want this here
//                }
//
//                return $this->renderResult($value->getValue(), $fields, null, $flat, $level);
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
                $objectsArray[] = $this->renderResult($object, $fields, $skipAuthCheck, $flat, $level);

                // Old $MaxDepth;
//            // Do not call recursive function if we reached maxDepth (if we already rendered this object before)
//            if ($maxDepth) {
//                if (!$maxDepth->contains($object)) {
//                    $objectsArray[] = $this->renderResult($object, $fields, $maxDepth, $flat, $level);
//                    continue;
//                }
//                // If multiple = true and a subresource contains an inversedby list of resources that contains this resource ($result), only show the @id
//                $objectsArray[] = ['@id' => ucfirst($object->getEntity()->getName()).'/'.$object->getId()];
//                continue;
//            }
//            $objectsArray[] = $this->renderResult($object, $fields, null, $flat, $level);
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
