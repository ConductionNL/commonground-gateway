<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\File;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use App\Security\User\AuthenticationUser;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use JWadhams\JsonLogic as jsonLogic;
use Ramsey\Uuid\Uuid;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class ValidationService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private GatewayService $gatewayService;
    private CacheInterface $cache;
    public $promises = []; //TODO: use ObjectEntity->promises instead!
    public $postPromiseUris = [];
    public $putPromiseUris = [];
    public $createdObjects = [];
    public $removeObjectsOnPut = [];
    public $removeObjectsNotMultiple = [];
    public $notifications = [];
    private ?Request $request = null;
    private AuthorizationService $authorizationService;
    private SessionInterface $session;
    private ConvertToGatewayService $convertToGatewayService;
    private ObjectEntityService $objectEntityService;
    private TranslationService $translationService;
    private ParameterBagInterface $parameterBag;
    private FunctionService $functionService;
    private LogService $logService;
    private TokenStorageInterface $tokenStorage;
    private bool $ignoreErrors;

    public function __construct(
        EntityManagerInterface $em,
        CommonGroundService $commonGroundService,
        GatewayService $gatewayService,
        CacheInterface $cache,
        AuthorizationService $authorizationService,
        SessionInterface $session,
        ConvertToGatewayService $convertToGatewayService,
        ObjectEntityService $objectEntityService,
        TranslationService $translationService,
        ParameterBagInterface $parameterBag,
        FunctionService $functionService,
        LogService $logService,
        TokenStorageInterface $tokenStorage
    ) {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->gatewayService = $gatewayService;
        $this->cache = $cache;
        $this->authorizationService = $authorizationService;
        $this->session = $session;
        $this->convertToGatewayService = $convertToGatewayService;
        $this->objectEntityService = $objectEntityService;
        $this->translationService = $translationService;
        $this->parameterBag = $parameterBag;
        $this->functionService = $functionService;
        $this->logService = $logService;
        $this->ignoreErrors = false;
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function setIgnoreErrors(bool $ignoreErrors): void
    {
        $this->ignoreErrors = $ignoreErrors;
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param array        $post
     * @param bool|null    $dontCheckAuth
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    public function validateEntity(ObjectEntity $objectEntity, array $post, ?bool $dontCheckAuth = false): ObjectEntity
    {
        $entity = $objectEntity->getEntity();

        foreach ($entity->getAttributes() as $attribute) {
            // Check attribute function
            if ($attribute->getFunction() !== 'noFunction') {
                $objectEntity = $this->handleAttributeFunction($objectEntity, $attribute);
                // Attributes with a function should (always?) have readOnly set to true, making sure to not save this attribute(/value) in any other way.
            }

            // Skip readOnly's
            if ($attribute->getReadOnly()) {
                continue;
            }

            // Only save the attributes that are used.
            if (!is_null($objectEntity->getEntity()->getUsedProperties()) && !in_array($attribute->getName(), $objectEntity->getEntity()->getUsedProperties())) {
                if (key_exists($attribute->getName(), $post)) {
                    // throw an error if a value is given for a disabled attribute.
                    $objectEntity->addError($attribute->getName(), 'This attribute is disabled for this entity');
                }
                continue;
            }

            // Do not change immutable attributes!
            if ($this->request->getMethod() == 'PUT' && $attribute->getImmutable()) {
                if (key_exists($attribute->getName(), $post)) {
                    $objectEntity->addError($attribute->getName(), 'This attribute is immutable, it can\'t be changed');
                    unset($post[$attribute->getName()]);
                }
                continue;
            } // Do not post 'unsetable' attributes!
            elseif ($this->request->getMethod() == 'POST' && $attribute->getUnsetable()) {
                if (key_exists($attribute->getName(), $post)) {
                    $objectEntity->addError($attribute->getName(), 'This attribute is not allowed to be set on creation, it can only be set or changed after creation of: ['.$attribute->getEntity()->getName().']');
                    unset($post[$attribute->getName()]);
                }
                continue;
            }

            // Check if we have a value to validate ( a value is given in the post body for this attribute, can be null )
            if (key_exists($attribute->getName(), $post)) {
                $objectEntity = $this->validateAttribute($objectEntity, $attribute, $post[$attribute->getName()], $dontCheckAuth);
            }
            // Check if a defaultValue is set (TODO: defaultValue should maybe be a Value object, so that defaultValue can be something else than a string)
            elseif ($attribute->getDefaultValue()) {
                //                $objectEntity->getValueObject($attribute)->setValue($attribute->getDefaultValue());
                $objectEntity = $this->validateAttribute($objectEntity, $attribute, $attribute->getDefaultValue(), $dontCheckAuth);
            }
            // Check if this field is nullable
            elseif ($attribute->getNullable() === true) {
                $objectEntity->setValue($attribute, null);
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
                            continue;
                        }
                    }
                }
                $objectEntity->addError($attribute->getName(), 'This attribute is required');
            } elseif ($attribute->getNullable() === false) {
                if ($this->request->getMethod() == 'PUT') {
                    $value = $objectEntity->getValue($attribute);
                    if (is_null($value) || ($attribute->getType() != 'boolean') && (!$value || empty($value))) {
                        $objectEntity->addError($attribute->getName(), 'This attribute can not be null');
                    }
                } elseif ($this->request->getMethod() == 'POST') {
                    $objectEntity->addError($attribute->getName(), 'This attribute can not be null');
                }
            } elseif ($this->request->getMethod() == 'POST') {
                // handling the setting to null of exisiting variables
                $objectEntity->setValue($attribute, null);
            }
        }
        // Check post for not allowed properties
        foreach ($post as $key => $value) {
            if (!$entity->getAttributeByName($key) && $key != 'id' && $key != '@organization') {
                $objectEntity->addError($key, 'Does not exist on this property');
            }
        }

        // Dit is de plek waarop we weten of er een api call moet worden gemaakt
        if (!$objectEntity->getHasErrors()) {
            if ($objectEntity->getEntity()->getGateway()) {
                // We notify the notification component here in the createPromise function:
                $promise = $this->createPromise($objectEntity, $post);
                $this->promises[] = $promise; //TODO: use ObjectEntity->promises instead!
                $objectEntity->addPromise($promise);
            } else {
                if (!$objectEntity->getUri()) {
                    // Lets make sure we always set the uri
                    $objectEntity->setUri($this->createUri($objectEntity));
                }
                // Notify notification component
                $this->notifications[] = [
                    'objectEntity' => $objectEntity,
                    'method'       => $this->request->getMethod(),
                ];
            }
            if (!$objectEntity->getSelf()) {
                // Lets make sure we always set the self (@id)
                $objectEntity->setSelf($this->createSelf($objectEntity));
            }
        }

        if (array_key_exists('@organization', $post) && $objectEntity->getOrganization() != $post['@organization']) {
            $objectEntity->setOrganization($post['@organization']);
        }

        return $objectEntity;
    }

    private function getUserName(): string
    {
        $user = $this->security->getUser();

        if ($user instanceof AuthenticationUser) {
            return $user->getName();
        }

        return '';
    }

    /**
     * Handles saving the value for an Attribute when the Attribute has a function set. A function makes it 'function' (/behave) differently.
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    private function handleAttributeFunction(ObjectEntity $objectEntity, Attribute $attribute): ObjectEntity
    {
        switch ($attribute->getFunction()) {
            case 'id':
                $objectEntity->setValue($attribute, $objectEntity->getId()->toString());
                // Note: attributes with function = id should also be readOnly and type=string
                break;
            case 'self':
                $self = $objectEntity->getSelf() ?? $objectEntity->setSelf($this->createSelf($objectEntity))->getSelf();
                $objectEntity->setValue($attribute, $self);
                // Note: attributes with function = self should also be readOnly and type=string
                break;
            case 'uri':
                $uri = $objectEntity->getUri() ?? $objectEntity->setUri($this->createUri($objectEntity))->getUri();
                $objectEntity->setValue($attribute, $uri);
                // Note: attributes with function = uri should also be readOnly and type=string
                break;
            case 'externalId':
                $objectEntity->setValue($attribute, $objectEntity->getExternalId());
                // Note: attributes with function = externalId should also be readOnly and type=string
                break;
            case 'dateCreated':
                $objectEntity->setValue($attribute, $objectEntity->getDateCreated()->format("Y-m-d\TH:i:sP"));
                // Note: attributes with function = dateCreated should also be readOnly and type=string||date||datetime
                break;
            case 'dateModified':
                $objectEntity->setValue($attribute, $objectEntity->getDateModified()->format("Y-m-d\TH:i:sP"));
                // Note: attributes with function = dateModified should also be readOnly and type=string||date||datetime
                break;
            case 'userName':
                $objectEntity->getValue($attribute) ?? $objectEntity->setValue($attribute, $this->getUserName());
                break;
        }

        return $objectEntity;
    }

    /**
     * TODO: docs.
     *
     * @param ObjectEntity $objectEntity
     * @param Attribute    $attribute
     * @param $value
     * @param bool $dontCheckAuth
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    private function validateAttribute(ObjectEntity $objectEntity, Attribute $attribute, $value, bool $dontCheckAuth = false): ObjectEntity
    {
        if (!$dontCheckAuth) {
            try {
                if (!$this->objectEntityService->checkOwner($objectEntity) && !($attribute->getDefaultValue() && $value === $attribute->getDefaultValue())) {
                    $this->authorizationService->checkAuthorization([
                        'method'    => $objectEntity->getUri() ? 'PUT' : 'POST',
                        'attribute' => $attribute,
                        'value'     => $value,
                    ]);
                }
            } catch (AccessDeniedException $e) {
                $objectEntity->addError($attribute->getName(), $e->getMessage());

                return $objectEntity;
            }
        }

        // Check if value is null, and if so, check if attribute has a defaultValue and else if it is nullable
        if (is_null($value) || ($attribute->getType() != 'boolean') && (!$value || empty($value))) {
            if ($attribute->getNullable() === false) {
                $objectEntity->addError($attribute->getName(), 'Expects '.$attribute->getType().', NULL given. (This attribute is not nullable)');
            } elseif ($attribute->getMultiple() && $value === []) {
                $valueObject = $objectEntity->getValueObject($attribute);
                if ($attribute->getType() == 'object') {
                    foreach ($valueObject->getObjects() as $object) {
                        // If we are not re-adding this object...
                        $this->removeObjectsOnPut[] = [
                            'valueObject' => $valueObject,
                            'object'      => $object,
                        ];
                    }
                    $valueObject->getObjects()->clear();
                } else {
                    $valueObject->setValue([]);
                }
            } else {
                $objectEntity->setValue($attribute, null);
            }
            // We should not continue other validations after this!
            return $objectEntity;
        }

        if ($attribute->getMultiple()) {
            // If multiple, this is an array, validation for an array:
            $objectEntity = $this->validateAttributeMultiple($objectEntity, $attribute, $value, $dontCheckAuth);
        } else {
            // Multiple == false, so this should not be an array (unless it is an object or a file)
            if (is_array($value) && $attribute->getType() != 'array' && $attribute->getType() != 'object' && $attribute->getType() != 'file') {
                $objectEntity->addError($attribute->getName(), 'Expects '.$attribute->getType().', array given. (Multiple is not set for this attribute)');

                // Lets not continue validation if $value is an array (because this will cause weird 500s!!!)
                return $objectEntity;
            }
            $objectEntity = $this->validateAttributeType($objectEntity, $attribute, $value, $dontCheckAuth);
            $objectEntity = $this->validateAttributeFormat($objectEntity, $attribute, $value);
        }

        if ($attribute->getMustBeUnique()) {
            $objectEntity = $this->validateAttributeUnique($objectEntity, $attribute, $value);
            // We should not continue other validations after this!
            if ($objectEntity->getHasErrors()) {
                return $objectEntity;
            }
        }

        //        $this->validateLogic($objectEntity->getValueObject($attribute)); // TODO maybe remove or place somewhere else than here?
        // if no errors we can set the value (for type object this is already done in validateAttributeType, other types we do it here,
        // because when we use validateAttributeType to validate items in an array, we dont want to set values for that)
        if ((!$objectEntity->getHasErrors() || $this->ignoreErrors) && $attribute->getType() != 'object' && $attribute->getType() != 'file') {
            $objectEntity->setValue($attribute, $value);
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
                    if (!$valueObject->getAttribute()->getCaseSensitive()) {
                        return strtolower($valueObject->getStringValue()) == strtolower($value);
                    }

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

        if (count($values) > 0 && !(count($values) == 1 && $objectEntity->getValue($attribute) == $value)) {
            if ($attribute->getType() == 'boolean') {
                $value = $value ? 'true' : 'false';
            }
            $strValue = $attribute->getCaseSensitive() ? $value : strtolower($value);
            $objectEntity->addError($attribute->getName(), 'Must be unique, there already exists an object with this value: '.$strValue.'.');
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
    private function validateAttributeMultiple(ObjectEntity $objectEntity, Attribute $attribute, $value, ?bool $dontCheckAuth = false): ObjectEntity
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
            // TODO: maybe move and merge all this code to the validateAttributeType function under type 'object'. NOTE: this code works very different, so be carefull!!!
            // This is an array of objects
            $valueObject = $objectEntity->getValueObject($attribute);
            $saveSubObjects = new ArrayCollection(); // collection to store all new subobjects in before we actually connect them to the value
            foreach ($value as $key => $object) {
                if (!is_array($object)) {
                    // If we want to connect an existing object using a string uuid: "uuid"
                    if (is_string($object)) {
                        if (Uuid::isValid($object) == false) {
                            // We should also allow commonground Uri's like: https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
                            // TODO: support /$attribute->getObject()->getEndpoint()/uuid?
                            // if ($object == $attribute->getObject()->getGateway()->getLocation() . '/' . $attribute->getObject()->getEndpoint() . '/' . $this->commonGroundService->getUuidFromUrl($object)) {
                            $object = $this->commonGroundService->getUuidFromUrl($object);
                            // } else {
                            //     if (!array_key_exists($attribute->getName(), $objectEntity->getErrors())) {
                            //         $objectEntity->addError($attribute->getName(), 'Multiple is set for this attribute. Expecting an array of objects (array, uuid or uri).');
                            //     }
                            //     $objectEntity->addError($attribute->getName() . '[' . $key . ']', 'The given value (' . $object . ') is not a valid object, a valid uuid or a valid uri (' . $attribute->getObject()->getGateway()->getLocation() . '/' . $attribute->getObject()->getEndpoint() . '/uuid).');
                            //     continue;
                            // }
                        }
                        // Look for an existing ObjectEntity with its id or externalId set to this string, else look in external component with this uuid.
                        // Always create a new ObjectEntity if we find an exernal object but it has no ObjectEntity yet. (see convertToGatewayObject)

                        // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
                        if (!$subObject = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'id' => $object])) {
                            if (!$subObject = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'externalId' => $object])) {
                                // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside of the gateway for an existing object.
                                $subObject = $this->convertToGatewayService->convertToGatewayObject($attribute->getObject(), null, $object, $valueObject, $objectEntity);
                                if (!$subObject) {
                                    $objectEntity->addError($attribute->getName().'['.$key.']', 'Could not find an object with id '.$object.' of type '.$attribute->getObject()->getName());
                                    continue;
                                }
                            }
                        }

                        // object toevoegen
                        $saveSubObjects->add($subObject);
                        continue;
                    } else {
                        $objectEntity->addError($attribute->getName(), 'Multiple is set for this attribute. Expecting an array of objects (array or uuid).');
                        continue;
                    }
                }
                // If we are doing a PUT with a subObject that contains an id, find the object with this id and update it.
                if ($this->request->getMethod() == 'PUT' && array_key_exists('id', $object)) {
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
                        $subObject = $this->convertToGatewayService->convertToGatewayObject($attribute->getObject(), null, $object['id'], $valueObject, $objectEntity);

                        if (!$subObject) {
                            $objectEntity->addError($attribute->getName(), 'Could not find an object with id '.$object['id'].' of type '.$attribute->getObject()->getName());
                            continue;
                        }

                        // object toevoegen
                        $saveSubObjects->add($subObject);
                        continue;
                    } elseif (count($subObject) > 1) {
                        $objectEntity->addError($attribute->getName(), 'Found more than 1 object with id '.$object['id'].' of type '.$attribute->getObject()->getName());
                        continue;
                    } else {
                        $subObject = $subObject->first();
                    }
                }
                // If we are doing a PUT with a single subObject (and it contains no id) and the existing mainObject only has a single subObject, use the existing subObject and update that.
                elseif ($this->request->getMethod() == 'PUT' && count($value) == 1 && count($valueObject->getObjects()) == 1) {
                    $subObject = $valueObject->getObjects()->first();
                    $object['id'] = $subObject->getExternalId();
                }
                // Create a new subObject (ObjectEntity)
                else {
                    //Lets do a cascade check here. As in, if cascade = false we should expect an uuid not an array/body
                    if (!$attribute->getCascade() && !is_string($value)) {
                        $objectEntity->addError($attribute->getName(), 'Is not a string but '.$attribute->getName().' is not allowed to cascade, provide an uuid as string instead');
                        continue;
                    }

                    $subObject = new ObjectEntity();
                    $subObject->setEntity($attribute->getObject());
                    $subObject->addSubresourceOf($valueObject);
                    $this->createdObjects[] = $subObject;
                }

                $subObject->setSubresourceIndex($key);
                $subObject = $this->validateEntity($subObject, $object, $dontCheckAuth);
                !$dontCheckAuth && $this->objectEntityService->handleOwner($subObject); // Do this after all CheckAuthorization function calls

                // We need to persist if this is a new ObjectEntity in order to set and getId to generate the uri...
                $subObject->setUri($this->createUri($subObject));
                // Set organization for this object
                if (count($subObject->getSubresourceOf()) > 0 && !empty($subObject->getSubresourceOf()->first()->getObjectEntity()->getOrganization())) {
                    $subObject->setOrganization($subObject->getSubresourceOf()->first()->getObjectEntity()->getOrganization());
                    $subObject->setApplication($subObject->getSubresourceOf()->first()->getObjectEntity()->getApplication());
                } else {
                    $subObject->setOrganization($this->session->get('activeOrganization'));
                    $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
                    $subObject->setApplication(!empty($application) ? $application : null);
                }
                $subObject = $this->functionService->handleFunction($subObject, $subObject->getEntity()->getFunction(), [
                    'method'           => $this->request->getMethod(),
                    'uri'              => $subObject->getUri(),
                    'organizationType' => array_key_exists('type', $object) ? $object['type'] : null,
                    'userGroupName'    => array_key_exists('name', $object) ? $object['name'] : null,
                ]);

                // object toevoegen
                $saveSubObjects->add($subObject);
            }
            // If we are doing a put, we want to actually clear (or remove) objects connected to this valueObject we no longer need
            if ($this->request->getMethod() == 'PUT' && !$objectEntity->getHasErrors()) {
                foreach ($valueObject->getObjects() as $object) {
                    // If we are not re-adding this object...
                    if (!$saveSubObjects->contains($object)) {
                        $this->removeObjectsOnPut[] = [
                            'valueObject' => $valueObject,
                            'object'      => $object,
                        ];
                    }
                }
                $valueObject->getObjects()->clear();
            }
            // Actually add the objects to the valueObject
            foreach ($saveSubObjects as $saveSubObject) {
                // If we have inversedBy on this attribute
                if ($attribute->getInversedBy()) {
                    $inversedByValue = $saveSubObject->getValueObject($attribute->getInversedBy());
                    if (!$inversedByValue->getObjects()->contains($objectEntity)) { // $valueObject->getObjectEntity() = $objectEntity
                        // If inversedBy attribute is not multiple it should only have one object connected to it
                        if (!$attribute->getInversedBy()->getMultiple() and count($inversedByValue->getObjects()) > 0) {
                            // Remove old objects
                            foreach ($inversedByValue->getObjects() as $object) {
                                // Clear any objects and there parent relations (subresourceOf) to make sure we only can have one object connected.
                                $this->removeObjectsNotMultiple[] = [
                                    'valueObject' => $inversedByValue,
                                    'object'      => $object,
                                ];
                            }
                        }
                    }
                }
                $valueObject->addObject($saveSubObject);
            }
        } elseif ($attribute->getType() == 'file') {
            // TODO: maybe move and merge all this code to the validateAttributeType function under type 'file'. NOTE: this code works very different, so be carefull!!!
            // This is an array of files
            $valueObject = $objectEntity->getValueObject($attribute);
            foreach ($value as $key => $file) {
                // Validations
                if (!is_array($file)) {
                    $objectEntity->addError($attribute->getName(), 'Multiple is set for this attribute. Expecting an array of files (arrays).');
                    continue;
                }
                if (!array_key_exists('base64', $file)) {
                    $objectEntity->addError($attribute->getName().'['.$key.'].base64', 'Expects an array with at least key base64 with a valid base64 encoded string value. (could also contain key filename)');
                    continue;
                }

                // Validate (and create/update) this file
                $objectEntity = $this->validateFile($objectEntity, $attribute, $this->base64ToFileArray($file, $key));
            }
        } else {
            foreach ($value as $item) {
                $objectEntity = $this->validateAttributeType($objectEntity, $attribute, $item, $dontCheckAuth);
                $objectEntity = $this->validateAttributeFormat($objectEntity, $attribute, $value);
            }
        }

        return $objectEntity;
    }

    /**
     * This function hydrates an object(tree) (new style).
     *
     * The act of hydrating means filling objects with values from a post
     *
     * @param ObjectEntity $objectEntity
     *
     * @throws Exception
     *
     * @return ObjectEntity
     */
    private function hydrate(ObjectEntity $objectEntity, $post): ObjectEntity
    {
        $entity = $objectEntity->getEntity();
        foreach ($entity->getAttributes() as $attribute) {
            // Check if we have a value to validate ( a value is given in the post body for this attribute, can be null )
            if (key_exists($attribute->getName(), $post)) {
                $objectEntity = $objectEntity->setValue($attribute, $post[$attribute->getName()]);
            }
            // Check if a defaultValue is set (TODO: defaultValue should maybe be a Value object, so that defaultValue can be something else than a string)
            elseif ($attribute->getDefaultValue()) {
                $objectEntity = $objectEntity->setValue($attribute, $attribute->getDefaultValue());
            }
            /* @todo this feels wierd, should we PUT "value":null if we want to delete? */
            //else {
            //    // handling the setting to null of exisiting variables
            //    $objectEntity->setValue($attribute, null);
            //}
        }

        // Check post for not allowed properties
        foreach ($post as $key => $value) {
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
                foreach ($value->getValue() as $key => $tempValue) {
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

        $validator = $this->validateType($valueObject, $validator, $value);
        $validator = $this->validateFormat($valueObject, $validator);
        //        $validator = $this->validateValidations($valueObject, $validator); // TODO: remove? (old version)
        $objectEntity = $this->validateLogic($valueObject); // New Validation logic...

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
        $format = str_replace(['rsin'], ['bsn'], $format);

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

    private function validateLogic(Value $valueObject): ObjectEntity
    {
        $objectEntity = $valueObject->getObjectEntity();
        // Let turn the value into an array TO ARRAY functie
        $value = $objectEntity->toArray();

        // Check required
        $rule = $valueObject->getAttribute()->getRequiredIf();
        //        var_dump($rule);

        if ($rule && jsonLogic::apply(json_decode($rule, true), $value)) {
            $objectEntity->addError($valueObject->getAttribute()->getName(), 'This value is REQUIRED because of the following JSON Logic: '.$rule);
        }

        // Check forbidden
        $rule = $valueObject->getAttribute()->getForbiddenIf();
        if ($rule && jsonLogic::apply(json_decode($rule, true), $value)) {
            $objectEntity->addError($valueObject->getAttribute()->getName(), 'This value is FORBIDDEN because of the following JSON Logic: '.$rule);
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
    private function validateAttributeType(ObjectEntity $objectEntity, Attribute $attribute, $value, ?bool $dontCheckAuth = false): ObjectEntity
    {
        // Validation for enum (if attribute type is not object or boolean)
        if ($attribute->getEnum() && !in_array(strtolower($value), array_map('strtolower', $attribute->getEnum())) && $attribute->getType() != 'object' && $attribute->getType() != 'boolean') {
            $enumValues = '['.implode(', ', array_map('strtolower', $attribute->getEnum())).']';
            $errorMessage = $attribute->getMultiple() ? 'All items in this array must be one of the following values: ' : 'Must be one of the following values: ';
            $objectEntity->addError($attribute->getName(), $errorMessage.$enumValues.' ('.strtolower($value).' is not).');
        }

        // Do validation for attribute depending on its type
        // todo: NOTE: Attribute->getMultiple = true for type object & file is handled somewhere else, see: validateAttributeMultiple()
        switch ($attribute->getType()) {
            case 'object':
                // lets see if we already have a sub object
                $valueObject = $objectEntity->getValueObject($attribute);

                // If this object is given as a uuid (string) it should be valid, if not throw error
                if (is_string($value) && Uuid::isValid($value) == false) {
                    // We should also allow commonground Uri's like: https://taalhuizen-bisc.commonground.nu/api/v1/wrc/organizations/008750e5-0424-440e-aea0-443f7875fbfe
                    // TODO: support /$attribute->getObject()->getEndpoint()/uuid?
                    if (!$attribute->getObject()) {
                        $objectEntity->addError($attribute->getName(), 'The attribute has no entity (object)');
                        break;
                    } elseif (!$attribute->getObject()->getGateway()) {
                        $objectEntity->addError($attribute->getName(), 'The attribute->object has no gateway');
                        break;
                    } elseif (!$attribute->getObject()->getGateway()->getLocation()) {
                        $objectEntity->addError($attribute->getName(), 'The attribute->object->gateway has no location');
                        break;
                    } else {
                        if ($value == $attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($value)) {
                            $value = $this->commonGroundService->getUuidFromUrl($value);
                        } else {
                            $objectEntity->addError($attribute->getName(), 'The given value ('.$value.') is not a valid object, a valid uuid or a valid uri ('.$attribute->getObject()->getGateway()->getLocation().'/'.$attribute->getObject()->getEndpoint().'/uuid).');
                            break;
                        }
                    }
                }

                // Lets check for cascading
                if (!$attribute->getCascade() && !is_string($value)) {
                    $objectEntity->addError($attribute->getName(), 'Is not a string but '.$attribute->getName().' is not allowed to cascade, provide an uuid as string instead');
                    break;
                }

                // Lets handle the stuf
                // If we are not cascading and value is a string, than value should be an id.
                if (is_string($value)) {
                    // Look for an existing ObjectEntity with its id or externalId set to this string, else look in external component with this uuid.
                    // Always create a new ObjectEntity if we find an exernal object but it has no ObjectEntity yet.

                    // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
                    if (!$subObject = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'id' => $value])) {
                        if (!$subObject = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $attribute->getObject(), 'externalId' => $value])) {
                            // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside of the gateway for an existing object.
                            $subObject = $this->convertToGatewayService->convertToGatewayObject($attribute->getObject(), null, $value, $valueObject, $objectEntity);
                            if (!$subObject) {
                                $objectEntity->addError($attribute->getName(), 'Could not find an object with id '.$value.' of type '.$attribute->getObject()->getName());
                                break;
                            }
                        }
                    }

                    // Object toevoegen
                    // If we have inversedBy on this attribute
                    if ($attribute->getInversedBy()) {
                        $inversedByValue = $subObject->getValueObject($attribute->getInversedBy());
                        if (!$inversedByValue->getObjects()->contains($objectEntity)) { // $valueObject->getObjectEntity() = $objectEntity
                            // If inversedBy attribute is not multiple it should only have one object connected to it
                            if (!$attribute->getInversedBy()->getMultiple() and count($inversedByValue->getObjects()) > 0) {
                                // Remove old objects
                                foreach ($inversedByValue->getObjects() as $object) {
                                    // Clear any objects and there parent relations (subresourceOf) to make sure we only can have one object connected.
                                    $this->removeObjectsNotMultiple[] = [
                                        'valueObject' => $inversedByValue,
                                        'object'      => $object,
                                    ];
                                }
                            }
                        }
                    }
                    $valueObject->getObjects()->clear(); // We start with a default object
                    $valueObject->addObject($subObject);
                    break;
                }

                if (!$valueObject->getValue()) {
                    $subObject = new ObjectEntity();
                    $subObject->setEntity($attribute->getObject());
                    $subObject->addSubresourceOf($valueObject);
                    $this->createdObjects[] = $subObject;
                    if ($attribute->getObject()->getFunction() === 'organization') {
                        $subObject = $this->functionService->createOrganization($subObject, $this->createUri($subObject), array_key_exists('type', $value) ? $value['type'] : $subObject->getValue('type'));
                    } else {
                        $subObject->setOrganization($this->session->get('activeOrganization'));
                    }
                    $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
                    $subObject->setApplication(!empty($application) ? $application : null);
                } else {
                    $subObject = $valueObject->getValue();
                }
                $subObject = $this->validateEntity($subObject, $value, $dontCheckAuth);
                !$dontCheckAuth && $this->objectEntityService->handleOwner($subObject); // Do this after all CheckAuthorization function calls
                $this->em->persist($subObject);

                // If no errors we can push it into our object
                if (!$objectEntity->getHasErrors()) {
                    // TODO: clear objects, add to removeObjectsNotMultiple if needed and use add object ipv setValue
                    $objectEntity->setValue($attribute, $subObject);
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
            case 'array':
                if (!is_array($value)) {
                    $objectEntity->addError($attribute->getName(), 'Contians a value that is not an array, provide an array as string instead');
                    break;
                }
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
        $value = $objectEntity->getValueObject($attribute);
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
        if ($attribute->getFileTypes() && !in_array($fileArray['mimeType'], $attribute->getFileTypes())) {
            $objectEntity->addError($attribute->getName().$key.'.base64', 'Expects a file with on of these mime types: ['.implode(', ', $attribute->getFileTypes()).'], not '.$fileArray['mimeType'].'. ('.$shortBase64String.')');
        }
        // Validate extension
        if ($attribute->getFileTypes() && empty(array_intersect($this->mimeToExt(null, $fileArray['extension']), $attribute->getFileTypes()))) {
            $objectEntity->addError($attribute->getName().$key.'.base64', 'Expects a file with on of these mime types: ['.implode(', ', $attribute->getFileTypes()).'], none of these equal extension '.$fileArray['extension'].'. ('.$fileArray['name'].')');
        }
        // Validate if mime type and extension match
        if ($this->mimeToExt($fileArray['mimeType']) != strtolower($fileArray['extension'])) {
            $objectEntity->addError($attribute->getName().$key.'.base64', 'Extension ('.strtolower($fileArray['extension']).') does not match the mime type ('.$fileArray['mimeType'].' -> '.$this->mimeToExt($fileArray['mimeType']).'). ('.$shortBase64String.')');
        }

        if ($fileArray['name']) {
            // Find file by filename (this can be the uuid of the file object)
            $fileObject = $value->getFiles()->filter(function (File $item) use ($fileArray) {
                return $item->getName() == $fileArray['name'];
            });
            if (count($fileObject) > 1) {
                $objectEntity->addError($attribute->getName().$key.'.name', 'More than 1 file found with this name: '.$fileArray['name']);
            }
            // (If we found 0 or 1 fileObjects, continue...)
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
     * Converts a mime type to an extension (or find all mime_types with an extension).
     *
     * @param $mime
     * @param null $ext
     *
     * @return array|false|string
     */
    private function mimeToExt($mime, $ext = null)
    {
        $mime_map = [
            'video/3gpp2'                                                               => '3g2',
            'video/3gp'                                                                 => '3gp',
            'video/3gpp'                                                                => '3gp',
            'application/x-compressed'                                                  => '7zip',
            'audio/x-acc'                                                               => 'aac',
            'audio/ac3'                                                                 => 'ac3',
            'application/postscript'                                                    => 'ai',
            'audio/x-aiff'                                                              => 'aif',
            'audio/aiff'                                                                => 'aif',
            'audio/x-au'                                                                => 'au',
            'video/x-msvideo'                                                           => 'avi',
            'video/msvideo'                                                             => 'avi',
            'video/avi'                                                                 => 'avi',
            'application/x-troff-msvideo'                                               => 'avi',
            'application/macbinary'                                                     => 'bin',
            'application/mac-binary'                                                    => 'bin',
            'application/x-binary'                                                      => 'bin',
            'application/x-macbinary'                                                   => 'bin',
            'image/bmp'                                                                 => 'bmp',
            'image/x-bmp'                                                               => 'bmp',
            'image/x-bitmap'                                                            => 'bmp',
            'image/x-xbitmap'                                                           => 'bmp',
            'image/x-win-bitmap'                                                        => 'bmp',
            'image/x-windows-bmp'                                                       => 'bmp',
            'image/ms-bmp'                                                              => 'bmp',
            'image/x-ms-bmp'                                                            => 'bmp',
            'application/bmp'                                                           => 'bmp',
            'application/x-bmp'                                                         => 'bmp',
            'application/x-win-bitmap'                                                  => 'bmp',
            'application/cdr'                                                           => 'cdr',
            'application/coreldraw'                                                     => 'cdr',
            'application/x-cdr'                                                         => 'cdr',
            'application/x-coreldraw'                                                   => 'cdr',
            'image/cdr'                                                                 => 'cdr',
            'image/x-cdr'                                                               => 'cdr',
            'zz-application/zz-winassoc-cdr'                                            => 'cdr',
            'application/mac-compactpro'                                                => 'cpt',
            'application/pkix-crl'                                                      => 'crl',
            'application/pkcs-crl'                                                      => 'crl',
            'application/x-x509-ca-cert'                                                => 'crt',
            'application/pkix-cert'                                                     => 'crt',
            'text/css'                                                                  => 'css',
            'text/x-comma-separated-values'                                             => 'csv',
            'text/comma-separated-values'                                               => 'csv',
            'application/vnd.msexcel'                                                   => 'csv',
            'application/x-director'                                                    => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/x-dvi'                                                         => 'dvi',
            'message/rfc822'                                                            => 'eml',
            'application/x-msdownload'                                                  => 'exe',
            'video/x-f4v'                                                               => 'f4v',
            'audio/x-flac'                                                              => 'flac',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/gpg-keys'                                                      => 'gpg',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'application/mac-binhex40'                                                  => 'hqx',
            'application/mac-binhex'                                                    => 'hqx',
            'application/x-binhex40'                                                    => 'hqx',
            'application/x-mac-binhex40'                                                => 'hqx',
            'text/html'                                                                 => 'html',
            'image/x-icon'                                                              => 'ico',
            'image/x-ico'                                                               => 'ico',
            'image/vnd.microsoft.icon'                                                  => 'ico',
            'text/calendar'                                                             => 'ics',
            'application/java-archive'                                                  => 'jar',
            'application/x-java-application'                                            => 'jar',
            'application/x-jar'                                                         => 'jar',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'application/x-javascript'                                                  => 'js',
            'application/json'                                                          => 'json',
            'text/json'                                                                 => 'json',
            'application/vnd.google-earth.kml+xml'                                      => 'kml',
            'application/vnd.google-earth.kmz'                                          => 'kmz',
            'text/x-log'                                                                => 'log',
            'audio/x-m4a'                                                               => 'm4a',
            'audio/mp4'                                                                 => 'm4a',
            'application/vnd.mpegurl'                                                   => 'm4u',
            'audio/midi'                                                                => 'mid',
            'application/vnd.mif'                                                       => 'mif',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/oda'                                                           => 'oda',
            'audio/ogg'                                                                 => 'ogg',
            'video/ogg'                                                                 => 'ogg',
            'application/ogg'                                                           => 'ogg',
            'font/otf'                                                                  => 'otf',
            'application/x-pkcs10'                                                      => 'p10',
            'application/pkcs10'                                                        => 'p10',
            'application/x-pkcs12'                                                      => 'p12',
            'application/x-pkcs7-signature'                                             => 'p7a',
            'application/pkcs7-mime'                                                    => 'p7c',
            'application/x-pkcs7-mime'                                                  => 'p7c',
            'application/x-pkcs7-certreqresp'                                           => 'p7r',
            'application/pkcs7-signature'                                               => 'p7s',
            'application/pdf'                                                           => 'pdf',
            'application/octet-stream'                                                  => 'pdf',
            'application/x-x509-user-cert'                                              => 'pem',
            'application/x-pem-file'                                                    => 'pem',
            'application/pgp'                                                           => 'pgp',
            'application/x-httpd-php'                                                   => 'php',
            'application/php'                                                           => 'php',
            'application/x-php'                                                         => 'php',
            'text/php'                                                                  => 'php',
            'text/x-php'                                                                => 'php',
            'application/x-httpd-php-source'                                            => 'php',
            'image/png'                                                                 => 'png',
            'image/x-png'                                                               => 'png',
            'application/powerpoint'                                                    => 'ppt',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.ms-office'                                                 => 'ppt',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'audio/x-realaudio'                                                         => 'ra',
            'audio/x-pn-realaudio'                                                      => 'ram',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'audio/x-pn-realaudio-plugin'                                               => 'rpm',
            'application/x-pkcs7'                                                       => 'rsa',
            'text/rtf'                                                                  => 'rtf',
            'text/richtext'                                                             => 'rtx',
            'video/vnd.rn-realvideo'                                                    => 'rv',
            'application/x-stuffit'                                                     => 'sit',
            'application/smil'                                                          => 'smil',
            'text/srt'                                                                  => 'srt',
            'image/svg+xml'                                                             => 'svg',
            'application/x-shockwave-flash'                                             => 'swf',
            'application/x-tar'                                                         => 'tar',
            'application/x-gzip-compressed'                                             => 'tgz',
            'image/tiff'                                                                => 'tiff',
            'font/ttf'                                                                  => 'ttf',
            'text/plain'                                                                => 'txt',
            'text/x-vcard'                                                              => 'vcf',
            'application/videolan'                                                      => 'vlc',
            'text/vtt'                                                                  => 'vtt',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'application/wbxml'                                                         => 'wbxml',
            'video/webm'                                                                => 'webm',
            'image/webp'                                                                => 'webp',
            'audio/x-ms-wma'                                                            => 'wma',
            'application/wmlc'                                                          => 'wmlc',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'font/woff'                                                                 => 'woff',
            'font/woff2'                                                                => 'woff2',
            'application/xhtml+xml'                                                     => 'xhtml',
            'application/excel'                                                         => 'xl',
            'application/msexcel'                                                       => 'xls',
            'application/x-msexcel'                                                     => 'xls',
            'application/x-ms-excel'                                                    => 'xls',
            'application/x-excel'                                                       => 'xls',
            'application/x-dos_ms_excel'                                                => 'xls',
            'application/xls'                                                           => 'xls',
            'application/x-xls'                                                         => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-excel'                                                  => 'xlsx',
            'application/xml'                                                           => 'xml',
            'text/xml'                                                                  => 'xml',
            'text/xsl'                                                                  => 'xsl',
            'application/xspf+xml'                                                      => 'xspf',
            'application/x-compress'                                                    => 'z',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
            'text/x-scriptzsh'                                                          => 'zsh',
        ];

        if ($ext) {
            $mime_types = [];
            foreach ($mime_map as $mime_type => $extension) {
                if ($extension == $ext) {
                    $mime_types[] = $mime_type;
                }
            }

            return $mime_types;
        }

        return $mime_map[$mime] ?? false;
    }

    /**
     * Create a file array (matching the Entity File) from an array containing at least a base64 string and maybe a filename (not required).
     *
     * @param array       $file
     * @param string|null $key
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

        // Create file data
        return [
            'name'      => array_key_exists('filename', $file) ? $file['filename'] : null,
            // Get extension from filename, and else from the mime_type
            'extension' => array_key_exists('filename', $file) ? pathinfo($file['filename'], PATHINFO_EXTENSION) : $this->mimeToExt($mime_type),
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
            'extension' => $file->getClientOriginalExtension() ?? $file->getClientMimeType() ? $this->mimeToExt($file->getClientMimeType()) : null,
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
        // if no format is provided we dont validate TODO validate uri
        if ($attribute->getFormat() == null || in_array($attribute->getFormat(), ['date', 'duration', 'uri', 'int64', 'byte', 'urn', 'reverse-dns'])) {
            return $objectEntity;
        }

        $format = $attribute->getFormat();

        // Let be a bit compasionate and compatable
        $format = str_replace(['telephone'], ['phone'], $format);
        $format = str_replace(['rsin'], ['bsn'], $format);

        // In order not to allow any respect/validation function to be called we explicatly call those containing formats
        $allowedValidations = ['countryCode', 'bsn', 'url', 'uuid', 'email', 'phone', 'json', 'dutch_pc4'];

        // new route
        if (in_array($format, $allowedValidations)) {
            if ($format == 'dutch_pc4') {
                //validate dutch_pc4
                if (!$this->validateDutchPC4($value)) {
                    $objectEntity->addError($attribute->getName(), 'This is not a valid Dutch postalCode');
                }
            } else {
                try {
                    Validator::$format()->check($value);
                } catch (ValidationException $exception) {
                    $objectEntity->addError($attribute->getName(), $exception->getMessage());
                }
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

        // Lets
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
                        $ubjectUri = '/'.$objectToUri->getEntity()->getEndpoint().'/'.$this->commonGroundService->getUuidFromUrl($objectToUri->getUri());
                    } else {
                        $ubjectUri = $objectToUri->getUri();
                    }
                    $post[$value->getAttribute()->getName()][] = $ubjectUri;
                }
            } elseif ($value->getObjects()->first()) {
                // If this object is from the same gateway as the main/parent object use: /entityName/uuid instead of the entire uri
                if (
                    $value->getAttribute()->getEntity()->getGateway() && $value->getObjects()->first()->getEntity()->getGateway()
                    && $value->getAttribute()->getEntity()->getGateway() === $value->getObjects()->first()->getEntity()->getGateway()
                ) {
                    $post[$value->getAttribute()->getName()] = '/'.$value->getObjects()->first()->getEntity()->getEndpoint().'/'.$value->getObjects()->first()->getExternalId();
                } else {
                    $post[$value->getAttribute()->getName()] = $value->getObjects()->first()->getUri();
                }
            }

            // Lets check if we actually want to send this to the gateway
            if (!$value->getAttribute()->getPersistToGateway()) {
                unset($post[$value->getAttribute()->getName()]);
            }
        }

        $post ?: $post = $objectEntity->toArray();

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
        $setOrganization = null;
        if (array_key_exists('@organization', $post)) {
            $setOrganization = $post['@organization'];
            unset($post['@organization']);
        }

        // Lets use the correct post type
        switch ($objectEntity->getEntity()->getGateway()->getType()) {
            case 'json':
                $post = json_encode($post);
                break;
            case 'soap':
                $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'S:Envelope']);
                $post = $this->translationService->parse($xmlEncoder->encode($this->translationService->dotHydrator(
                    $objectEntity->getEntity()->getToSoap()->getRequest() ? $xmlEncoder->decode($objectEntity->getEntity()->getToSoap()->getRequest(), 'xml') : [],
                    $objectEntity->toArray(),
                    $objectEntity->getEntity()->getToSoap()->getRequestHydration()
                ), 'xml', ['xml_encoding' => 'utf-8', 'remove_empty_tags' => true]), false);
                $headers['Content-Type'] = 'application/xml;charset=UTF-8';
                break;
            default:
                // @todo throw error
                break;
        }

        // TODO: if translationConfig overwrite promise if needed, still needs to be tested!
        if ($objectEntity->getEntity()->getTranslationConfig()) {
            $translationConfig = $objectEntity->getEntity()->getTranslationConfig();
            // Use switch to look for possible methods & overwrite/merge values if configured for this method
            switch ($method) {
                case 'POST':
                    if (array_key_exists('POST', $translationConfig)) {
                        // TODO make these if statements a function:
                        if (array_key_exists('method', $translationConfig['POST'])) {
                            $method = $translationConfig['POST']['method'];
                        }
                        if (array_key_exists('headers', $translationConfig['POST'])) {
                            $headers = array_merge($headers, $translationConfig['POST']['headers']);
                        }
                        if (array_key_exists('query', $translationConfig['POST'])) {
                            $query = array_merge($query, $translationConfig['POST']['query']);
                        }
                        if (array_key_exists('endpoint', $translationConfig['POST'])) {
                            $url = $objectEntity->getEntity()->getGateway()->getLocation().'/'.$translationConfig['POST']['endpoint'];
                        }
                    }
                    break;
                case 'PUT':
                    if (array_key_exists('PUT', $translationConfig)) {
                        // TODO make these if statements a function:
                        if (array_key_exists('method', $translationConfig['PUT'])) {
                            $method = $translationConfig['PUT']['method'];
                        }
                        if (array_key_exists('headers', $translationConfig['PUT'])) {
                            $headers = array_merge($headers, $translationConfig['PUT']['headers']);
                        }
                        if (array_key_exists('query', $translationConfig['PUT'])) {
                            $query = array_merge($query, $translationConfig['PUT']['query']);
                        }
                        if (array_key_exists('endpoint', $translationConfig['PUT'])) {
                            $newEndpoint = str_replace('{id}', $objectEntity->getExternalId(), $translationConfig['PUT']['endpoint']);
                            $url = $objectEntity->getEntity()->getGateway()->getLocation().'/'.$newEndpoint;
                        }
                    }
                    break;
            }
        }
        // log hier
        $logPost = !is_string($post) ? json_encode($post) : $post;
        $this->logService->saveLog($this->logService->makeRequest(), null, 12, $logPost, null, 'out');

        $promise = $this->commonGroundService->callService($component, $url, $post, $query, $headers, true, $method)->then(
            // $onFulfilled
            function ($response) use ($objectEntity, $url, $method) {
                if ($objectEntity->getEntity()->getGateway()->getLogging()) {
                }
                // Lets use the correct response type
                switch ($objectEntity->getEntity()->getGateway()->getType()) {
                    case 'json':
                        $result = json_decode($response->getBody()->getContents(), true);
                        break;
                    case 'xml':
                        $xmlEncoder = new XmlEncoder();
                        $result = $xmlEncoder->decode($response->getBody()->getContents(), 'xml');
                        break;
                    case 'soap':
                        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'soap:Envelope']);
                        $result = $response->getBody()->getContents();
                        // $result = $this->translationService->parse($result);
                        $result = $xmlEncoder->decode($result, 'xml');
                        $result = $this->translationService->dotHydrator([], $result, $objectEntity->getEntity()->getToSoap()->getResponseHydration());
                        break;
                    default:
                        // @todo throw error
                        break;
                }

                if (array_key_exists('id', $result) && !strpos($url, $result['id'])) {
                    $objectEntity->setUri($url.'/'.$result['id']);
                    $objectEntity->setExternalId($result['id']);

                    $item = $this->cache->getItem('commonground_'.base64_encode($url.'/'.$result['id']));
                } else {
                    $objectEntity->setUri($url);
                    $objectEntity->setExternalId($this->commonGroundService->getUuidFromUrl($url));
                    $item = $this->cache->getItem('commonground_'.base64_encode($url));
                }

                // Set organization for this object
                //                if (count($objectEntity->getSubresourceOf()) > 0 && !empty($objectEntity->getSubresourceOf()->first()->getObjectEntity()->getOrganization())) {
                //                    $objectEntity->setOrganization($objectEntity->getSubresourceOf()->first()->getObjectEntity()->getOrganization());
                //                    $objectEntity->setApplication($objectEntity->getSubresourceOf()->first()->getObjectEntity()->getApplication());
                //                } else {
                //                    $objectEntity->setOrganization($this->session->get('activeOrganization'));
                //                    $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
                //                    $objectEntity->setApplication(!empty($application) ? $application : null);
                //                }
                //                $objectEntity = $this->functionService->handleFunction($objectEntity, $objectEntity->getEntity()->getFunction(), [
                //                    'method' => $method,
                //                    'uri'    => $objectEntity->getUri(),
                //                ]);
                //                if (isset($setOrganization)) {
                //                    $objectEntity->setOrganization($setOrganization);
                //                }

                // Only show/use the available properties for the external response/result
                if (!is_null($objectEntity->getEntity()->getAvailableProperties())) {
                    $availableProperties = $objectEntity->getEntity()->getAvailableProperties();
                    $result = array_filter($result, function ($key) use ($availableProperties) {
                        return in_array($key, $availableProperties);
                    }, ARRAY_FILTER_USE_KEY);
                }
                $objectEntity->setExternalResult($result);

                $responseLog = new Response(json_encode($result), 201, []);
                // log hier
                $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 13, json_encode($result), null, 'out');

                // Notify notification component
                $this->notifications[] = [
                    'objectEntity' => $objectEntity,
                    'method'       => $method,
                ];

                // Lets stuff this into the cache for speed reasons
                $item->set($result);
                //$item->expiresAt(new \DateTime('tomorrow'));
                $this->cache->save($item);
            },
            // $onRejected
            function ($error) use ($objectEntity) {

                /* @todo lelijke code */
                if ($error->getResponse()) {
                    $errorBody = json_decode((string) $error->getResponse()->getBody(), true);
                    if ($errorBody && array_key_exists('message', $errorBody)) {
                        $error_message = $errorBody['message'];
                    } elseif ($errorBody && array_key_exists('hydra:description', $errorBody)) {
                        $error_message = $errorBody['hydra:description'];
                    } else {
                        $error_message = (string) $error->getResponse()->getBody();
                    }
                } else {
                    $error_message = $error->getMessage();
                }
                // log hier
                if ($error->getResponse() instanceof Response) {
                    $responseLog = $error->getResponse();
                } else {
                    $responseLog = new Response($error_message, $error->getResponse()->getStatusCode(), []);
                }
                $log = $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 14, $error_message, null, 'out');
                /* @todo eigenlijk willen we links naar error reports al losse property mee geven op de json error message */
                $objectEntity->addError('gateway endpoint on '.$objectEntity->getEntity()->getName().' said', $error_message.'. (see /admin/logs/'.$log->getId().') for a full error report');
            }
        );

        return $promise;
    }

    /**
     * Create a NRC notification for the given ObjectEntity.
     *
     * @param ObjectEntity $objectEntity
     * @param string       $method
     */
    public function notify(ObjectEntity $objectEntity, string $method)
    {
        if (!$this->commonGroundService->getComponent('nrc')) {
            return;
        }
        // TODO: move this function to a notificationService?
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
     * TODO: docs.
     *
     * @param $type
     * @param $id
     *
     * @return string
     */
    public function createUri(ObjectEntity $objectEntity): string
    {
        // We need to persist if this is a new ObjectEntity in order to set and getId to generate the uri...
        $this->em->persist($objectEntity);
        if ($objectEntity->getEntity()->getGateway() && $objectEntity->getEntity()->getGateway()->getLocation() && $objectEntity->getEntity()->getGateway() && $objectEntity->getExternalId()) {
            return $objectEntity->getEntity()->getGateway()->getLocation().'/'.$objectEntity->getEntity()->getEndpoint().'/'.$objectEntity->getExternalId();
        }

        $uri = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' ? 'https://'.$_SERVER['HTTP_HOST'] : 'http://localhost';

        if ($objectEntity->getEntity()->getRoute()) {
            return $uri.$objectEntity->getEntity()->getRoute().'/'.$objectEntity->getId();
        }

        return $uri.'/admin/object_entities/'.$objectEntity->getId();
    }

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

    /**
     * @return array
     */
    public function getDutchPC4List(): array
    {
        $file = fopen(dirname(__FILE__).'/csv/dutch_pc4.csv', 'r');

        $i = 0;
        $dutch_pc4_list = [];
        while (!feof($file)) {
            $line = fgetcsv($file);
            if ($i === 0) {
                $i++;
                continue;
            }
            if (isset($line[1])) {
                $dutch_pc4_list[] = $line[1];
            }
            $i++;
        }

        return $dutch_pc4_list;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public function validateDutchPC4(string $value): bool
    {
        $dutch_pc4_list = $this->getDutchPC4List();

        foreach ($dutch_pc4_list as $dutch_pc4) {
            if ($dutch_pc4 == $value) {
                return true;
            }
        }

        return false;
    }

    public function dutchPC4ToJson(): \Symfony\Component\HttpFoundation\Response
    {
        $dutch_pc4_list = $this->getDutchPC4List();

        $data = [
            'postalCodes' => $dutch_pc4_list,
        ];

        $json = json_encode($data);

        $response = new \Symfony\Component\HttpFoundation\Response(
            $json,
            200,
            ['content-type' => 'application/json']
        );

        return  $response;
    }
}
