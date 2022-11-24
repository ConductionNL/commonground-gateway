<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use App\Message\SyncPageMessage;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ConvertToGatewayService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $em;
    private SessionInterface $session;
    private GatewayService $gatewayService;
    private FunctionService $functionService;
    private LogService $logService;
    private MessageBusInterface $messageBus;
    private TranslationService $translationService;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, SessionInterface $session, GatewayService $gatewayService, FunctionService $functionService, LogService $logService, MessageBusInterface $messageBus, TranslationService $translationService)
    {
        $this->commonGroundService = $commonGroundService;
        $this->em = $entityManager;
        $this->session = $session;
        $this->gatewayService = $gatewayService;
        $this->functionService = $functionService;
        $this->logService = $logService;
        $this->messageBus = $messageBus;
        $this->translationService = $translationService;
    }

    /**
     * Gets all objects from entity->gateway (/source) and converts them to ObjectEntities,
     * todo: will also remove ObjectEntities that should no longer exist, because they got removed from the source api.
     *
     * @param Entity $entity
     * @param $query
     *
     * @return void|null
     */
    public function convertEntityObjects(Entity $entity, $query)
    {
        // Make sure we have a gateway and endpoint on this Entity.
        if (!$entity->getSource() || !$entity->getSource()->getLocation() || !$entity->getEndpoint() || !$entity->getSource()->getIsEnabled()) {
            return null; //Or false or error?
        }

        // Get the first page of objects for this entity that exist outside the gateway
        $collectionConfigPaginationPages = explode('.', $entity->getCollectionConfig()['paginationPages']);
        $component = $this->gatewayService->gatewayToArray($entity->getGateway());
        $url = $entity->getGateway()->getLocation().'/'.$entity->getEndpoint();
        $query = $this->stripAt(array_filter($query, fn ($key) => (strpos($key, '@') === 0), ARRAY_FILTER_USE_KEY));
        $response = $this->commonGroundService->callService($component, $url, '', $query, $entity->getGateway()->getHeaders(), false, 'GET');
        // Now get the total amount of pages from the correct place in the response
        $amountOfPages = json_decode($response->getBody()->getContents(), true);
        foreach ($collectionConfigPaginationPages as $item) {
            $amountOfPages = $amountOfPages[$item] ?? 1;
        }
        if (!is_int($amountOfPages)) {
            $matchesCount = preg_match('/\?page=([0-9]+)/', $amountOfPages, $matches);
            if ($matchesCount == 1) {
                $amountOfPages = (int) $matches[1];
            } else {
                // todo: throw error or something...
//                var_dump('Could not find the total amount of pages');
                return;
            }
        }

//        var_dump($amountOfPages . ' pages');

        $application = $this->session->get('application');
        $activeOrganization = $this->session->get('activeOrganization');

        // todo: make this a message in order to compare and handle gateway objects that need to be deleted? see old code below this for.
        // Loop, for each page create a message:
        for ($page = 1; $page <= $amountOfPages; $page++) {
            $this->messageBus->dispatch(new SyncPageMessage(
                [
                    'component' => $component,
                    'url'       => $url,
                    'query'     => $query,
                    'headers'   => $entity->getGateway()->getHeaders(),
                ],
                $page,
                $entity->getId(),
                [
                    'application'        => $application,
                    'activeOrganization' => $activeOrganization,
                ]
            ));
        }

        // todo: old code for deleting existing ObjectEntities that should no longer exist
////        var_dump('New gateway objects = '.count($newGatewayObjects));
//
//        // Now also find all objects that exist in the gateway but not outside the gateway in the extern component.
//        $externObjectIds = $totalExternObjects;
//        foreach ($collectionConfigId as $item) {
//            $externObjectIds = array_column($externObjectIds, $item);
//        }
////        var_dump('ExternObjectIds:', $externObjectIds);
//        $onlyInGateway = $entity->getObjectEntities()->filter(function (ObjectEntity $object) use ($externObjectIds) {
//            return !in_array($object->getExternalId(), $externObjectIds) && !in_array($this->commonGroundService->getUuidFromUrl($object->getUri()), $externObjectIds);
//        });
//
//        // Delete these $onlyInGateway objectEntities ?
//        foreach ($onlyInGateway as $item) {
////            var_dump($item->getId()->toString());
//            $this->em->remove($item);
//        }
////        var_dump('Deleted gateway objects = '.count($onlyInGateway));
    }

    private function stripAt(array $in)
    {
        $out = [];
        foreach ($in as $key => $value) {
            $out[ltrim($key, '@')] = $value;
        }

        return $out;
    }

    /**
     * Convert an object from outside the gateway into an ObjectEntity in the gateway.
     *
     * @param Entity            $entity
     * @param array|null        $body
     * @param string|null       $id
     * @param Value|null        $subresourceOf
     * @param ObjectEntity|null $objectEntity  a main objectEntity this new OE will be part of, used to check for errors before flushing new OE.
     * @param string|null       $url
     * @param array             $sessionData
     *
     * @throws InvalidArgumentException
     *
     * @return ObjectEntity|null
     */
    public function convertToGatewayObject(Entity $entity, ?array $body, string $id = null, Value $subresourceOf = null, ?ObjectEntity $objectEntity = null, string $url = null, array $sessionData = []): ?ObjectEntity
    {
        // Always make sure we have a gateway and endpoint on this Entity.
        if (!$url && (!$entity->getSource() || !$entity->getSource()->getLocation() || !$entity->getEndpoint() || !$entity->getSource()->getIsEnabled())) {
//            var_dump('No url or gateway+endpoint');
            return null; //Or false or error? //todo?
        }
        // If we have no $body we should use id to look for an extern object, if it exists get it and convert it to ObjectEntity in the gateway
        if (!$body) {
            if (!$id) {
                // If we have no $body or $id
//                var_dump('No id');
                return null; //Or false or error? //todo?
            } else {
                $component = $this->gatewayService->gatewayToArray($entity->getGateway());
                $url = !empty($url) ? $url : $entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id;
                $response = $this->commonGroundService->callService($component, $url, '', [], $entity->getGateway()->getHeaders(), false, 'GET');
                // if no resource with this $id exists... (callservice returns array on error)
                if (is_array($response)) {
//                    var_dump($response); //Throw error? //todo?
                    return null; //Or false or error? //todo?
                }

                // create log
                $content = $response->getBody()->getContents();
                $status = $response->getStatusCode();
                $responseLog = new Response($content, $status, $entity->getGateway()->getHeaders());
                $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 9, $content, null, 'out');

                $body = json_decode($content, true);
                if (array_key_exists('envelope', $entity->getItemConfig())) {
                    $itemConfigEnvelope = explode('.', $entity->getItemConfig()['envelope']);
                    foreach ($itemConfigEnvelope as $item) {
                        $body = $body[$item];
                    }
                }
            }
        } elseif (!$id) {
            if (array_key_exists('id', $entity->getCollectionConfig())) {
                $id = $body;
                $itemConfigEnvelope = explode('.', $entity->getCollectionConfig()['id']);
                foreach ($itemConfigEnvelope as $item) {
                    $id = $id[$item];
                }
            } else {
                // todo: use another way to find the extern object by a primary key other than id. (maybe multiple fields together)
                $id = 'ThisObjectHasNoExternalId';
            }
        }

        // Filter out unwanted properties before converting extern object to a gateway ObjectEntity
        $availableBody = array_filter($body, function ($propertyName) use ($entity) {
            if ($entity->getAvailableProperties()) {
                return in_array($propertyName, $entity->getAvailableProperties());
            }

            return $entity->getAttributeByName($propertyName);
        }, ARRAY_FILTER_USE_KEY);

        if ($entity->getExternMappingIn()) {
            $availableBody = $this->translationService->dotHydrator($availableBody, $availableBody, $entity->getExternMappingIn());
        }

        // These following if check has no effect if this function (convertToGatewayObject) is called from the (old) ValidationService. Because there we already checked if an ObjectEntity exists with the given id and if so, this convertToGatewayObject function is never called!
        // Check if there already exists an objectEntity with this id as externalId
        if (!$object = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'externalId' => $id])) {
            $object = new ObjectEntity();
            $object->setEntity($entity);

            // Set the externalId & uri if we have an externalId.
            if ($id !== 'ThisObjectHasNoExternalId') {
                $object->setExternalId($id);
                $object->setUri($entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id);
            }
        }
        if (!is_null($subresourceOf)) {
            $object->addSubresourceOf($subresourceOf);
        }

        // If extern object has dateCreated & dateModified, set them for this new ObjectEntity
        if (key_exists('dateCreated', $body)) {
            $object->setDateCreated(new DateTime($body['dateCreated']));
        }
        if (key_exists('date_created', $body)) {
            $object->setDateCreated(new DateTime($body['date_created']));
        }
        if (key_exists('dateModified', $body)) {
            $object->setDateModified(new DateTime($body['dateModified']));
        }
        if (key_exists('date_modified', $body)) {
            $object->setDateModified(new DateTime($body['date_modified']));
        }

        // Set application for this object
        if ($this->session->get('application') || array_key_exists('application', $sessionData)) {
            $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application') ?? $sessionData['application']]);
            $object->setApplication(!empty($application) ? $application : null); // Default application (can be changed after this if needed)
        }

        // Set organization (& application) for this object
        // If extern object has a property organization, use that organization // TODO: only use it if it is also saved inside the gateway? (so from $availableBody, or only if it is an actual Entity type?)
        if ($entity->getFunction() === 'organization') {
            $object = $this->functionService->createOrganization($object, $object->getUri(), $body['type']);
        }
        if (!$object->getOrganization()) {
            if (key_exists('organization', $body) && !empty($body['organization'])) {
                $object->setOrganization($body['organization']);
            } elseif (count($object->getSubresourceOf()) > 0 && !empty($object->getSubresourceOf()->first()->getObjectEntity()->getOrganization())) {
                $object->setOrganization($object->getSubresourceOf()->first()->getObjectEntity()->getOrganization());
                if (!is_null($object->getSubresourceOf()->first()->getObjectEntity()->getApplication())) {
                    $object->setApplication($object->getSubresourceOf()->first()->getObjectEntity()->getApplication());
                }
            } else {
                $object->setOrganization($this->session->get('activeOrganization') ?? (array_key_exists('activeOrganization', $sessionData) ? $sessionData['activeOrganization'] : null));
            }
        }

        $object = $this->checkAttributes($object, $availableBody, $objectEntity);

