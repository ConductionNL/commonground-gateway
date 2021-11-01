<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\File;
use App\Entity\GatewayResponseLog;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Ramsey\Uuid\Uuid;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ValidationService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private GatewayService $gatewayService;
    private CacheInterface $cache;
    public $promises = []; //TODO: use ObjectEntity->promises instead!
    private AuthorizationService $authorizationService;

    public function __construct(
        EntityManagerInterface $em,
        CommonGroundService $commonGroundService,
        GatewayService $gatewayService,
        CacheInterface $cache,
        AuthorizationService $authorizationService
    ) {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->gatewayService = $gatewayService;
        $this->cache = $cache;
        $this->authorizationService = $authorizationService;
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param array        $post
     * @param bool         $createOEforExternObject Let's not create any promises if we are creating a new ObjectEntity in the gateway for an object that already exists outside the gateway and only an uuid and not an object is given for this.
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    public function validateEntity(ObjectEntity $objectEntity, array $post, bool $createOEforExternObject = false): ObjectEntity
    {
        $entity = $objectEntity->getEntity();
        foreach ($entity->getAttributes() as $attribute) {
            // Only save the attributes that are used.
            if (!is_null($objectEntity->getEntity()->getUsedProperties()) && !in_array($attribute->getName(), $objectEntity->getEntity()->getUsedProperties())) {
                if (key_exists($attribute->getName(), $post)) {
                    // throw an error if a value is given for a disabled attribute.
                    $objectEntity->addError($attribute->getName(), 'This attribute is disabled for this entity');
                }
                continue;
            }

            // Check if we have a value to validate ( a value is given in the post body for this attribute, can be null )
            if (key_exists($attribute->getName(), $post)) {
                $objectEntity = $this->validateAttribute($objectEntity, $attribute, $post[$attribute->getName()], $createOEforExternObject);
            }
            // Check if a defaultValue is set (TODO: defaultValue should maybe be a Value object, so that defaultValue can be something else than a string)
            elseif ($attribute->getDefaultValue()) {
                $objectEntity->getValueByAttribute($attribute)->setValue($attribute->getDefaultValue());
            }
            // Check if this field is nullable
            elseif ($attribute->getNullable()) {
                $objectEntity->getValueByAttribute($attribute)->setValue(null);
            }
            // Check if this field is required
            elseif ($attribute->getRequired()) {
                // If this field is of type object and if the ObjectEntity this field is part of, is itself...
                // ...a subresource of another objectEntity, lets check if we are dealing with a cascade loop.
                if ($attribute->getType() == 'object' && count($objectEntity->getSubresourceOf()) > 0) {
                    // Lets check if the Entity we expect for this field is also the same Entity as one of the parent ObjectEntities of this ObjectEntity
                    $parentValues = $objectEntity->getSubresourceOf()->filter(function (Value $valueObject) use ($attribute) {
                        return $valueObject->getObjectEntity()->getEntity() === $attribute->getObject();
                    });
                    // If we find at least 1 we know we are dealing with a loop.
                    // example: Object LearningNeed has a field Results and Object Result has a required field LearningNeed,
                    // if the Results of a LearningNeed can be cascaded these cascaded Results require a LearningNeed,
                    // but because of inversedBy the LearningNeed these Results expect will be set automatically.
                    if (count($parentValues) > 0) { // == 1? maybe
                        // So if we found a value in the 'parent values' of the ObjectEntity, with ->getObjectEntity()->getEntity()...
                        // ...equal to the Entity (->getObject) of this field / attribute. Get the attribute of this Value.
                        $parentValueAttribute = $parentValues->first()->getAttribute();
//                        var_dump($attribute->getName());
//                        var_dump('cascadeLoop');
//                        var_dump($parentValueAttribute->getName());
//                        var_dump($parentValueAttribute->getCascade());
//                        var_dump($parentValueAttribute->getInversedBy()->getName());
                        // Now lets make sure this attribute is of type object, has cascade on and is inversedBy the attribute of our current field.
                        if ($parentValueAttribute->getType() == 'object' && $parentValueAttribute->getCascade() && $parentValueAttribute->getInversedBy() == $attribute) {
                            // If so, skip throwing a 'is required' error, because after this validation this required field will be set because of InversedBy in the Value->addObject() function.
                            return $objectEntity;
                        }
                    }
                }
                $objectEntity->addError($attribute->getName(), 'This attribute is required');
            } else {
                // handling the setting to null of exisiting variables
                $objectEntity->getValueByAttribute($attribute)->setValue(null);
            }
        }
        // Check post for not allowed properties
        foreach ($post as $key=>$value) {
            if (!$entity->getAttributeByName($key) && $key != 'id') {
                $objectEntity->addError($key, 'Does not exist on this property');
            }
        }

        // Dit is de plek waarop we weten of er een api call moet worden gemaakt
        // $createOEforExternObject = Let's not create any promises if we are creating a new ObjectEntity in the gateway for an object that already...
        // ...exists outside the gateway and only an uuid and not an object is given for this. Preventing creation of a new object for an already existing one.
        if (!$createOEforExternObject && !$objectEntity->getHasErrors() && $objectEntity->getEntity()->getGateway()) {
            $promise = $this->createPromise($objectEntity, $post);
            $this->promises[] = $promise; //TODO: use ObjectEntity->promises instead!
            $objectEntity->addPromise($promise);
        }

        return $objectEntity;
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param $value
     * @param bool $createOEforExternObject Let's not check for scopes if we are creating a new ObjectEntity in the gateway for an object that already exists outside the gateway and only an uuid and not an object is given for this. There is no way a user can change this object, it is only added into the gateway with the already existing data outside the gateway!
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    private function validateAttribute(ObjectEntity $objectEntity, Attribute $attribute, $value, bool $createOEforExternObject = false): ObjectEntity
    {
        // Let's not check for scopes if we are creating a new ObjectEntity in the gateway for an object that already...
        // ...exists outside the gateway and only an uuid and not an object is given for this. There is no way a user...
        // ...can change this object, it is only added into the gateway with the already existing data outside the gateway!
        if (!$createOEforExternObject) {
            try {
                $this->authorizationService->checkAuthorization($this->authorizationService->getRequiredScopes($objectEntity->getUri() ? 'PUT' : 'POST', $attribute));
            } catch (AccessDeniedException $e) {
                $objectEntity->addError($attribute->getName(), $e->getMessage());
            }
        }

        // Check if value is null, and if so, check if attribute has a defaultValue and else if it is nullable
        if (is_null($value)) {
            if ($attribute->getDefaultValue()) {
                $objectEntity->getValueByAttribute($attribute)->setValue($attribute->getDefaultValue());
            } elseif (!$attribute->getNullable()) {
                $objectEntity->addError($attribute->getName(), 'Expects '.$attribute->getType().', '.gettype($value).' given. (Nullable is not set for this attribute)');
            } else {
                $objectEntity->getValueByAttribute($attribute)->setValue(null);
            }
            // We should not continue other validations after this!
            return $objectEntity;
        }

        if ($attribute->getMultiple()) {
            // If multiple, this is an array, validation for an array:
            $objectEntity = $this->validateAttributeMultiple($objectEntity, $attribute, $value);
        } else {
            // Multiple == false, so this should not be an array (unless it is an object)
            if (is_array($value) && $attribute->getType() != 'object' && $attribute->getType() != 'file') {
                $objectEntity->addError($attribute->getName(), 'Expects '.$attribute->getType().', array given. (Multiple is not set for this attribute)');

                // Lets not continue validation if $value is an array (because this will cause weird 500s!!!)
                return $objectEntity;
            }
            $objectEntity = $this->validateAttributeType($objectEntity, $attribute, $value);
            $objectEntity = $this->validateAttributeFormat($objectEntity, $attribute, $value);
        }

        if ($attribute->getMustBeUnique()) {
            $objectEntity = $this->validateAttributeUnique($objectEntity, $attribute, $value);
            // We should not continue other validations after this!
            if ($objectEntity->getHasErrors()) {
                return $objectEntity;
            }
        }

        // if no errors we can set the value (for type object this is already done in validateAttributeType, other types we do it here,
        // because when we use validateAttributeType to validate items in an array, we dont want to set values for that)
        if (!$objectEntity->getHasErrors() && $attribute->getType() != 'object' && $attribute->getType() != 'file') {
            $objectEntity->getValueByAttribute($attribute)->setValue($value);
        }

        return $objectEntity;
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param $value
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    private function validateAttributeUnique(ObjectEntity $objectEntity, Attribute $attribute, $value): ObjectEntity
    {
        $values = $attribute->getAttributeValues()->filter(function (Value $valueObject) use ($value) {
            switch ($valueObject->getAttribute()->getType()) {
                //TODO:
//                case 'object':
//                    return $valueObject->getObjects() == $value;
                case 'string':
                    return $valueObject->getStringValue() == $value;
                case 'number':
                    return $valueObject->getNumberValue() == $value;
                case 'integer':
                    return $valueObject->getIntegerValue() == $value;
                case 'boolean':
                    return $valueObject->getBooleanValue() == $value;
                case 'datetime':
                    return $valueObject->getDateTimeValue() == new DateTime($value);
                default:
                    return false;
            }
        });

        if (count($values) > 0 && !(count($values) == 1 && $objectEntity->getValueByAttribute($attribute)->getValue() == $value)) {
            if ($attribute->getType() == 'boolean') {
                $value = $value ? 'true' : 'false';
            }
            $objectEntity->addError($attribute->getName(), 'Must be unique, there already exists an object with this value: '.$value.'.');
        }

        return $objectEntity;
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param $value
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    private function validateAttributeMultiple(ObjectEntity $objectEntity, Attribute $attribute, $value): ObjectEntity
    {
        // If multiple, this is an array, validation for an array:
        if (!is_array($value)) {
            $objectEntity->addError($attribute->getName(), 'Expects array, '.gettype($value).' given. (Multiple is set for this attribute)');

            // Lets not continue validation if $value is not an array (because this will cause weird 500s!!!)
            return $objectEntity;
        }
        if ($attribute->getMinItems() && count($value) < $attribute->getMinItems()) {
            $objectEntity->addError($attribute->getName(), 'The minimum array length of this attribute is '.$attribute->getMinItems().'.');
        }
        if ($attribute->getMaxItems() && count($value) > $attribute->getMaxItems()) {
            $objectEntity->addError($attribute->getName(), 'The maximum array length of this attribute is '.$attribute->getMaxItems().'.');
        }
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
                $objectEntity->addError($attribute->getName(), 'Must be an array of unique items');
            }
        }

        // Then validate all items in this array
        if ($attribute->getType() == 'object') {
            // TODO: maybe move and merge all this code to the validateAttributeType function under type 'object'. NOTE: this code works very different!!!
            // This is an array of object
            $valueObject = $objectEntity->getValueByAttribute($attribute);
            foreach ($value as $key => $object) {
                if (!is_array($object)) {
                    // If we want to connect an existing object using a string uuid: "uuid"
                    if (is_string($object)) {
                        if (Uuid::isValid($object) == false) {
                            // We should also allow commonground Uri's like: https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
                            if ($object == $attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($object)) {
                                $object = $this->commonGroundService->getUuidFromUrl($object);
                            } else {
                                if (!array_key_exists($attribute->getName(), $objectEntity->getErrors())) {
                                    $objectEntity->addError($attribute->getName(), 'Multiple is set for this attribute. Expecting an array of objects (array, uuid or uri).');
                                }
                                $objectEntity->addError($attribute->getName().'['.$key.']', 'The given value ('.$object.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).');
                                continue;
                            }
                        }
                        // Look for an existing ObjectEntity with its id or externalId set to this string, else look in external component with this uuid.
                        // Always create a new ObjectEntity if we find an exernal object but it has no ObjectEntity yet.

                        // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
                        if (!$subObject = $this->em->getRepository('App:ObjectEntity')->find($object)) {
                            if (!$subObject = $this->em->getRepository('App:ObjectEntity')->findBy(['externalId' => $object])) {
                                // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside of the gateway for an existing object.
                                $subObject = $this->createOEforExternObject($attribute->getObject(), $object, $valueObject, $objectEntity);
                                if (!$subObject) {
                                    $objectEntity->addError($attribute->getName().'['.$key.']', 'Could not find an object with id '.$object.' of type '.$attribute->getObject()->getName());
                                    break;
                                }
                            } else {
                                // Found an object with externalId
                                $subObject = $subObject[0];
                            }
                        }

                        // object toevoegen
                        $valueObject->getObjects()->clear(); // We start with a deafult object //TODO: do we need this here?
                        $valueObject->addObject($subObject);
                        continue;
                    } else {
                        $objectEntity->addError($attribute->getName(), 'Multiple is set for this attribute. Expecting an array of objects (array or uuid).');
                        break;
                    }
                }
                // If we are doing a PUT with a subObject that contains an id, find the object with this id and update it.
                if (array_key_exists('id', $object)) {
                    if (!is_string($object['id']) || Uuid::isValid($object['id']) == false) {
                        $objectEntity->addError($attribute->getName().'['.$key.']', 'The given value ('.$object['id'].') is not a valid uuid.');
                        continue;
                    }
                    $subObject = $valueObject->getObjects()->filter(function (ObjectEntity $item) use ($object) {
                        return $item->getId() == $object['id'] || $item->getExternalId() == $object['id'];
                    });
                    if (count($subObject) == 0) {
                        // In the rare case that we are creating a new Gateway ObjectEntity for an object existing outside the gateway. (maybe even another ObjectEntity for a subresource of an extern object like this)
                        // (this can happen when an uuid is given for an attribute that expects an object and this object is only found outside the gateway)
                        // Than if gateway->location and endpoint are set on the attribute(->getObject) Entity, we should check for objects outside the gateway here.
                        $subObject = $this->createOEforExternObject($attribute->getObject(), $object['id'], $valueObject, $objectEntity);

                        if (!$subObject) {
                            $objectEntity->addError($attribute->getName(), 'Could not find an object with id '.$object['id'].' of type '.$attribute->getObject()->getName());
                            break;
                        }

                        $valueObject->addObject($subObject);
                        continue;
                    } elseif (count($subObject) > 1) {
                        $objectEntity->addError($attribute->getName(), 'Found more than 1 object with id '.$object['id'].' of type '.$attribute->getObject()->getName());
                        break;
                    } else {
                        $subObject = $subObject->first();
                    }
                }
                // If we are doing a PUT with a single subObject (and it contains no id) and the existing mainObject only has a single subObject, use the existing subObject and update that.
                elseif (count($value) == 1 && count($valueObject->getObjects()) == 1) {
                    $subObject = $valueObject->getObjects()->first();
                }
                // Create a new subObject (ObjectEntity)
                else {
                    //TODO: Lets do some cascade checks here?
                    $subObject = new ObjectEntity();

                    $subObject->setEntity($attribute->getObject());
                    $subObject->addSubresourceOf($valueObject);
                }

                $subObject = $this->validateEntity($subObject, $object);

                // We need to persist if this is a new ObjectEntity in order to set and getId to generate the uri...
                $this->em->persist($subObject);
                $subObject->setUri($this->createUri($subObject->getEntity()->getName(), $subObject->getId()));

//                $valueObject->setValue($subObject);
                $valueObject->addObject($subObject);
            }
        } elseif ($attribute->getType() == 'file') {
            // TODO: maybe move and merge all this code to the validateAttributeType function under type 'file'. NOTE: this code works very different!!!
            // This is an array of files
            $valueObject = $objectEntity->getValueByAttribute($attribute);
            foreach ($value as $key => $file) {
                // Validations
                if (!is_array($file)) {
                    $objectEntity->addError($attribute->getName(), 'Multiple is set for this attribute. Expecting an array of files (arrays).');
                    break;
                }
                if (!array_key_exists('base64', $file)) {
                    $objectEntity->addError($attribute->getName().'['.$key.'].base64', 'Expects an array with at least key base64 with a valid base64 encoded string value. (could also contain key filename)');
                    break;
                }

                // Validate (and create/update) this file
                $objectEntity = $this->validateFile($objectEntity, $attribute, $this->base64ToFileArray($file, $key));
            }
        } else {
            foreach ($value as $item) {
                $objectEntity = $this->validateAttributeType($objectEntity, $attribute, $item);
                $objectEntity = $this->validateAttributeFormat($objectEntity, $attribute, $value);
            }
        }

        return $objectEntity;
    }

    /**
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param Value        $valueObject
     * @param string       $id
     *
     * @throws Exception
     *
     * @return ObjectEntity|null
     */
    public function createOEforExternObject(Entity $entity, string $id, Value $valueObject = null, ObjectEntity $objectEntity = null): ?ObjectEntity
    {
        // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside of the gateway for an existing object.
        var_dump('create OE for extern object');
        if ($entity->getGateway()->getLocation() && $entity->getEndpoint()) {
            var_dump('url = '.$entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id);
//            try {
                $object = $this->commonGroundService->getResource($entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id);
//            } catch (Exception $exception) {
//                return null;
//            }
            var_dump($object);
//            if (isset($object)) {
//            if ($object = $this->commonGroundService->isResource($entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id)) {
                var_dump('found an extern resource');
                // Filter out unwanted properties before converting extern object to a gateway ObjectEntity
                $object = array_filter($object, function ($propertyName) use ($entity) {
                    if ($entity->getAvailableProperties()) {
                        return in_array($propertyName, $entity->getAvailableProperties());
                    }

                    return $entity->getAttributeByName($propertyName);
                }, ARRAY_FILTER_USE_KEY);
                $newSubObject = new ObjectEntity();
                $newSubObject->setEntity($entity);
                if (!is_null($valueObject)) {
                    $newSubObject->addSubresourceOf($valueObject);
                }

                // Set the externalId and uri.
                $newSubObject->setExternalId($id);
                $newSubObject->setUri($entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id);
                $object = $this->validateEntity($newSubObject, $object, true);

                // For in the rare case that a body contains the same uuid of an extern object more than once we need to persist and flush this ObjectEntity in the gateway.
                // Because if we do not do this, multiple ObjectEntities will be created for the same extern object. (externalId needs to be set!)
                if ((is_null($objectEntity) || !$objectEntity->getHasErrors()) && !$object->getHasErrors()) {
                    $this->em->persist($object);
                    $this->em->flush();
                }

                var_dump(gettype($object));
                var_dump($object->getUri());
                var_dump($object->getExternalId());
                return $object;
//            }
        }
        var_dump('no new OE, returned null');

        return null;
    }

    /**
     * This function hydrates an object(tree) (new style).
     *
     * The act of hydrating means filling objects with values from a post
     *
     * @param ObjectEntity $objectEntity
     *
     * @return ObjectEntity
     */
    private function hydrate(ObjectEntity $objectEntity, $post): ObjectEntity
    {
        $entity = $objectEntity->getEntity();
        foreach ($entity->getAttributes() as $attribute) {
            // Check if we have a value to validate ( a value is given in the post body for this attribute, can be null )
            if (key_exists($attribute->getName(), $post)) {
                $objectEntity = $objectEntity->getValueByAttribute($attribute)->setValue($post[$attribute->getName()]);
            }
            // Check if a defaultValue is set (TODO: defaultValue should maybe be a Value object, so that defaultValue can be something else than a string)
            elseif ($attribute->getDefaultValue()) {
                $objectEntity = $objectEntity->getValueByAttribute($attribute)->setValue($attribute->getDefaultValue());
            }
            /* @todo this feels wierd, should we PUT "value":null if we want to delete? */
            //else {
            //    // handling the setting to null of exisiting variables
            //    $objectEntity->getValueByAttribute($attribute)->setValue(null);
            //}
        }

        // Check post for not allowed properties
        foreach ($post as $key=>$value) {
            if ($key != 'id' && !$entity->getAttributeByName($key)) {
                $objectEntity->addError($key, 'Property '.(string) $key.' not exist on this object');
            }
        }
    }

    /* @todo ik mis nog een set value functie die cascading en dergenlijke afhandeld */

    /**
     * This function validates an object (new style).
     *
     * @param ObjectEntity $objectEntity
     *
     * @return ObjectEntity
     */
    private function validate(ObjectEntity $objectEntity): ObjectEntity
    {
        // Lets loop trough the objects values and check those
        foreach ($objectEntity->getObjectValues() as $value) {
            if ($value->getAttribute()->getMultiple()) {
                foreach ($value->getValue() as $key=>$tempValue) {
                    $objectEntity = $this->validateValue($value, $tempValue, $key);
                }
            } else {
                $objectEntity = $this->validateValue($value, $value->getValue());
            }
        }

        // It is now here that we know if we have errors or not

        /* @todo lets create an promise */

        return $objectEntity;
    }

    /**
     * This function validates a given value for an object (new style).
     *
     * @param Value $valueObject
     * @param $value
     *
     * @return ObjectEntity
     */
    private function validateValue(Value $valueObject, $value): ObjectEntity
    {
        // Set up the validator
        $validator = new Validator();
        $objectEntity = $value->getObjectEntity();

        $validator = $this->validateType($valueObject, $validator);
        $validator = $this->validateFormat($valueObject, $validator, $value);
        $validator = $this->validateValidations($valueObject, $validator);

        // Lets roll the actual validation
        try {
            $validator->assert($value);
        } catch (NestedValidationException $exception) {
            $objectEntity->addError($value->getAttribute()->getName(), $exception->getMessages());
        }

        return $objectEntity;
    }

    /**
     * This function handles the type part of value validation (new style).
     *
     * @param ObjectEntity $objectEntity
     * @param $value
     * @param array     $validations
     * @param Validator $validator
     *
     * @return Validator
     */
    private function validateType(Value $valueObject, Validator $validator, $value): Validator
    {
        // if no type is provided we dont validate
        if ($type = $valueObject->getAttribute()->getType() == null) {
            return $validator;
        }
        /* @todo we realy might consider throwing an error */

        // Let be a bit compasionate and compatable
        $type = str_replace(['integer', 'boolean', 'text'], ['int', 'bool', 'string'], $type);

        // In order not to allow any respect/validation function to be called we explicatly call those containing formats
        $basicTypes = ['bool', 'string', 'int', 'array', 'float'];

        // new route
        if (in_array($type, $basicTypes)) {
            $validator->type($type);
        } else {
            // The are some uncoverd types so we will have to add those manualy
            switch ($type) {
                case 'date':
                    $validator->date();
                    break;
                case 'datetime':
                    $validator->dateTime();
                    break;
                case 'number':
                    $validator->number();
                    break;
                case 'object':
                    // We dont validate an object normaly but hand it over to its own validator
                    $this->validate($value);
                    break;
                default:
                    // we should never end up here
                    /* @todo throw an custom error */
            }
        }

        return $validator;
    }

    /**
     * Format validation (new style).
     *
     * Format validation is done using the [respect/validation](https://respect-validation.readthedocs.io/en/latest/) packadge for php
     *
     * @param Value     $valueObject
     * @param Validator $validator
     *
     * @return Validator
     */
    private function validateFormat(Value $valueObject, Validator $validator): Validator
    {
        // if no format is provided we dont validate
        if ($format = $valueObject->getAttribute()->getFormat() == null) {
            return $validator;
        }

        // Let be a bit compasionate and compatable
        $format = str_replace(['telephone'], ['phone'], $format);

        // In order not to allow any respect/validation function to be called we explicatly call those containing formats
        $allowedFormats = ['countryCode', 'bsn', 'url', 'uuid', 'email', 'phone', 'json'];

        // new route
        if (in_array($format, $allowedFormats)) {
            $validator->$format();
        }

        return $validator;
    }

    /**
     * This function handles the validator part of value validation (new style).
     *
     * @param Value     $valueObject
     * @param Validator $validator
     *
     * @throws Exception
     *
     * @return Validator
     */
    private function validateValidations(Value $valueObject, Validator $validator): Validator
    {
        $validations = $valueObject->getAttribute()->getValidations();
        foreach ($validations as $validation => $config) {
            switch ($validation) {
                case 'multipleOf':
                    $validator->multiple($config);
                case 'maximum':
                case 'exclusiveMaximum': // doet niks
                case 'minimum':
                case 'exclusiveMinimum': // doet niks
                    $min = $validations['minimum'] ?? null;
                    $max = $validations['maximum'] ?? null;
                    $validator->between($min, $max);
                    break;
                case 'minLength':
                case 'maxLength':
                    $min = $validations['minLength'] ?? null;
                    $max = $validations['maxLength'] ?? null;
                    $validator->length($min, $max);
                    break;
                case 'maxItems':
                case 'minItems':
                    $min = $validations['minItems'] ?? null;
                    $max = $validations['maxItems'] ?? null;
                    $validator->length($min, $max);
                    break;
                case 'uniqueItems':
                    $validator->unique();
                case 'maxProperties':
                case 'minProperties':
                    $min = $validations['minProperties'] ?? null;
                    $max = $validations['maxProperties'] ?? null;
                    $validator->length($min, $max);
                case 'minDate':
                case 'maxDate':
                    $min = new DateTime($validations['minDate'] ?? null);
                    $max = new DateTime($validations['maxDate'] ?? null);
                    $validator->length($min, $max);
                    break;
                case 'maxFileSize':
                case 'fileType':
                    //TODO
                    break;
                case 'required':
                    $validator->notEmpty();
                    break;
                case 'forbidden':
                    $validator->not(Validator::notEmpty());
                    break;
                case 'conditionals':
                    /// here we go
                    foreach ($config as $con) {
                        // Lets check if the referenced value is present
                        /* @tdo this isnt array proof */
                        if ($conValue = $objectEntity->getValueByName($con['property'])->value) {
                            switch ($con['condition']) {
                                case '==':
                                    if ($conValue == $con['value']) {
                                        $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                                    }
                                    break;
                                case '!=':
                                    if ($conValue != $con['value']) {
                                        $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                                    }
                                    break;
                                case '<=':
                                    if ($conValue <= $con['value']) {
                                        $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                                    }
                                    break;
                                case '>=':
                                    if ($conValue >= $con['value']) {
                                        $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                                    }
                                    break;
                                case '>':
                                    if ($conValue > $con['value']) {
                                        $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                                    }
                                    break;
                                case '<':
                                    if ($conValue < $con['value']) {
                                        $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                                    }
                                    break;
                            }
                        }
                    }
                    break;
                default:
                    // we should never end up here
                    //$objectEntity->addError($attribute->getName(),'Has an an unknown validation: [' . (string) $validation . '] set to'. (string) $config);
            }
        }

        return $validator;
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param $value
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    private function validateAttributeType(ObjectEntity $objectEntity, Attribute $attribute, $value): ObjectEntity
    {
        // Validation for enum (if attribute type is not object or boolean)
        if ($attribute->getEnum() && !in_array($value, $attribute->getEnum()) && $attribute->getType() != 'object' && $attribute->getType() != 'boolean') {
            $enumValues = '['.implode(', ', $attribute->getEnum()).']';
            $errorMessage = $attribute->getMultiple() ? 'All items in this array must be one of the following values: ' : 'Must be one of the following values: ';
            $objectEntity->addError($attribute->getName(), $errorMessage.$enumValues.' ('.$value.' is not).');
        }

        // Do validation for attribute depending on its type
        switch ($attribute->getType()) {
            case 'object':
                //TODO: all code that uses $attribute->getMultiple() == true here, will never be reached, because of validateAttributeMultiple() in validateAttribute()!

                // lets see if we already have a sub object
                $valueObject = $objectEntity->getValueByAttribute($attribute);

                // If this object is given as a uuid (string) it should be valid, if not throw error
                if (is_string($value) && Uuid::isValid($value) == false) {
                    // We should also allow commonground Uri's like: https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
                    if ($value == $attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($value)) {
                        $value = $this->commonGroundService->getUuidFromUrl($value);
                    } else {
                        $objectEntity->addError($attribute->getName(), 'The given value ('.$value.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).');
                        break;
                    }
                }

                // Lets check for cascading
                /* todo make switch */
                if (!$attribute->getCascade() && !$attribute->getMultiple() && !is_string($value)) {
                    $objectEntity->addError($attribute->getName(), 'Is not an string but '.$attribute->getName().' is not allowed to cascade, provide an uuid as string instead');
                    break;
                }
                if (!$attribute->getCascade() && $attribute->getMultiple()) {
                    foreach ($value as $arraycheck) {
                        if (!is_string($arraycheck)) {
                            $objectEntity->addError($attribute->getName(), 'Contians a value that is not an string but '.$attribute->getName().' is not allowed to cascade, provide an uuid as string instead');
                            break;
                        }
                    }
                }

                // Lets handle the stuf
                // If we are not cascading, attribute is not multiple and value is a string, than value should be an id.
                if (!$attribute->getCascade() && !$attribute->getMultiple() && is_string($value)) {
                    // Look for an existing ObjectEntity with its id or externalId set to this string, else look in external component with this uuid.
                    // Always create a new ObjectEntity if we find an exernal object but it has no ObjectEntity yet.

                    // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
                    if (!$subObject = $this->em->getRepository('App:ObjectEntity')->find($value)) {
                        if (!$subObject = $this->em->getRepository('App:ObjectEntity')->findBy(['externalId' => $value])) {
                            // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside of the gateway for an existing object.
                            $subObject = $this->createOEforExternObject($attribute->getObject(), $value, $valueObject, $objectEntity);
                            if (!$subObject) {
                                $objectEntity->addError($attribute->getName(), 'Could not find an object with id '.$value.' of type '.$attribute->getObject()->getName());
                                break;
                            }
                        } else {
                            // Found an object with externalId
                            $subObject = $subObject[0];
                        }
                    }

                    // object toeveogen
                    $valueObject->getObjects()->clear(); // We start with a deafult object
                    $valueObject->addObject($subObject);
                    break;
                }
                if (!$attribute->getCascade() && $attribute->getMultiple()) {
                    $valueObject->getObjects()->clear();
                    foreach ($value as $arraycheck) {
                        if (is_string($value) && !$subObject = $this->em->getRepository('App:ObjectEntity')->find($value)) {
                            $objectEntity->addError($attribute->getName(), 'Could not find an object with id '.(string) $value.' of type '.$attribute->getObject()->getName());
                        } else {
                            // object toeveogen
                            $valueObject->addObject($subObject);
                        }
                    }
                    break;
                }

                if (!$valueObject->getValue()) {
                    $subObject = new ObjectEntity();
                    $subObject->setEntity($attribute->getObject());
                    $subObject->addSubresourceOf($valueObject);
                    $valueObject->addObject($subObject);
                }

                /* @todo check if is have multpile objects but multiple is false and throw error */
                //var_dump($subObject->getName());
                // TODO: more validation for type object?
                if (!$attribute->getMultiple()) {
                    // Lets see if the object already exists
                    if (!$valueObject->getValue()) {
                        $subObject = $this->validateEntity($subObject, $value);
                        $valueObject->setValue($subObject);
                    } else {
                        $subObject = $valueObject->getValue();
                        $subObject = $this->validateEntity($subObject, $value);
                    }
                    $this->em->persist($subObject);
                } else {
                    $subObjects = $valueObject->getObjects();
                    if ($subObjects->isEmpty()) {
                        $subObject = new ObjectEntity();
                        $subObject->setEntity($attribute->getObject());
                        $subObject->addSubresourceOf($valueObject);
                        $subObject = $this->validateEntity($subObject, $value);
                        $valueObject->addObject($subObject);
                    }
                    // Loop trough the subs
                    foreach ($valueObject->getObjects() as $subObject) {
                        $subObject = $this->validateEntity($subObject, $value); // Dit is de plek waarop we weten of er een api call moet worden gemaakt
                    }
                }

                // if not we can push it into our object
                if (!$objectEntity->getHasErrors()) {
                    $objectEntity->getValueByAttribute($attribute)->setValue($subObject);
                }
                break;
            case 'string':
                if (!is_string($value)) {
                    $objectEntity->addError($attribute->getName(), 'Expects '.$attribute->getType().', '.gettype($value).' given. ('.$value.')');
                }
                if ($attribute->getMinLength() && strlen($value) < $attribute->getMinLength()) {
                    $objectEntity->addError($attribute->getName(), $value.' is to short, minimum length is '.$attribute->getMinLength().'.');
                }
                if ($attribute->getMaxLength() && strlen($value) > $attribute->getMaxLength()) {
                    $objectEntity->addError($attribute->getName(), $value.' is to long, maximum length is '.$attribute->getMaxLength().'.');
                }
                break;
            case 'number':
                if (!is_integer($value) && !is_float($value) && gettype($value) != 'float' && gettype($value) != 'double') {
                    $objectEntity->addError($attribute->getName(), 'Expects '.$attribute->getType().', '.gettype($value).' given. ('.$value.')');
                }
                break;
            case 'integer':
                if (!is_integer($value)) {
                    $objectEntity->addError($attribute->getName(), 'Expects '.$attribute->getType().', '.gettype($value).' given. ('.$value.')');
                }
                if ($attribute->getMinimum()) {
                    if ($attribute->getExclusiveMinimum() && $value <= $attribute->getMinimum()) {
                        $objectEntity->addError($attribute->getName(), 'Must be higher than '.$attribute->getMinimum().' ('.$value.' is not).');
                    } elseif ($value < $attribute->getMinimum()) {
                        $objectEntity->addError($attribute->getName(), 'Must be '.$attribute->getMinimum().' or higher ('.$value.' is not).');
                    }
                }
                if ($attribute->getMaximum()) {
                    if ($attribute->getExclusiveMaximum() && $value >= $attribute->getMaximum()) {
                        $objectEntity->addError($attribute->getName(), 'Must be lower than '.$attribute->getMaximum().'  ('.$value.' is not).');
                    } elseif ($value > $attribute->getMaximum()) {
                        $objectEntity->addError($attribute->getName(), 'Must be '.$attribute->getMaximum().' or lower  ('.$value.' is not).');
                    }
                }
                if ($attribute->getMultipleOf() && $value % $attribute->getMultipleOf() != 0) {
                    $objectEntity->addError($attribute->getName(), 'Must be a multiple of '.$attribute->getMultipleOf().', '.$value.' is not a multiple of '.$attribute->getMultipleOf().'.');
                }
                break;
            case 'boolean':
                if (!is_bool($value)) {
                    $objectEntity->addError($attribute->getName(), 'Expects '.$attribute->getType().', '.gettype($value).' given. ('.$value.')');
                }
                break;
            case 'date':
            case 'datetime':
                try {
                    new DateTime($value);
                } catch (Exception $e) {
                    $objectEntity->addError($attribute->getName(), 'Expects '.$attribute->getType().' (ISO 8601 datetime standard), failed to parse string to DateTime. ('.$value.')');
                }
                break;
            case 'file':
                if (!array_key_exists('base64', $value)) {
                    $objectEntity->addError($attribute->getName().'.base64', 'Expects an array with at least key base64 with a valid base64 encoded string value. (could also contain key filename)');
                    break;
                }

                // Validate (and create/update) this file
                $objectEntity = $this->validateFile($objectEntity, $attribute, $this->base64ToFileArray($value));

                break;
            default:
                $objectEntity->addError($attribute->getName(), 'Has an an unknown type: ['.$attribute->getType().']');
        }

        return $objectEntity;
    }

    /**
     * Validates a file.
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param array        $fileArray
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    public function validateFile(ObjectEntity $objectEntity, Attribute $attribute, array $fileArray): ObjectEntity
    {
        $value = $objectEntity->getValueByAttribute($attribute);
        $key = $fileArray['key'] ? '['.$fileArray['key'].']' : '';
        $shortBase64String = strlen($fileArray['base64']) > 75 ? substr($fileArray['base64'], 0, 75).'...' : $fileArray['base64'];

        // Validate base64 string (for raw json body input)
        $explode_base64 = explode(',', $fileArray['base64']);
        if (base64_encode(base64_decode(end($explode_base64), true)) !== end($explode_base64)) {
            $objectEntity->addError($attribute->getName().$key.'.base64', 'Expects a valid base64 encoded string. ('.$shortBase64String.' is not)');
        }
        // Validate max file size
        if ($attribute->getMaxFileSize() && $fileArray['size'] > $attribute->getMaxFileSize()) {
            $objectEntity->addError($attribute->getName().$key.'.base64', 'This file is to big ('.$fileArray['size'].' bytes), expecting a file with maximum size of '.$attribute->getMaxFileSize().' bytes. ('.$shortBase64String.')');
        }
        // Validate mime type
        if ($attribute->getFileType() && $fileArray['mimeType'] != $attribute->getFileType()) {
            $objectEntity->addError($attribute->getName().$key.'.base64', 'Expects a file of type '.$attribute->getFileType().', not '.$fileArray['mimeType'].'. ('.$shortBase64String.')');
        }

        if ($fileArray['name']) {
            // Find file by filename (this can be the uuid of the file object)
            $fileObject = $value->getFiles()->filter(function (File $item) use ($fileArray) {
                return $item->getName() == $fileArray['name'];
            });
            if (count($fileObject) > 1) {
                $objectEntity->addError($attribute->getName().$key.'.name', 'More than 1 file found with this name: '.$fileArray['name']);
            }
            // (If we found 0 or 1, continue...)
        }

        // If no errors we can update or create a File
        if (!$objectEntity->getHasErrors()) {
            if (isset($fileObject) && count($fileObject) == 1) {
                // Update existing file if we found one using the given file name
                $fileObject = $fileObject->first();
            } else {
                // Create a new file
                $fileObject = new File();
            }
            $this->em->persist($fileObject); // For getting the id if no name is given
            $fileObject->setName($fileArray['name'] ?? $fileObject->getId());
            $fileObject->setExtension($fileArray['extension']);
            $fileObject->setMimeType($fileArray['mimeType']);
            $fileObject->setSize($fileArray['size']);
            $fileObject->setBase64($fileArray['base64']);

            $value->addFile($fileObject);
        }

        return $objectEntity;
    }

    /**
     * Create a file array (matching the Entity File) from an array containing at least a base64 string and maybe a filename (not required).
     *
     * @param array $file
     *
     * @return array
     */
    private function base64ToFileArray(array $file, string $key = null): array
    {
        // Get mime_type from base64
        $explode_base64 = explode(',', $file['base64']);
        $imgdata = base64_decode(end($explode_base64));
        $f = finfo_open();
        $mime_type = finfo_buffer($f, $imgdata, FILEINFO_MIME_TYPE);
        finfo_close($f);

        // Get extension from mime_type
        $explode_mime_type = explode('/', $mime_type);

        // Create file data
        return [
            'name'      => array_key_exists('filename', $file) ? $file['filename'] : null,
            'extension' => end($explode_mime_type),
            'mimeType'  => $mime_type,
            'size'      => $this->getBase64Size($file['base64']),
            'base64'    => $file['base64'],
            'key'       => $key, // Pass this through for showing correct error messages with multiple files
        ];
    }

    /**
     * Gets the memory size of a base64 file.
     *
     * @param $base64
     *
     * @return Exception|float|int
     */
    private function getBase64Size($base64)
    { //return memory size in B, KB, MB
        try {
            $size_in_bytes = (int) (strlen(rtrim($base64, '=')) * 3 / 4);
            $size_in_kb = $size_in_bytes / 1024;
            $size_in_mb = $size_in_kb / 1024;

            return $size_in_bytes;
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Create a file array (matching the Entity File) from an UploadedFile object.
     *
     * @param UploadedFile $file
     *
     * @return array
     */
    public function uploadedFileToFileArray(UploadedFile $file, string $key = null): array
    {
        return [
            'name'      => $file->getClientOriginalName() ?? null,
            'extension' => $file->getClientOriginalExtension() ?? null,
            'mimeType'  => $file->getClientMimeType() ?? null,
            'size'      => $file->getSize() ?? null,
            'base64'    => $this->uploadToBase64($file),
            'key'       => $key, // Pass this through for showing correct error messages with multiple files
        ];
    }

    /**
     * Create a base64 string from an UploadedFile object.
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    private function uploadToBase64(UploadedFile $file): string
    {
        $content = base64_encode($file->openFile()->fread($file->getSize()));
        $mimeType = $file->getClientMimeType();

        return 'data:'.$mimeType.';base64,'.$content;
    }

    /**
     * Format validation.
     *
     * Format validation is done using the [respect/validation](https://respect-validation.readthedocs.io/en/latest/) packadge for php
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param $value
     *
     * @return ObjectEntity
     */
    private function validateAttributeFormat(ObjectEntity $objectEntity, Attribute $attribute, $value): ObjectEntity
    {
        // if no format is provided we dont validate
        if ($attribute->getFormat() == null) {
            return $objectEntity;
        }

        $format = $attribute->getFormat();

        // Let be a bit compasionate and compatable
        $format = str_replace(['telephone'], ['phone'], $format);

        // In order not to allow any respect/validation function to be called we explicatly call those containing formats
        $allowedValidations = ['countryCode', 'bsn', 'url', 'uuid', 'email', 'phone', 'json'];

        // new route
        if (in_array($format, $allowedValidations)) {
            try {
                Validator::$format()->check($value);
            } catch (ValidationException $exception) {
                $objectEntity->addError($attribute->getName(), $exception->getMessage());
            }

            return $objectEntity;
        }

        $objectEntity->addError($attribute->getName(), 'Has an an unknown format: ['.$attribute->getFormat().']');

        return $objectEntity;
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param array        $post
     *
     * @return PromiseInterface
     */
    public function createPromise(ObjectEntity $objectEntity, array $post): PromiseInterface
    {
        // We willen de post wel opschonnen, met andere woorden alleen die dingen posten die niet als in een attrubte zijn gevangen

        $component = $this->gatewayService->gatewayToArray($objectEntity->getEntity()->getGateway());
        $query = [];
        $headers = [];

        if ($objectEntity->getUri()) {
            $method = 'PUT';
            $url = $objectEntity->getUri();
        } elseif ($objectEntity->getExternalId()) {
            $method = 'PUT';
            $url = $objectEntity->getEntity()->getGateway()->getLocation().'/'.$objectEntity->getEntity()->getEndpoint().'/'.$objectEntity->getExternalId();
        } else {
            $method = 'POST';
            $url = $objectEntity->getEntity()->getGateway()->getLocation().'/'.$objectEntity->getEntity()->getEndpoint();
        }

        // do transformation
        if ($objectEntity->getEntity()->getTransformations() && !empty($objectEntity->getEntity()->getTransformations())) {
            /* @todo use array map to rename key's https://stackoverflow.com/questions/9605143/how-to-rename-array-keys-in-php */
        }

        // If we are depend on subresources on another api we need to wait for those to resolve (we might need there id's for this resoure)
        /* @todo dit systeem gaat maar 1 level diep */
        $promises = [];
        foreach ($objectEntity->getSubresources() as $sub) {
            $promises = array_merge($promises, $sub->getPromises());
        }

        if (!empty($promises)) {
            Utils::settle($promises)->wait();
        }

        // At this point in time we have the object values (becuse this is post validation) so we can use those to filter the post
        foreach ($objectEntity->getObjectValues() as $value) {

            // Lets prefend the posting of values that we store localy
            //if(!$value->getAttribute()->getPersistToGateway()){
            //    unset($post[$value->getAttribute()->getName()]);
            // }

            // then we can check if we need to insert uri for the linked data of subobjects in other api's
            if ($value->getAttribute()->getMultiple() && $value->getObjects()) {
                // Lets whipe the current values (we will use Uri's)
                $post[$value->getAttribute()->getName()] = [];

                /* @todo this loop in loop is a death sin */
                foreach ($value->getObjects() as $objectToUri) {
                    /* @todo the hacky hack hack */
                    // If it is a an internal url we want to us an internal id
                    if ($objectToUri->getEntity()->getGateway() == $objectEntity->getEntity()->getGateway()) {
                        $ubjectUri = $objectToUri->getEntity()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($objectToUri->getUri());
                    } else {
                        $ubjectUri = $objectToUri->getUri();
                    }
                    $post[$value->getAttribute()->getName()][] = $ubjectUri;
                }
            } elseif ($value->getObjects()->first()) {
                $post[$value->getAttribute()->getName()] = $value->getObjects()->first()->getUri();
            }

            // Lets check if we actually want to send this to the gateway
            if (!$value->getAttribute()->getPersistToGateway()) {
                unset($post[$value->getAttribute()->getName()]);
            }
        }

        // We want to clear some stuf upp dh
        if (array_key_exists('id', $post)) {
            unset($post['id']);
        }
        if (array_key_exists('@context', $post)) {
            unset($post['@context']);
        }
        if (array_key_exists('@id', $post)) {
            unset($post['@id']);
        }
        if (array_key_exists('@type', $post)) {
            unset($post['@type']);
        }

        //var_dump($url);
        //var_dump($post);

        $promise = $this->commonGroundService->callService($component, $url, json_encode($post), $query, $headers, true, $method)->then(
            // $onFulfilled
            function ($response) use ($objectEntity, $url, $method) {
                if ($objectEntity->getEntity()->getGateway()->getLogging()) {
                    $gatewayResponseLog = new GatewayResponseLog();
                    $gatewayResponseLog->setObjectEntity($objectEntity);
                    $gatewayResponseLog->setResponse($response);
                    $this->em->persist($gatewayResponseLog);
                }

                $result = json_decode($response->getBody()->getContents(), true);
                if (array_key_exists('id', $result) && !strpos($url, $result['id'])) {
                    $objectEntity->setUri($url.'/'.$result['id']);
                    $objectEntity->setExternalId($result['id']);

                    $item = $this->cache->getItem('commonground_'.md5($url.'/'.$result['id']));
                } else {
                    $objectEntity->setUri($url);
                    $objectEntity->setExternalId($this->commonGroundService->getUuidFromUrl($url));
                    $item = $this->cache->getItem('commonground_'.md5($url));
                }

                // Only show/use the available properties for the external response/result
                if (!is_null($objectEntity->getEntity()->getAvailableProperties())) {
                    $availableProperties = $objectEntity->getEntity()->getAvailableProperties();
                    $result = array_filter($result, function ($key) use ($availableProperties) {
                        return in_array($key, $availableProperties);
                    }, ARRAY_FILTER_USE_KEY);
                }
                $objectEntity->setExternalResult($result);

                // Notify notification component
                $this->notify($objectEntity, $method);

                // Lets stuff this into the cache for speed reasons
                $item->set($result);
                //$item->expiresAt(new \DateTime('tomorrow'));
                $this->cache->save($item);
            },
            // $onRejected
            function ($error) use ($objectEntity) {

                /* @todo wat dachten we van een logging service? */
                $gatewayResponseLog = new GatewayResponseLog();
                $gatewayResponseLog->setGateway($objectEntity->getEntity()->getGateway());
                //$gatewayResponseLog->setObjectEntity($objectEntity);
                if ($error->getResponse()) {
                    $gatewayResponseLog->setResponse($error->getResponse());
                }
                $this->em->persist($gatewayResponseLog);
                $this->em->flush();

                /* @todo lelijke code */
                if ($error->getResponse()) {
                    $error = json_decode((string) $error->getResponse()->getBody(), true);
                    if ($error && array_key_exists('message', $error)) {
                        $error_message = $error['message'];
                    } elseif ($error && array_key_exists('hydra:description', $error)) {
                        $error_message = $error['hydra:description'];
                    } else {
                        $error_message = (string) $error->getResponse()->getBody();
                    }
                } else {
                    $error_message = $error->getMessage();
                }
                /* @todo eigenlijk willen we links naar error reports al losse property mee geven op de json error message */
                $objectEntity->addError('gateway endpoint on '.$objectEntity->getEntity()->getName().' said', $error_message.'. (see /gateway_logs/'.$gatewayResponseLog->getId().') for a full error report');
            }
        );

        return $promise;
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param string       $method
     */
    private function notify(ObjectEntity $objectEntity, string $method)
    {
        // TODO: move this function to a notificationService?
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
            ];
            $this->commonGroundService->createResource($notification, ['component' => 'nrc', 'type' => 'notifications'], false, true, false);
        }
    }

    /**
     * TODO: docs.
     *
     * @param $type
     * @param $id
     *
     * @return string
     */
    public function createUri($entityName, $id): string
    {
        //TODO: change how this uri is generated? use $entityName? or just remove $entityName
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $uri = 'https://';
        } else {
            $uri = 'http://';
        }
        $uri .= $_SERVER['HTTP_HOST'];

        return $uri.'/object_entities/'.$id;
    }
}
