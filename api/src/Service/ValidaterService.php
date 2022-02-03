<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Exception\GatewayException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Rules;
use Respect\Validation\Validator;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Response;

class ValidaterService
{
    public CacheInterface $cache;

    public function __construct(
        CacheInterface $cache,
        EntityManagerInterface $entityManager
    ) {
        $this->cache = $cache;
        $this->entityManager = $entityManager;
    }

    public function validateData(array $data, Entity $entity)
    {
        // @todo if monday is posted as 5 (int) it still is a string here....
        // dump(is_int($data['monday']));
        // $data['monday'] = 5;
        $validator = $this->getEntityValidator($entity);

        try {
            $validator->assert($data);
        } catch (NestedValidationException $exception) {
            return $exception->getMessages();
        }
    }

    private function getEntityValidator(Entity $entity): Validator
    {
        // Get validator for this Entity from cache.
        $item = $this->cache->getItem('entities_'.md5($entity->getName()));
        if ($item->isHit()) {
//            return $item->get(); // TODO: put this back so we can use caching
        }

        // No Validator cached for this Entity, so create a new Validator and cache it.
        $validator = new Validator();
        $validator = $this->addAttributeValidators($entity, $validator);

        $item->set($validator);
        $item->tag('entity');

        $this->cache->save($item);

        return $validator;
    }

    private function addAttributeValidators(Entity $entity, Validator $validator): Validator
    {
        foreach ($entity->getAttributes() as $attribute) {
            $attributeValidator = $this->getAttributeValidator($attribute);

            $validator->AddRule(new Rules\Key($attribute->getName(), $attributeValidator, false)); // mandatory = required
        }

        return $validator;
    }

    private function getAttributeValidator(Attribute $attribute): Validator
    {
        $attributeValidator = new Validator();

        // Add rule for type
        $attribute->getType() !== null && $attributeValidator->AddRule($this->getAttTypeRule($attribute));

        // Add rule for format
        $attribute->getFormat() !== null && $attributeValidator->AddRule($this->getAttFormatRule($attribute));

        // Add rules for validations
        // TODO:
        // $attribute->getValidations !== null && $attributeValidator = $this->addValidationRules($attribute, $attributeValidator);

        if ($attribute->getType() == 'object') {
            $subresourceValidator = $this->getEntityValidator($attribute->getObject()); // TODO: max depth...
            if ($attribute->getMultiple()) {
                $attributeValidator->addRule(new Rules\Each($subresourceValidator));
                // TODO: When we get a validation error we somehow need to get the index of that object in the array for in the error data...
            } else {
                $attributeValidator->AddRule($subresourceValidator);
            }
        }

        return $attributeValidator;
    }