//        var_dump($object->getExternalId());
//        if ($object->getHasErrors()) {
//            var_dump($object->getErrors());
//        }
        // For in the rare case that a body contains the same uuid of an extern object more than once we need to persist and flush this ObjectEntity in the gateway.
        // Because if we do not do this, multiple ObjectEntities will be created for the same extern object.
        // Or if we run convertEntityObjects and multiple extern objects have the same (not yet in gateway) subresource.
        if ((is_null($objectEntity) || !$objectEntity->getHasErrors()) && !$object->getHasErrors()) {
//            var_dump('persist and flush');
            // todo: set owner with: $this->objectEntityService->handleOwner($newObject); // Do this after all CheckAuthorization function calls
            $this->em->persist($object);
            if ($id === 'ThisObjectHasNoExternalId' || !$object->getUri()) {
                $uri = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' ? 'https://'.$_SERVER['HTTP_HOST'] : 'http://localhost';
                $object->setUri($uri.'/admin/object_entities/'.$object->getId());
            }
            $this->em->flush(); // Needed here! read comment above if statement!
            $this->functionService->removeResultFromCache = [];
            $this->functionService->removeResultFromCache($object);
            $this->notify($object, 'Create'); // TODO: use promises instead of this function?
        }

        return $object;
    }

    /**
     * @TODO docs
     *
     * @param string $id
     *
     * @throws InvalidArgumentException
     *
     * @return ObjectEntity|null
     */
    public function syncObjectEntity(string $id): ?ObjectEntity
    {
        // todo: sync should work both ways, now we only sync from extern -> gateway
        // todo: if we want to sync the other way around we could/should use promises (from gateway -> extern)
        // todo: we can't sync both ways at the same time? How to determine which way has priority above the other?

        // todo: And what if the object no longer exists when trying to sync? delete it?

        // Should we support externalId as $id input option?
        $objectEntity = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id' => $id]);

        if ($objectEntity instanceof ObjectEntity && $objectEntity->getExternalId()) {
            $objectEntity = $this->convertToGatewayObject($objectEntity->getEntity(), null, $objectEntity->getExternalId());
        }

        return $objectEntity;
    }

    // TODO: duplicate with other notify functions in validationService & objectEntityService.
    /**
     * @param ObjectEntity $objectEntity
     * @param string       $method
     */
    private function notify(ObjectEntity $objectEntity, string $method)
    {
        if (!$this->commonGroundService->getComponent('nrc')) {
            return;
        }
        $topic = $objectEntity->getEntity()->getName();
        switch ($method) {
            case 'POST':
                $action = 'Create';
                break;
            case 'PUT':
            case 'PATCH':
                $action = 'Update';
                break;
            case 'DELETE':
                $action = 'Delete';
                break;
        }
        if (isset($action)) {
            $notification = [
                'topic'    => $topic,
                'action'   => $action,
                'resource' => $objectEntity->getUri(),
                'id'       => $objectEntity->getExternalId(),
            ];
            if (!$objectEntity->getUri()) {
                //                var_dump('Couldn\'t notifiy for object, because it has no uri!');
                //                var_dump('Id: '.$objectEntity->getId());
                //                var_dump('ExternalId: '.$objectEntity->getExternalId() ?? null);
                //                var_dump($notification);
                return;
            }
            $this->commonGroundService->createResource($notification, ['component' => 'nrc', 'type' => 'notifications'], false, true, false);
        }
    }

    /**
     * @TODO docs
     *
     * @param ObjectEntity      $newObject
     * @param array             $body
     * @param ObjectEntity|null $objectEntity
     *
     * @throws Exception|InvalidArgumentException
     *
     * @return ObjectEntity
     */
    private function checkAttributes(ObjectEntity $newObject, array $body, ?ObjectEntity $objectEntity): ObjectEntity
    {
        $entity = $newObject->getEntity();

        // Loop through entity attributes if we find a value for this attribute from extern object, set it, if not but is for example required, set to null.
        foreach ($entity->getAttributes() as $attribute) {
            // Only save the attributes that are used.
            if (!is_null($entity->getUsedProperties()) && !in_array($attribute->getName(), $entity->getUsedProperties())) {
                continue;
            }

            // Check if we have a value ( a value is given in the post body for this attribute, can be null )
            // Else if check if a defaultValue is set (TODO: defaultValue should maybe be a Value object, so that defaultValue can be something else than a string)
            // And else set to null. (even if $attribute is required)
            $value = key_exists($attribute->getName(), $body) ? $body[$attribute->getName()] : $attribute->getDefaultValue() ?? null;
            if ($attribute->getMultiple()) {
                // If multiple, this should be an array
                if (!is_array($value)) {
                    // 'Expects array, '.gettype($value).' given. (Multiple is set for this attribute)'
                    $newObject->setValue($attribute, null);
                    continue;
                }
                // Check for array of unique items TODO: is setting it to null the correct solution here?
                if ($attribute->getUniqueItems() && count(array_filter(array_keys($value), 'is_string')) == 0) {
                    // TODOmaybe:check this in another way so all kinds of arrays work with it.
                    $containsStringKey = false;
                    foreach ($value as $arrayItem) {
                        if (is_array($arrayItem) && count(array_filter(array_keys($arrayItem), 'is_string')) > 0) {
                            $containsStringKey = true;
                            break;
                        }
                    }
                    if (!$containsStringKey && count($value) !== count(array_unique($value))) {
//                        'Must be an array of unique items'
                        $newObject->setValue($attribute, null);
                        continue;
                    }
                }

                // Then validate all items in this array
                if ($attribute->getType() == 'object') {
                    // This is an array of objects
                    $valueObject = $newObject->getValueObject($attribute);
                    foreach ($value as $key => $object) {
                        // $key could be used for addError with attributeName = $attribute->getName().'['.$key.']'
                        $this->addObjectToValue($attribute, $object, $valueObject, $objectEntity);
                    }
                } elseif ($attribute->getType() == 'file') {
                    // TODO? or is null ok?
                    $newObject->setValue($attribute, null);
                    continue;
                } else {
                    foreach ($value as &$item) {
                        $item = $this->checkAttribute($item, $attribute, $newObject, $objectEntity);
                    }
                }
            } else {
                $value = $this->checkAttribute($value, $attribute, $newObject, $objectEntity);
            }

            if ($attribute->getMustBeUnique()) {
                // todo! (should we actually throw an error in this case? or also just set value to null?)
            }

            // if no errors we can set the value (for type object this is already done in validateAttributeType, other types we do it here,
            // because when we use validateAttributeType to validate items in an array, we dont want to set values for that)
            if (!$newObject->getHasErrors() && $attribute->getType() != 'object' && $attribute->getType() != 'file') {
                $newObject->setValue($attribute, $value);
            }
        }

        return $newObject;
    }

    /**
     * @TODO docs
     *
     * @param $value
     * @param Attribute         $attribute
     * @param ObjectEntity      $newObject
     * @param ObjectEntity|null $objectEntity
     *
     * @throws Exception|InvalidArgumentException
     *
     * @return string|null
     */
    private function checkAttribute($value, Attribute $attribute, ObjectEntity $newObject, ?ObjectEntity $objectEntity)
    {
        // Check if value is an array
        if (is_array($value) && $attribute->getType() != 'object' && $attribute->getType() != 'file') {
//            var_dump('Expects '.$attribute->getType().', array given. (Multiple is not set for this attribute)');
            return null;
        }
        // Check for enums TODO: is setting it to null the correct solution here?
        if ($attribute->getEnum() && !in_array(strtolower($value), array_map('strtolower', $attribute->getEnum())) && $attribute->getType() != 'object' && $attribute->getType() != 'boolean') {
//            var_dump('Must be one of the following values: ['.implode(', ', array_map('strtolower', $attribute->getEnum())).'] ('.strtolower($value).' is not).');
            return null;
        }

        // Switch for attribute types
        switch ($attribute->getType()) {
            case 'object':
                // First get the valueObject for this attribute
                $valueObject = $newObject->getValueObject($attribute);

                $value = $this->addObjectToValue($attribute, $value, $valueObject, $objectEntity);
                if ($value === null) {
                    $newObject->setValue($attribute, null);
                }

                break;
            case 'file':
                // TODO? or is null ok?
                $newObject->setValue($attribute, null);
                break;
            case 'date':
            case 'datetime':
                try {
                    new DateTime($value);
                } catch (Exception $e) {
                    //                    'Expects '.$attribute->getType().' (ISO 8601 datetime standard), failed to parse string to DateTime. ('.$value.')'
                    $value = null;
                }
            break;
            case 'boolean':
                if (!is_bool($value)) {
                    $value = null;
                }
                break;
            case 'number':
                if (!is_integer($value) && !is_float($value) && gettype($value) != 'float' && gettype($value) != 'double') {
                    $value = null;
                }
                break;
            case 'integer':
                if (!is_integer($value)) {
                    $value = null;
                }
                break;
            case 'string':
                if (!is_string($value)) {
                    $value = null;
                }
                break;
            default:
                $newObject->addError($attribute->getName(), 'Has an an unknown type: ['.$attribute->getType().']');
        }

        // Check the format of this attribute
        if ($attribute->getFormat() != null) {
            // todo? validate / check AttributeFormat ?
        }

        return $value;
    }

    /**
     * @TODO docs
     *
     * @param Attribute $attribute
     * @param $value
     * @param Value             $valueObject
     * @param ObjectEntity|null $objectEntity
     *
     * @throws Exception|InvalidArgumentException
     *
     * @return array|false|string|null
     */
    private function addObjectToValue(Attribute $attribute, $value, Value $valueObject, ?ObjectEntity $objectEntity)
    {
        // If this object is given as a uuid (string) it should be valid
        if (is_string($value) && Uuid::isValid($value) == false) {
            // TODO: support /$attribute->getObject()->getEndpoint()/uuid?
            if ($value == $attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($value)) {
                $value = $this->commonGroundService->getUuidFromUrl($value);
            } else {
                // We should also allow commonground Uri's like: https://opentest.izaaksuite.nl/api/v1/statussen/8578f55b-1df7-4620-af55-daafd0dc5bf3 OR https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
                $subObject = $this->convertToGatewayObject($attribute->getObject(), null, $value, $valueObject, $objectEntity, $value);

                if (!$subObject) {
//                var_dump('The given value ('.$value.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).');
                    return null; // set $value to null
                }

                // Object toevoegen
                if (!$attribute->getMultiple()) {
                    $valueObject->getObjects()->clear(); // We start with a default object
                }
                $valueObject->addObject($subObject);

                return $value;
            }
            $bodyForNewObject = null;
        } elseif (is_array($value)) {
            // If not string but array use $value['id'] and $value as $body for find with externalId or convertToGatewayObject
            $bodyForNewObject = $value;
            if (array_key_exists('envelope', $attribute->getObjectConfig())) {
                $objectConfigEnvelope = explode('.', $attribute->getObjectConfig()['envelope']);
                foreach ($objectConfigEnvelope as $item) {
                    $bodyForNewObject = $bodyForNewObject[$item];
                }
            }
            $objectConfigId = explode('.', $attribute->getObjectConfig()['id']);
            foreach ($objectConfigId as $item) {
                $value = $value[$item];
            }
            // TODO: what if we have no existing id key?
        } else {
//            var_dump('The given value ('.$value.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).');
            return null; // set $value to null
        }

        // Look for an existing ObjectEntity with its externalId set to this string, else look in external component with this uuid.
        // Always create a new ObjectEntity if we find an exernal object but it has no ObjectEntity yet.
        if (!$subObject = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'externalId' => $value])) {
            // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside of the gateway for an existing object.
            $subObject = $this->convertToGatewayObject($attribute->getObject(), $bodyForNewObject, $value, $valueObject, $objectEntity);
            if (!$subObject) {
//                var_dump('Could not find an object with id '.$value.' of type '.$attribute->getObject()->getName());
                return null; // set $value to null
            }
        }

        // Object toevoegen
        if (!$attribute->getMultiple()) {
            $valueObject->getObjects()->clear(); // We start with a default object
        }
        $valueObject->addObject($subObject);

        return $value;
    }
}
