<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ConvertToGatewayService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $em;
    private SessionInterface $session;
    private GatewayService $gatewayService;
    private FunctionService $functionService;
    private LogService $logService;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, SessionInterface $session, GatewayService $gatewayService, FunctionService $functionService, LogService $logService)
    {
        $this->commonGroundService = $commonGroundService;
        $this->em = $entityManager;
        $this->session = $session;
        $this->gatewayService = $gatewayService;
        $this->functionService = $functionService;
        $this->logService = $logService;
    }

    /**
     * @param Entity $entity
     *
     * @throws Exception
     *
     * @return void|null
     */
    public function convertEntityObjects(Entity $entity)
    {
        // Make sure we have a gateway and endpoint on this Entity.
        if (!$entity->getGateway() || !$entity->getGateway()->getLocation() || !$entity->getEndpoint()) {
            return null; //Or false or error? //todo?
        }

        // Get all objects for this Entity that exist outside the gateway
        $collectionConfigResults = explode('.', $entity->getCollectionConfig()['results']);
        if (array_key_exists('paginationNext', $entity->getCollectionConfig())) {
            $collectionConfigPaginationNext = explode('.', $entity->getCollectionConfig()['paginationNext']);
        }
        $component = $this->gatewayService->gatewayToArray($entity->getGateway());
        $url = $entity->getGateway()->getLocation().'/'.$entity->getEndpoint();
        $totalExternObjects = $this->getExternObjects(['collectionConfigResults' => $collectionConfigResults, 'collectionConfigPaginationNext' => $collectionConfigPaginationNext, 'headers' => $entity->getGateway()->getHeaders()], $component, $url);
//        var_dump('Found total extern objects = '.count($totalExternObjects));

        // Loop through all extern objects and check if they have an object in the gateway, if not create one.
        $newGatewayObjects = new ArrayCollection();
        $collectionConfigEnvelope = [];
        if (array_key_exists('envelope', $entity->getCollectionConfig())) {
            $collectionConfigEnvelope = explode('.', $entity->getCollectionConfig()['envelope']);
        }
        $collectionConfigId = explode('.', $entity->getCollectionConfig()['id']);
        foreach ($totalExternObjects as &$externObject) {
            $id = $externObject;
            // Make sure to get this item from the correct place in $externObject
            foreach ($collectionConfigEnvelope as $item) {
                $externObject = $externObject[$item];
            }
            // Make sure to get id of this item from the correct place in $externObject
            foreach ($collectionConfigId as $item) {
                $id = $id[$item];
            }
            if (!$this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'externalId' => $id])) {
                // Convert this object to a gateway object
                $object = $this->convertToGatewayObject($entity, $externObject, $id);
                if ($object) {
                    $newGatewayObjects->add($object);
                }
            }
        }
//        var_dump('New gateway objects = '.count($newGatewayObjects));

        // Now also find all objects that exist in the gateway but not outside the gateway on the extern component.
        //TODO make sure to get all id's from the correct place with $entity->getCollectionConfig()['id'] !!!
        $externObjectIds = array_column($totalExternObjects, 'id');
        $onlyInGateway = $entity->getObjectEntities()->filter(function (ObjectEntity $object) use ($externObjectIds) {
            return !in_array($object->getExternalId(), $externObjectIds) && !in_array($this->commonGroundService->getUuidFromUrl($object->getUri()), $externObjectIds);
        });

        // Delete these $onlyInGateway objectEntities ?
        foreach ($onlyInGateway as $item) {
//            var_dump($item->getId());
            $this->em->remove($item);
        }
