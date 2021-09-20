<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\GatewayResponceLog;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;

class ValidationService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private GatewayService $gatewayService;
    private CacheInterface $cache;
    public $promises = []; //TODO: use ObjectEntity->promises instead!

    public function __construct(
        EntityManagerInterface $em,
        CommonGroundService $commonGroundService,
        GatewayService $gatewayService,
        CacheInterface $cache)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->gatewayService = $gatewayService;
        $this->cache = $cache;
    }

    /** TODO: docs
     * @param ObjectEntity $objectEntity
     * @param array $post
     * @return ObjectEntity
     * @throws Exception
     */
    public function validateEntity (ObjectEntity $objectEntity, array $post): ObjectEntity
    {
        $entity = $objectEntity->getEntity();
        foreach($entity->getAttributes() as $attribute) {
            // Check if we have a value to validate ( a value is given in the post body for this attribute, can be null )
            if (key_exists($attribute->getName(), $post)) {
                $objectEntity = $this->validateAttribute($objectEntity, $attribute, $post[$attribute->getName()]);
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
            elseif ($attribute->getRequired()){
                $objectEntity->addError($attribute->getName(),'This attribute is required');
            } else {
                // handling the setting to null of exisiting variables
                $objectEntity->getValueByAttribute($attribute)->setValue(null);
            }
        }

        // Check post for not allowed properties
        foreach($post as $key=>$value){
            if(!$entity->getAttributeByName($key)){
                $objectEntity->addError($key,'Does not exist on this property');
            }
        }

        // Check optional conditional logic
        $objectEntity->checkConditionlLogic();

        // Dit is de plek waarop we weten of er een api call moet worden gemaakt
        if(!$objectEntity->getHasErrors() && $objectEntity->getEntity()->getGateway()){
            $promise = $this->createPromise($objectEntity, $post);
            $this->promises[] = $promise; //TODO: use ObjectEntity->promises instead!
            $objectEntity->addPromise($promise);
        }

        return $objectEntity;
    }

    /** TODO: docs
     * @param ObjectEntity $objectEntity
     * @param Attribute $attribute
     * @param $value
     * @return ObjectEntity
     * @throws Exception
     */
    private function validateAttribute(ObjectEntity $objectEntity, Attribute $attribute, $value): ObjectEntity
    {
        // Check if value is null, and if so, check if attribute has a defaultValue and else if it is nullable
        if (is_null($value)) {
            if ($attribute->getDefaultValue()) {
                $objectEntity->getValueByAttribute($attribute)->setValue($attribute->getDefaultValue());
            } elseif (!$attribute->getNullable()) {
                $objectEntity->addError($attribute->getName(),'Expects ' . $attribute->getType() . ', ' . gettype($value) . ' given. (Nullable is not set for this attribute)');
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
            // Multiple == false, so this is not an array
            $objectEntity = $this->validateAttributeType($objectEntity, $attribute, $value);
            $objectEntity = $this->validateAttributeFormat($objectEntity, $attribute, $value);
        }

        if ($attribute->getMustBeUnique()) {
            $objectEntity = $this->validateAttributeUnique($objectEntity, $attribute, $value);
            // We should not continue other validations after this!
            if ($objectEntity->getHasErrors()) return $objectEntity;
        }

        // if no errors we can set the value (for type object this is already done in validateAttributeType, other types we do it here,
        // because when we use validateAttributeType to validate items in an array, we dont want to set values for that)
        if (!$objectEntity->getHasErrors() && $attribute->getType() != 'object') {
            $objectEntity->getValueByAttribute($attribute)->setValue($value);
        }

        return $objectEntity;
    }

    /** TODO: docs
     * @param ObjectEntity $objectEntity
     * @param Attribute $attribute
     * @param $value
     * @return ObjectEntity
     * @throws Exception
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

        if (count($values) > 0) {
            if ($attribute->getType() == 'boolean') $value = $value ? 'true' : 'false';
            $objectEntity->addError($attribute->getName(),'Must be unique, there already exists an object with this value: ' . $value . '.');
        }

        return $objectEntity;
    }

    /** TODO: docs
     * @param ObjectEntity $objectEntity
     * @param Attribute $attribute
     * @param $value
     * @return ObjectEntity
     * @throws Exception
     */
    private function validateAttributeMultiple(ObjectEntity $objectEntity, Attribute $attribute, $value): ObjectEntity
    {
        // If multiple, this is an array, validation for an array:
        if (!is_array($value)) {
            $objectEntity->addError($attribute->getName(),'Expects array, ' . gettype($value) . ' given. (Multiple is set for this attribute)');

            // Lets not continue validation if $value is not an array (because this will cause weird 500s!!!)
            return $objectEntity;
        }
        if ($attribute->getMinItems() && count($value) < $attribute->getMinItems()) {
            $objectEntity->addError($attribute->getName(),'The minimum array length of this attribute is ' . $attribute->getMinItems() . '.');
        }
        if ($attribute->getMaxItems() && count($value) > $attribute->getMaxItems()) {
            $objectEntity->addError($attribute->getName(),'The maximum array length of this attribute is ' . $attribute->getMaxItems() . '.');
        }
        if ($attribute->getUniqueItems() && count(array_filter(array_keys($value), 'is_string')) == 0) {
            // TODOmaybe:check this in another way so all kinds of arrays work with it.
            $containsStringKey = false;
            foreach ($value as $arrayItem) {
                if (is_array($arrayItem) && count(array_filter(array_keys($arrayItem), 'is_string')) > 0){
                    $containsStringKey = true; break;
                }
            }
            if (!$containsStringKey && count($value) !== count(array_unique($value))) {
                $objectEntity->addError($attribute->getName(),'Must be an array of unique items');
            }
        }

        // Then validate all items in this array
        if ($attribute->getType() != 'object') {
            foreach ($value as $item) {
                $objectEntity = $this->validateAttributeType($objectEntity, $attribute, $item);
                $objectEntity = $this->validateAttributeFormat($objectEntity, $attribute, $value);
            }
        } else {
            // TODO: maybe move and merge all this code to the validateAttributeType function under type 'object'. NOTE: this code works very different!!!
            // This is an array of objects
            $valueObject = $objectEntity->getValueByAttribute($attribute);
            foreach($value as $object) {
                if (!is_array($object)) {
                    $objectEntity->addError($attribute->getName(),'Multiple is set for this attribute. Expecting an array of objects.');
                    break;
                }
                if(array_key_exists('id', $object)) {
                    $subObject = $objectEntity->getValueByAttribute($attribute)->getObjects()->filter(function(ObjectEntity $item) use($object) {
                        return $item->getId() == $object['id'];
                    });
                    if (empty($subObject)) {
                        $objectEntity->addError($attribute->getName(),'No existing object found with this id: '.$object['id']);
                        break;
                    } elseif (count($subObject) > 1) {
                        $objectEntity->addError($attribute->getName(),'More than 1 object found with this id: '.$object['id']);
                        break;
                    }
                    unset($object['id']);
                    $subObject = $subObject->first();
                }
                else {
                    $subObject = New ObjectEntity();
                    $subObject->addSubresourceOf($valueObject);
                    $subObject->setEntity($attribute->getObject());
                }
                $subObject = $this->validateEntity($subObject, $object);

                // We need to persist if this is a new ObjectEntity in order to set and getId to generate the uri...
                $this->em->persist($subObject);
                $subObject->setUri($this->createUri($subObject->getEntity()->getName(), $subObject->getId()));

                // if no errors we can add this subObject tot the valueObject array of objects
//                    if (!$subObject->getHasErrors()) { // TODO: put this back?, with this if statement errors of subresources will not be shown, bug...?
                $subObject->getValueByAttribute($attribute)->setValue($subObject);
                $valueObject->addObject($subObject);
//                    }
            }
        }

        return $objectEntity;
    }

    /** TODO: docs
     * @param ObjectEntity $objectEntity
     * @param Attribute $attribute
     * @param $value
     * @return ObjectEntity
     * @throws Exception
     */
    private function validateAttributeType(ObjectEntity $objectEntity, Attribute $attribute, $value): ObjectEntity
    {
        // Validation for enum (if attribute type is not object or boolean)
        if ($attribute->getEnum() && !in_array($value, $attribute->getEnum()) && $attribute->getType() != 'object' && $attribute->getType() != 'boolean') {
            $enumValues = '[' . implode( ", ", $attribute->getEnum() ) . ']';
            $errorMessage = $attribute->getMultiple() ? 'All items in this array must be one of the following values: ' : 'Must be one of the following values: ';
            $objectEntity->addError($attribute->getName(), $errorMessage . $enumValues . ' (' . $value . ' is not).');
        }

        // Do validation for attribute depending on its type
        switch ($attribute->getType()) {
            case 'object':
                // lets see if we already have a sub object
                $valueObject = $objectEntity->getValueByAttribute($attribute);

                // Lets check for cascading
                /* todo make switch */
                if(!$attribute->getCascade() && !$attribute->getMultiple() && !is_string($value)){
                    $objectEntity->addError($attribute->getName(),'Is not an string but ' . $attribute->getName() . ' is not allowed to cascade, provide an uuid as string instead');
                    break;
                }
                if(!$attribute->getCascade() && $attribute->getMultiple()){
                    foreach($value as $arraycheck) {
                        if(!is_string($arraycheck)){
                            $objectEntity->addError($attribute->getName(),'Contians a value that is not an string but ' . $attribute->getName() . ' is not allowed to cascade, provide an uuid as string instead');
                            break;
                        }
                    }
                }

                if(!$valueObject->getValue()) {
                    $subObject = New ObjectEntity();
                    $subObject->setEntity($attribute->getObject());
                    $subObject->addSubresourceOf($valueObject);
                    $valueObject->addObject($subObject);
                }

                // Lets handle the stuf
                if(!$attribute->getCascade() && !$attribute->getMultiple() && is_string($value)){
                    // Object ophalen
                    if(!$subObject = $this->em->getRepository("App:ObjectEntity")->find($value)){
                        $objectEntity->addError($attribute->getName(),'Could not find an object with id ' . $value . ' of type '. $attribute->getEntity()->getName());
                        break;
                    }

                    // object toeveogen
                    $valueObject->getObjects()->clear(); // We start with a deafult object
                    $valueObject->addObject($subObject);
                    break;

                }
                if(!$attribute->getCascade() && $attribute->getMultiple()){
                    $valueObject->getObjects()->clear();
                    foreach($value as $arraycheck) {
                        if(is_string($value) && !$subObject = $this->em->getRepository("App:ObjectEntity")->find($value)){
                            $objectEntity->addError($attribute->getName(),'Could not find an object with id ' . (string) $value . ' of type '. $attribute->getEntity()->getName());
                        }
                        else{
                            // object toeveogen
                            $valueObject->addObject($subObject);
                        }
                    }
                    break;
                }



                /* @todo check if is have multpile objects but multiple is false and throw error */
                //var_dump($subObject->getName());
                // TODO: more validation for type object?
                if(!$attribute->getMultiple()){
                    // Lets see if the object already exists
                    if(!$valueObject->getValue()) {
                        $subObject = $this->validateEntity($subObject, $value);
                        $valueObject->setValue($subObject);
                    } else {
                        $subObject = $valueObject->getValue();
                        $subObject = $this->validateEntity($subObject, $value);
                    }
                    $this->em->persist($subObject);
                }
                else{
                    $subObjects = $valueObject->getObjects();
                    if($subObjects->isEmpty()){
                        $subObject = New ObjectEntity();
                        $subObject->setEntity($attribute->getObject());
                        $subObject->addSubresourceOf($valueObject);
                        $subObject = $this->validateEntity($subObject, $value);
                        $valueObject->addObject($subObject);
                    }
                    // Loop trough the subs
                    foreach($valueObject->getObjects() as $subObject){
                        $subObject = $this->validateEntity($subObject, $value); // Dit is de plek waarop we weten of er een api call moet worden gemaakt
                    }
                }

                // We need to persist if this is a new ObjectEntity in order to set and getId to generate the uri...
                // $subObject->setUri($this->createUri($subObject->getEntity()->getName(), $subObject->getId()));

                // if not we can push it into our object
                if (!$objectEntity->getHasErrors()) {
                    $objectEntity->getValueByAttribute($attribute)->setValue($subObject);
                }
                break;
            case 'string':
                if (!is_string($value)) {
                    $objectEntity->addError($attribute->getName(),'Expects ' . $attribute->getType() . ', ' . gettype($value) . ' given. ('.$value.')');
                }
                if ($attribute->getMinLength() && strlen($value) < $attribute->getMinLength()) {
                    $objectEntity->addError($attribute->getName(),$value.' is to short, minimum length is ' . $attribute->getMinLength() . '.');
                }
                if ($attribute->getMaxLength() && strlen($value) > $attribute->getMaxLength()) {
                    $objectEntity->addError($attribute->getName(),$value.' is to long, maximum length is ' . $attribute->getMaxLength() . '.');
                }
                break;
            case 'number':
                if (!is_integer($value) && !is_float($value) && gettype($value) != 'float' && gettype($value) != 'double') {
                    $objectEntity->addError($attribute->getName(),'Expects ' . $attribute->getType() . ', ' . gettype($value) . ' given. ('.$value.')');
                }
                break;
            case 'integer':
                if (!is_integer($value)) {
                    $objectEntity->addError($attribute->getName(),'Expects ' . $attribute->getType() . ', ' . gettype($value) . ' given. ('.$value.')');
                }
                if ($attribute->getMinimum()) {
                    if ($attribute->getExclusiveMinimum() && $value <= $attribute->getMinimum()) {
                        $objectEntity->addError($attribute->getName(),'Must be higher than ' . $attribute->getMinimum() . ' ('.$value.' is not).');
                    } elseif ($value < $attribute->getMinimum()) {
                        $objectEntity->addError($attribute->getName(),'Must be ' . $attribute->getMinimum() . ' or higher ('.$value.' is not).');
                    }
                }
                if ($attribute->getMaximum()) {
                    if ($attribute->getExclusiveMaximum() && $value >= $attribute->getMaximum()) {
                        $objectEntity->addError($attribute->getName(),'Must be lower than ' . $attribute->getMaximum() . '  ('.$value.' is not).');
                    } elseif ($value > $attribute->getMaximum()) {
                        $objectEntity->addError($attribute->getName(),'Must be ' . $attribute->getMaximum() . ' or lower  ('.$value.' is not).');
                    }
                }
                if ($attribute->getMultipleOf() && $value % $attribute->getMultipleOf() != 0) {
                    $objectEntity->addError($attribute->getName(),'Must be a multiple of ' . $attribute->getMultipleOf() . ', ' . $value . ' is not a multiple of ' . $attribute->getMultipleOf() . '.');
                }
                break;
            case 'boolean':
                if (!is_bool($value)) {
                    $objectEntity->addError($attribute->getName(),'Expects ' . $attribute->getType() . ', ' . gettype($value) . ' given. ('.$value.')');
                }
                break;
            case 'date':
            case 'datetime':
                try {
                    new DateTime($value);
                } catch (Exception $e) {
                    $objectEntity->addError($attribute->getName(),'Expects ' . $attribute->getType() . ' (ISO 8601 datetime standard), failed to parse string to DateTime. ('.$value.')');
                }
                break;
            default:
                $objectEntity->addError($attribute->getName(),'Has an an unknown type: [' . $attribute->getType() . ']');
        }

        return $objectEntity;
    }

    /** TODO: docs
     * @param ObjectEntity $objectEntity
     * @param Attribute $attribute
     * @param $value
     * @return ObjectEntity
     */
    private function validateAttributeFormat(ObjectEntity $objectEntity, Attribute $attribute, $value): ObjectEntity
    {
        if ($attribute->getFormat() == null) return $objectEntity;

        // Do validation for attribute depending on its format
        switch ($attribute->getFormat()) {
            case 'email':
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $objectEntity->addError($attribute->getName(),'Expects an email format, ' . $value . ' is not a valid email.');
                }
                break;
            case 'telephone':
                if (!is_string($value) || (preg_match('/^\+?[1-9]\d{1,14}$/', $value) !== 1)) {
                    $objectEntity->addError($attribute->getName(),'Expects an telephone format, ' . $value . ' is not a valid phone number that conforms to the E.164 standard.');
                }
                break;
            case 'uuid':
                if (!is_string($value) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value) !== 1)) {
                    $objectEntity->addError($attribute->getName(),'Expects a uuid format, ' . $value . ' is not a valid uuid.');
                }
                break;
            case 'url':
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                    $objectEntity->addError($attribute->getName(),'Expects an url format, ' . $value . ' is not a valid url.');
                }
                break;
            default:
                $objectEntity->addError($attribute->getName(),'Has an an unknown format: [' . $attribute->getFormat() . ']');
        }

        return $objectEntity;
    }

    /** TODO: docs
     * @param ObjectEntity $objectEntity
     * @param array $post
     * @return PromiseInterface
     */
    function createPromise(ObjectEntity $objectEntity, array $post): PromiseInterface
    {

        // We willen de post wel opschonnen, met andere woorden alleen die dingen posten die niet als in een attrubte zijn gevangen

        $component = $this->gatewayService->gatewayToArray($objectEntity->getEntity()->getGateway());
        $query = [];
        $headers = [];

        if($objectEntity->getUri()){
            $method = 'PUT';
            $url = $objectEntity->getUri();
        }
        else{
            $method = 'POST';
            $url = $objectEntity->getEntity()->getGateway()->getLocation() . '/' . $objectEntity->getEntity()->getEndpoint();
        }

        // do transformation
        if($objectEntity->getEntity()->getTransformations() && !empty($objectEntity->getEntity()->getTransformations())){
            /* @todo use array map to rename key's https://stackoverflow.com/questions/9605143/how-to-rename-array-keys-in-php */
        }

        // If we are depend on subresources on another api we need to wait for those to resolve (we might need there id's for this resoure)
        /* @todo dit systeem gaat maar 1 level diep */
        $promises = [];
        foreach($objectEntity->getSubresources() as $sub){
            $promises = array_merge($promises,$sub->getPromises());
        }

        if(!empty($promises)){ Utils::settle($promises)->wait();}



        // At this point in time we have the object values (becuse this is post validation) so we can use those to filter the post
        foreach($objectEntity->getObjectValues() as $value){

            // Lets prefend the posting of values that we store localy
            //if(!$value->getAttribute()->getPersistToGateway()){
            //    unset($post[$value->getAttribute()->getName()]);
            // }

            // then we can check if we need to insert uri for the linked data of subobjects in other api's
            if($value->getAttribute()->getMultiple() && $value->getObjects()){
                // Lets whipe the current values (we will use Uri's)
                $post[$value->getAttribute()->getName()] = [];

                /* @todo this loop in loop is a death sin */
                foreach ($value->getObjects() as $objectToUri){
                    /* @todo the hacky hack hack */
                    // If it is a an internal url we want to us an internal id
                    if($objectToUri->getEntity()->getGateway() == $objectEntity->getEntity()->getGateway()){
                        $ubjectUri = $objectToUri->getEntity()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($objectToUri->getUri());
                    }
                    else{
                        $ubjectUri = $objectToUri->getUri();
                    }
                    $post[$value->getAttribute()->getName()][] = $ubjectUri;
                }
            }
            elseif($value->getObjects()->first())
            {
                $post[$value->getAttribute()->getName()] = $value->getObjects()->first()->getUri();
            }

            // Lets check if we actually want to send this to the gateway
            if(!$value->getAttribute()->getPersistToGateway())
            {
                unset($post[$value->getAttribute()->getName()]);
            }
        }

        // We want to clear some stuf upp dh
        if(array_key_exists('id',$post)){unset($post['id']);}
        if(array_key_exists('@context',$post)){unset($post['@context']);}
        if(array_key_exists('@id',$post)){unset($post['@id']);}
        if(array_key_exists('@type',$post)){unset($post['@type']);}

        //var_dump($url);
        //var_dump($post);

        $promise = $this->commonGroundService->callService($component, $url, json_encode($post), $query, $headers, true, $method)->then(
            // $onFulfilled
            function ($response) use ($post, $objectEntity, $url, $method, $component) {

                if($objectEntity->getEntity()->getGateway()->getLogging()){
                    $gatewayResponceLog = New GatewayResponceLog;
                    $gatewayResponceLog->setObjectEntity($objectEntity);
                    $gatewayResponceLog->setResponce($response);
                    $this->em->persist($gatewayResponceLog);
                }

                $result = json_decode($response->getBody()->getContents(), true);
                if(array_key_exists('id',$result) && !strpos($url, $result['id'])){

                    $objectEntity->setUri($url.'/'.$result['id']);

                    $item = $this->cache->getItem('commonground_'.md5($url.'/'.$result['id']));
                }
                else{
                    $objectEntity->setUri($url);
                    $item = $this->cache->getItem('commonground_'.md5($url));
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
            function ($error) use ($post, $objectEntity ) {

                /* @todo wat dachten we van een logging service? */
                $gatewayResponceLog = New GatewayResponceLog;
                $gatewayResponceLog->setGateway($objectEntity->getEntity()->getGateway());
                //$gatewayResponceLog->setObjectEntity($objectEntity);
                $gatewayResponceLog->setResponce($error->getResponse());
                $this->em->persist($gatewayResponceLog);
                $this->em->flush();

                /* @todo lelijke code */
                if($error->getResponse()){
                    $error = json_decode((string)$error->getResponse()->getBody(), true);
                    if($error && array_key_exists('message', $error)){
                        $error_message = $error['message'];
                    }
                    elseif($error && array_key_exists('hydra:description', $error)){
                        $error_message = $error['hydra:description'];
                    }
                    else {
                        $error_message =  (string)$error->getResponse()->getBody();
                    }
                }
                else {
                    $error_message =  $error->getMessage();
                }
                /* @todo eigenlijk willen we links naar error reports al losse property mee geven op de json error message */
                $objectEntity->addError('gateway endpoint on ' . $objectEntity->getEntity()->getName() . ' said', $error_message.'. (see /gateway_logs/'.$gatewayResponceLog->getId().') for a full error report');
            }
        );

        return $promise;
    }

    /** TODO: docs
     * @param ObjectEntity $objectEntity
     * @param string $method
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
                'topic' => $topic,
                'action' => $action,
                'resource' => $objectEntity->getUri()
            ];
            $this->commonGroundService->createResource($notification, ['component' => 'nrc', 'type' => 'notifications'], false, true, false);
        }
    }

    /** TODO: docs
     * @param $type
     * @param $id
     * @return string
     */
    public function createUri($entityName, $id): string
    {
        //TODO: change how this uri is generated? use $entityName? or just remove $entityName
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $uri = "https://";
        } else {
            $uri = "http://";
        }
        $uri .= $_SERVER['HTTP_HOST'];
        return $uri . '/object_entities/' . $id;
    }
}