    private function getAttTypeRule(Attribute $attribute): Rules\AbstractRule
    {
        switch ($attribute->getType()) {
            case 'string':
            case 'text':
                return new Rules\StringType();
            case 'integer':
            case 'int':
                return new Rules\IntType();
            case 'float':
                return new Rules\FloatType();
            case 'number':
                return new Rules\Number();
            case 'datetime':
                return new Rules\DateTime();
            case 'file':
                return new Rules\File();
            case 'object':
                if ($attribute->getMultiple()) {
                    // TODO: make sure this is an array of objects
                    return new Rules\ArrayType();
                }
                return new Rules\ArrayType();
//                return new Rules\ObjectType(); // ObjectType expect an actual class object not a json object, so this will not work...
            default:
                throw new GatewayException('Unknown attribute type!', null, null, ['data' => $attribute->getType(), 'path' => $attribute->getEntity()->getName().'.'.$attribute->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }
    }

    private function getAttFormatRule(Attribute $attribute): Rules\AbstractRule
    {
        $format = $attribute->getFormat();

        // Let be a bit compassionate and compatible
        $format = str_replace(['telephone'], ['phone'], $format);

        switch ($format) {
            case 'countryCode':
                return new Rules\CountryCode();
            case 'bsn':
                return new Rules\Bsn();
            case 'url':
                return new Rules\Url();
            case 'uuid':
                return new Rules\Uuid();
            case 'email':
                return new Rules\Email();
            case 'phone':
                return new Rules\Phone();
            case 'json':
                return new Rules\Json();
            case 'dutch_pc4':
                // TODO
            default:
                throw new GatewayException('Unknown attribute format!', null, null, ['data' => $format, 'path' => $attribute->getEntity()->getName().'.'.$attribute->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }
    }

    private function addValidationRules(Attribute $attribute, Validator $attributeValidator): Validator
    {
        // todo; testen en uitbreiden
        $validations = $attribute->getValidations();
        foreach ($validations as $validation => $config) {
            switch ($validation) {
                case 'multipleOf':
                    $attributeValidator->AddRule(new Rules\Multiple($config));
                case 'maximum':
                case 'exclusiveMaximum': // doet niks
                case 'minimum':
                case 'exclusiveMinimum': // doet niks
                    $attributeValidator->AddRule(new Rules\Between($validations['minimum'] ?? null, $validations['maximum'] ?? null));
                    break;
                case 'minLength':
                case 'maxLength':
                    $attributeValidator->AddRule(new Rules\Length($validations['minLength'] ?? null, $validations['maxLength'] ?? null));
                    break;
                case 'maxItems':
                case 'minItems':
                    $attributeValidator->AddRule(new Rules\Length($validations['minItems'] ?? null, $validations['maxItems'] ?? null));
                    break;
                case 'uniqueItems':
                    $attributeValidator->AddRule(new Rules\Unique());
                case 'maxProperties':
                case 'minProperties':
                    $attributeValidator->AddRule(new Rules\Length($validations['minProperties'] ?? null, $validations['maxProperties'] ?? null));
                case 'minDate':
                case 'maxDate':
                    $attributeValidator->AddRule(new Rules\Length(new DateTime($validations['minDate'] ?? null) ?? null, new DateTime($validations['maxDate'] ?? null) ?? null));
                    break;
                case 'maxFileSize':
                case 'fileType':
                    // @TODO
                    break;
                case 'required':
                    $attributeValidator->AddRule(new Rules\Not(Validator::notEmpty()));
                    break;
                case 'forbidden':
                    $attributeValidator->AddRule(new Rules\Not(Validator::notEmpty()));
                    break;
                // case 'conditionals':
                //     /// here we go
                //     foreach ($config as $con) {
                //         // Lets check if the referenced value is present
                //         /* @tdo this isnt array proof */
                //         if ($conValue = $objectEntity->getValueByName($con['property'])->value) {
                //             switch ($con['condition']) {
                //                 case '==':
                //                     if ($conValue == $con['value']) {
                //                         $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                //                     }
                //                     break;
                //                 case '!=':
                //                     if ($conValue != $con['value']) {
                //                         $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                //                     }
                //                     break;
                //                 case '<=':
                //                     if ($conValue <= $con['value']) {
                //                         $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                //                     }
                //                     break;
                //                 case '>=':
                //                     if ($conValue >= $con['value']) {
                //                         $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                //                     }
                //                     break;
                //                 case '>':
                //                     if ($conValue > $con['value']) {
                //                         $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                //                     }
                //                     break;
                //                 case '<':
                //                     if ($conValue < $con['value']) {
                //                         $validator = $this->validateValue($objectEntity, $value, $con['validations'], $validator);
                //                     }
                //                     break;
                //             }
                //         }
                //     }
                //     break;
                default:
                    // we should never end up here
                    //$objectEntity->addError($attribute->getName(),'Has an an unknown validation: [' . (string) $validation . '] set to'. (string) $config);
            }
        }

        return $attributeValidator;
    }


    // Example code:
    // TODO: translate / move this code below to functions above^
    // TODO: remove functions below after... >>>

    private function createEntityValidator(array $data, Entity $entity, string $method, Validator $validator)
    {
        // Lets validate each attribute
        foreach ($entity->getAttributes() as $attribute) {
            // fallback for empty data
            !array_key_exists($attribute->getName(), $data) && $data[$attribute->getName()] = null;

            $validator->key($attribute->getName(), $this->createAttributeEntityValidator($data, $attribute, $method, $validator));
            //
            // Lets clean it up
            unset($data[$attribute->getName()]);
        }

        // Lets see if we have attributes that should not be here (if we haven’t cleaned it up it isn’t an attribute)
        foreach ($data as $key => $value) {
            $validator->key(
                $key,
            /** custom not allowed validator*/
            );
        }

        return $validator;
    }

    private function createAttributeEntityValidator(array $data, Attribute $attribute, string $method, Validator $validator)
    {
        // if this is an entity we can skip al this
        if ($attribute->getType() === 'object' || $attribute->getType() === null) {
            // @todo maybe error?
            return $validator;
        }

        // Validate type
        // kijk naar de huidige validations service on validateType()

        // Let be a bit compasionate and compatable
        $type = str_replace(['integer', 'boolean', 'text'], ['int', 'bool', 'string'], $attribute->getType());
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
                    $validator->numericVal();
                    break;
                case 'object':
                    // We dont validate an object normaly but hand it over to its own validator
                    $this->validate($data[$attribute->getName()]);
                    break;
                default:
                    // we should never end up here
                    /* @todo throw an custom error */
            }
        }

        // Validate format
        // kijk naar de huidige validations service on validateType()

        // Besides the type and format there could be other validations (like minimal datetime, requered etc)
        foreach ($attribute->getValidations() as $key => $value) {
            switch ($key) {
                    // first we need to do of casses (anything not natively supported by validator or requiring additional logic)
                case 'jsonlogic':
                    // code to be executed if n=label1;
                    break;
                case 'postalcode':
                    // code to be executed if n=label2;
                    break;
                case 'label3':
                    // code to be executed if n=label3;
                    break;
                    // what is then left is the generic stuff
                default:
                    // we should not end up here…
                    // @todo throw error
            }
        }

        return $validator;
    }
}