//        var_dump('Deleted gateway objects = '.count($onlyInGateway));

        $this->em->flush();
    }

    /**
     * Get all objects for this Entity that exist outside the gateway.
     *
     * @param array  $config             array with collectionConfigResults, collectionConfigPaginationNext & headers TODO: also add query params?
     * @param array  $component
     * @param string $url
     * @param array  $totalExternObjects
     * @param int    $page
     *
     * @return array
     */
    private function getExternObjects(array $config, array $component, string $url, array $totalExternObjects = [], int $page = 1): array
    {
        $firstResponse = $response = json_decode($this->commonGroundService->callService($component, $url, '', ['page'=>$page], $config['headers'], false, 'GET')->getBody()->getContents(), true);
        // Now get response from the correct place in the response
        foreach ($config['collectionConfigResults'] as $item) {
            $response = $response[$item];
        }

        // Add it to total result
        $totalExternObjects = array_merge($totalExternObjects, $response);

        // Check if we have pagination and should repeat for the next page
        if (isset($config['collectionConfigPaginationNext'])) {
            $paginationNext = $firstResponse;
            foreach ($config['collectionConfigPaginationNext'] as $item) {
                if (!isset($paginationNext[$item])) {
                    $paginationNext = false;
                } else {
                    $paginationNext = $paginationNext[$item];
                }
            }
        }
        // Repeat if we have pagination and if there is a next page
        if (isset($paginationNext) && $paginationNext) {
            return $this->getExternObjects($config, $component, $url, $totalExternObjects, $page + 1);
        }
//        var_dump('pages: '. $page);

        return $totalExternObjects;
    }

    /**
     * @param Entity            $entity
     * @param array|null        $body
     * @param string|null       $id
     * @param Value|null        $subresourceOf
     * @param ObjectEntity|null $objectEntity  a main objectEntity this new OE will be part of, used to check for errors before flushing new OE.
     *
     * @throws Exception
     *
     * @return ObjectEntity|null
     */
    public function convertToGatewayObject(Entity $entity, ?array $body, string $id = null, Value $subresourceOf = null, ?ObjectEntity $objectEntity = null): ?ObjectEntity
    {
        // Always make sure we have a gateway and endpoint on this Entity.
        if (!$entity->getGateway() || !$entity->getGateway()->getLocation() || !$entity->getEndpoint()) {
            return null; //Or false or error? //todo?
        }

        // If we have no $body we should use id to look for an extern object, if it exists get it and convert it to ObjectEntity in the gateway
        if (!$body) {
            if (!$id) {
                // If we have no $body or $id
                return null; //Or false or error? //todo?
            } else {
                $component = $this->gatewayService->gatewayToArray($entity->getGateway());
                $url = $entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id;
                $response = $this->commonGroundService->callService($component, $url, '', [], $entity->getGateway()->getHeaders(), false, 'GET');
                // if no resource with this $id exists... (callservice returns array on error)
                if (is_array($response)) {
                    return null; //Or false or error? //todo?
                }

                // create log
                $content = $response->getBody()->getContents();
                $status = $response->getStatusCode();
                $responseLog = new Response($content, $status, $entity->getGateway()->getHeaders());
                $this->logService->saveLog($this->logService->makeRequest(), $responseLog, $content, null, 'out');

                $body = json_decode($content, true);
                if (array_key_exists('envelope', $entity->getItemConfig())) {
                    $itemConfigEnvelope = explode('.', $entity->getItemConfig()['envelope']);
                    foreach ($itemConfigEnvelope as $item) {
                        $body = $body[$item];
                    }
                }
            }
        } elseif (!$id) {
            $id = $body;
            $itemConfigEnvelope = explode('.', $entity->getCollectionConfig()['id']);
            foreach ($itemConfigEnvelope as $item) {
                $id = $id[$item];
            }
        }

        // Filter out unwanted properties before converting extern object to a gateway ObjectEntity
        $availableBody = array_filter($body, function ($propertyName) use ($entity) {
            if ($entity->getAvailableProperties()) {
                return in_array($propertyName, $entity->getAvailableProperties());
            }

            return $entity->getAttributeByName($propertyName);
        }, ARRAY_FILTER_USE_KEY);

        // These following if check has no effect if this function (convertToGatewayObject) is called from the (old) ValidationService. Because there we already checked if an ObjectEntity exists with the given id and if so, this convertToGatewayObject function is never called!
        // Check if there already exists an objectEntity with this id as externalId
        if (!$object = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'externalId' => $id])) {
            $object = new ObjectEntity();
            $object->setEntity($entity);

            // Set the externalId, uri, organization and application.
            $object->setExternalId($id);
            $object->setUri($entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id);
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
        if ($this->session->get('application')) {
            $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')->getId()->toString()]);
            if (!empty($application)) {
                $object->setApplication($application); // Default application (can be changed after this if needed)
            }
        }

        // Set organization (& application) for this object
        // If extern object has a property organization, use that organization // TODO: only use it if it is also saved inside the gateway? (so from $availableBody, or only if it is an actual Entity type?)
        if ($entity->getFunction() === 'organization') {
            $object = $this->functionService->createOrganization($object, $object->getUri(), $body['type']);
        } elseif (key_exists('organization', $body) && !empty($body['organization'])) {
            $object->setOrganization($body['organization']);
        } elseif (count($object->getSubresourceOf()) > 0 && !empty($object->getSubresourceOf()->first()->getObjectEntity()->getOrganization())) {
            $object->setOrganization($object->getSubresourceOf()->first()->getObjectEntity()->getOrganization());
            if (!is_null($object->getSubresourceOf()->first()->getObjectEntity()->getApplication())) {
                $object->setApplication($object->getSubresourceOf()->first()->getObjectEntity()->getApplication());
            }
        } else {
            $object->setOrganization($this->session->get('activeOrganization'));
        }

        $object = $this->checkAttributes($object, $availableBody, $objectEntity);

//        var_dump($newObject->getExternalId());
//        if ($newObject->getHasErrors()) {
//            var_dump($newObject->getErrors());
//        }

        // For in the rare case that a body contains the same uuid of an extern object more than once we need to persist and flush this ObjectEntity in the gateway.
        // Because if we do not do this, multiple ObjectEntities will be created for the same extern object.
        // Or if we run convertEntityObjects and multiple extern objects have the same (not yet in gateway) subresource.
        if ((is_null($objectEntity) || !$objectEntity->getHasErrors()) && !$object->getHasErrors()) {
//            var_dump('persist and flush');
            $this->em->persist($object);
            $this->em->flush(); // Needed here! read comment above!
            $this->notify($object, 'Create');
        }

        return $object;
    }

    public function syncObjectEntity(string $id): ?ObjectEntity
    {
        // Should we support externalId as $id input option?
        $objectEntity = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id' => $id]);

        if ($objectEntity instanceof ObjectEntity && $objectEntity->getExternalId()) {
            $objectEntity = $this->convertToGatewayObject($objectEntity->getEntity(), null, $objectEntity->getExternalId());
        }

        return $objectEntity;
    }

    // TODO: duplicate with notify function in validationService, move this to a notificationService
    /**
     * @param ObjectEntity $objectEntity
     * @param string       $method
     */
    private function notify(ObjectEntity $objectEntity, string $method)
    {
        $topic = $objectEntity->getEntity()->getName();
        switch ($method) {
            case 'POST':
                $action = 'Create';
                break;
            case 'PUT':
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
                    $newObject->getValueByAttribute($attribute)->setValue(null);
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
                        $newObject->getValueByAttribute($attribute)->setValue(null);
                        continue;
                    }
                }

                // Then validate all items in this array
                if ($attribute->getType() == 'object') {
                    // This is an array of objects
                    $valueObject = $newObject->getValueByAttribute($attribute);
                    foreach ($value as $key => $object) {
                        // $key could be used for addError with attributeName = $attribute->getName().'['.$key.']'
                        $this->addObjectToValue($attribute, $object, $valueObject, $objectEntity);
                    }
                } elseif ($attribute->getType() == 'file') {
                    // TODO? or is null ok?
                    $newObject->getValueByAttribute($attribute)->setValue(null);
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
                $newObject->getValueByAttribute($attribute)->setValue($value);
            }
        }

        return $newObject;
    }

    /**
     * @param $value
     * @param Attribute         $attribute
     * @param ObjectEntity      $newObject
     * @param ObjectEntity|null $objectEntity
     *
     * @throws Exception
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
        if ($attribute->getEnum() && !in_array($value, $attribute->getEnum()) && $attribute->getType() != 'object' && $attribute->getType() != 'boolean') {
//            var_dump('Must be one of the following values: ['.implode(', ', $attribute->getEnum()).'] ('.$value.' is not).');
            return null;
        }

        // Switch for attribute types
        switch ($attribute->getType()) {
            case 'object':
                // First get the valueObject for this attribute
                $valueObject = $newObject->getValueByAttribute($attribute);

                $value = $this->addObjectToValue($attribute, $value, $valueObject, $objectEntity);
                if ($value === null) {
                    $newObject->getValueByAttribute($attribute)->setValue(null);
                }

                break;
            case 'file':
                // TODO? or is null ok?
                $newObject->getValueByAttribute($attribute)->setValue(null);
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
     * @param Attribute $attribute
     * @param $value
     * @param Value             $valueObject
     * @param ObjectEntity|null $objectEntity
     *
     * @throws Exception
     *
     * @return false|mixed|string|null
     */
    private function addObjectToValue(Attribute $attribute, $value, Value $valueObject, ?ObjectEntity $objectEntity)
    {
        // If this object is given as a uuid (string) it should be valid
        if (is_string($value) && Uuid::isValid($value) == false) {
            // We should also allow commonground Uri's like: https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
            // TODO: support /$attribute->getObject()->getEndpoint()/uuid?
            if ($value == $attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($value)) {
                $value = $this->commonGroundService->getUuidFromUrl($value);
            } else {
//                'The given value ('.$value.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).'
                return null; // set $value to null
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
//            'The given value ('.$value.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).'
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
