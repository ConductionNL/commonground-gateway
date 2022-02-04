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
        // @todo if weer.monday is posted as 5 (int) it still is a string here....
        // todo: But if we do the same for person.givenName it is an integer here
        // dump(is_int($data['monday']));
        // $data['monday'] = 5;
        $validator = $this->getEntityValidator($entity);

        // TODO: what if we have fields in $data that do not exist on this Entity?

        try {
            $validator->assert($data);
        } catch (NestedValidationException $exception) {
            return $exception->getMessages();
        }
    }

    private function getEntityValidator(Entity $entity): Validator
    {
        // Get validator for this Entity from cache.
        $item = $this->cache->getItem('entityValidators_'.$entity->getId());
        if ($item->isHit()) {
//            return $item->get(); // TODO: put this back so we can use caching
        }

        // No Validator cached for this Entity, so create a new Validator and cache it.
        $validator = new Validator();
        $validator = $this->addAttributeValidators($entity, $validator);

        $item->set($validator);
        $item->tag('entityValidator');

        $this->cache->save($item);

        return $validator;
    }

    private function addAttributeValidators(Entity $entity, Validator $validator): Validator
    {
        foreach ($entity->getAttributes() as $attribute) {
            $attributeValidator = $this->getAttributeValidator($attribute);

            $validator->AddRule(new Rules\Key($attribute->getName(), $attributeValidator, array_key_exists('required', $attribute->getValidations()))); // mandatory = required
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

        // Add object (/subresource) validations
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
                    return new Rules\ArrayType(); // check multidimensional array
                }
                return new Rules\ArrayType();
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
                case 'nullable':
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
}
