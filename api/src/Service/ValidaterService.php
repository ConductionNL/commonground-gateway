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
use Sabberworm\CSS\Rule\Rule;
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

            $validator->AddRule(new Rules\Key($attribute->getName(), $attributeValidator, $attribute->getValidations()['required'] === true)); // mandatory = required
        }

        return $validator;
    }

    private function getAttributeValidator(Attribute $attribute): Validator
    {
        // note: When multiple rules are broken and somehow only one error is returned for one of the two rules, only the last added rule will be shown in the error message.
        // ^this is why the rules in this function are added in the current order. Subresources->Validations->Format->Type
        $attributeValidator = new Validator();

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

        // Add rules for validations
        $attribute->getValidations() !== null && $attributeValidator = $this->addValidationRules($attribute, $attributeValidator);

        // Add rule for format, but only if input is not empty.
        $attribute->getFormat() !== null && $attributeValidator->AddRule(new Rules\When(new Rules\NotEmpty(), $this->getAttFormatRule($attribute), new Rules\AlwaysValid()));

        // Add rule for type, but only if input is not empty.
        $attribute->getType() !== null && $attributeValidator->AddRule(new Rules\When(new Rules\NotEmpty(), $this->getAttTypeRule($attribute), new Rules\AlwaysValid()));

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
            case 'date':
                return new Rules\Date();
            case 'datetime':
                return new Rules\DateTime();
            case 'file':
                return new Rules\File(); // todo: this is probably incorrect
            case 'object':
                if ($attribute->getMultiple()) {
                    // Make sure this is an array of objects, multidimensional array
                    return new Rules\Each(new Rules\ArrayType());
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
        foreach ($attribute->getValidations() as $validation => $config) {
            // If attribute can not be null add NotEmpty rule
            if ($attribute->getValidations()['nullable'] !== true) { //todo: something about defaultValue
                $attributeValidator->AddRule(new Rules\NotEmpty());
            } else {
                // if we have no config or validation config == false continue without adding a new Rule.
                // And validation required is not done through the addValidationRule function!
                if (empty($config) || $validation == 'required' || $validation == 'nullable') {
                    continue;
                }
//                var_dump($attribute->getName());
//                var_dump($validation);
                // Only apply the rule if input is notEmpty, else skip rule (AlwaysValid).
                $attributeValidator->AddRule(new Rules\When(new Rules\NotEmpty(), $this->addValidationRule($attribute, $validation, $config), new Rules\AlwaysValid()));
            }
        }

        return $attributeValidator;
    }

    private function addValidationRule(Attribute $attribute, $validation, $config): ?Rules\AbstractRule
    {
        switch ($validation) {
            case 'enum':
                return new Rules\In($config);
            case 'multipleOf':
                return new Rules\Multiple($config);
            case 'maximum':
                return new Rules\Max($config);
            case 'exclusiveMaximum':
                return new Rules\LessThan($config);
            case 'minimum':
                return new Rules\Min($config);
            case 'exclusiveMinimum':
                return new Rules\GreaterThan($config);
            case 'minLength':
            case 'maxLength':
                return new Rules\Length($validations['minLength'] ?? null, $validations['maxLength'] ?? null);
            case 'maxItems':
            case 'minItems':
                return new Rules\Length($validations['minItems'] ?? null, $validations['maxItems'] ?? null);
            case 'uniqueItems':
                return new Rules\Unique();
            case 'maxProperties':
            case 'minProperties':
                return new Rules\Length($validations['minProperties'] ?? null, $validations['maxProperties'] ?? null);
            case 'minDate':
                return new Rules\Min(new DateTime($config));
            case 'maxDate':
                return new Rules\Max(new DateTime($config));
            case 'maxFileSize':
            case 'fileType':
                // @TODO
                break;
            case 'forbidden':
                return new Rules\Not(Validator::notEmpty());
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
                throw new GatewayException('Unknown validation!', null, null, ['data' => $validation.' set to '.$config, 'path' => $attribute->getEntity()->getName().'.'.$attribute->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        return new Rules\NotEmpty();
    }
}
