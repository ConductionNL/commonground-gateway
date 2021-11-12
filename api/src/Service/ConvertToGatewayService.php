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
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ConvertToGatewayService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $em;
    private SessionInterface $session;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, SessionInterface $session)
    {
        $this->commonGroundService = $commonGroundService;
        $this->em = $entityManager;
        $this->session = $session;
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
        if (!$entity->getGateway()->getLocation() || !$entity->getEndpoint()) {
            return null; //Or false or error? //todo?
        }

        // Get all objects for this Entity that exist outside the gateway
        $totalExternObjects = [];
        $page = 1;
        while (true) {
            $response = $this->commonGroundService->getResourceList($entity->getGateway()->getLocation().'/'.$entity->getEndpoint(), ['page'=>$page]);
            $totalExternObjects = array_merge($totalExternObjects, $response['hydra:member']);
            if (!isset($response['hydra:view']['hydra:next'])) {
                break;
            }
            $page += 1;
        }
//        var_dump('Found total extern objects = '.count($totalExternObjects));

        // Loop through all extern objects and check if they have an object in the gateway, if not create one.
        $newGatewayObjects = new ArrayCollection();
        foreach ($totalExternObjects as $externObject) {
            if (!$this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'externalId' => $externObject['id']])) {
                // Convert this object to a gateway object
                $object = $this->convertToGatewayObject($entity, $externObject);
                if ($object) {
                    $newGatewayObjects->add($object);
                }
            }
        }
//        var_dump('New gateway objects = '.count($newGatewayObjects));

        // Now also find all objects that exist in the gateway but not outside the gateway on the extern component.
        $externObjectIds = array_column($totalExternObjects, 'id');
        $onlyInGateway = $entity->getObjectEntities()->filter(function (ObjectEntity $object) use ($externObjectIds) {
            return !in_array($object->getExternalId(), $externObjectIds) && !in_array($this->commonGroundService->getUuidFromUrl($object->getUri()), $externObjectIds);
        });

        // Delete these $onlyInGateway objectEntities ?
        foreach ($onlyInGateway as $item) {
            $this->em->remove($item);
        }
//        var_dump('Deleted gateway objects = '.count($onlyInGateway));

        $this->em->flush(); // TODO: Do we need this here or not?
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
        if (!$entity->getGateway()->getLocation() || !$entity->getEndpoint()) {
            return null; //Or false or error? //todo?
        }

        // If we have no $body we should use id to look for an extern object, if it exists get it and convert it to ObjectEntity in the gateway
        if (!$body) {
            if (!$id || !$object = $this->commonGroundService->isResource($entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id)) {
                // If we have no $body or $id, or if no resource with this $id exists...
                return null; //Or false or error? //todo?
            } else {
                $body = $object;
            }
        }
        $id = $body['id'];

        // Filter out unwanted properties before converting extern object to a gateway ObjectEntity
        $availableBody = array_filter($body, function ($propertyName) use ($entity) {
            if ($entity->getAvailableProperties()) {
                return in_array($propertyName, $entity->getAvailableProperties());
            }

            return $entity->getAttributeByName($propertyName);
        }, ARRAY_FILTER_USE_KEY);

        $newObject = new ObjectEntity();
        $newObject->setEntity($entity);
        if (!is_null($subresourceOf)) {
            $newObject->addSubresourceOf($subresourceOf);
        }

        // Set the externalId, uri, organization and application.
        $newObject->setExternalId($id);
        $newObject->setUri($entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id);

        // If extern object has dateCreated & dateModified, set them for this new ObjectEntity
        if (key_exists('dateCreated', $body)) {
            $newObject->setDateCreated(new DateTime($body['dateCreated']));
        }
        if (key_exists('dateModified', $body)) {
            $newObject->setDateModified(new DateTime($body['dateModified']));
        }

        // Set organization for this object
        // If extern object has a property organization, use that organization // TODO: only use it if it is also saved inside the gateway? (so from $availableBody, or only if it is an actual Entity type?)
        if (key_exists('organization', $body) && !empty($body['organization'])) {
            $newObject->setOrganization($body['organization']);
        } elseif (count($newObject->getSubresourceOf()) > 0 && !empty($newObject->getSubresourceOf()->first()->getObjectEntity()->getOrganization())) {
            $newObject->setOrganization($newObject->getSubresourceOf()->first()->getObjectEntity()->getOrganization());
        } else {
            $newObject->setOrganization($this->session->get('activeOrganization'));
        }
//                $newObject->setApplication(); // TODO

        $newObject = $this->checkAttributes($newObject, $availableBody, $objectEntity);

        // For in the rare case that a body contains the same uuid of an extern object more than once we need to persist and flush this ObjectEntity in the gateway.
        // Because if we do not do this, multiple ObjectEntities will be created for the same extern object.
        // Or if we run convertEntityObjects and multiple extern objects have the same (not yet in gateway) subresource.
        if ((is_null($objectEntity) || !$objectEntity->getHasErrors()) && !$newObject->getHasErrors()) {
            $this->em->persist($newObject);
            $this->em->flush(); // Needed here! read comment above!
        }

        return $newObject;
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
            $value = $value['id']; // TODO: check if id key exists? and if not, what to do?
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
//                'Could not find an object with id '.$value.' of type '.$attribute->getObject()->getName()
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
