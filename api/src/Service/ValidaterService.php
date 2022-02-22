<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Exception\GatewayException;
use App\Service\Validation\Rules as CustomRules;
use DateTime;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Factory;
use Respect\Validation\Rules;
use Respect\Validation\Validator;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Response;

class ValidaterService
{
    public CacheInterface $cache;

    public function __construct(
        CacheInterface $cache
    ) {
        $this->cache = $cache;
        Factory::setDefaultInstance(
            (new Factory())
            ->withRuleNamespace('App\Service\Validation\Rules')
            ->withExceptionNamespace('App\Service\Validation\Exceptions')
        );
    }

    /**
     * Validates an array with data using the Validator for the given Entity.
     *
     * @param array  $data
     * @param Entity $entity
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return string[]|void
     */
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

    /**
     * Gets a Validator for the given Entity, uses caching.
     *
     * @param Entity $entity
     *
     * @throws CacheException
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
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

    /**
     * Adds Attribute Validators to an Entity Validator.
     *
     * @param Entity    $entity
     * @param Validator $validator
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function addAttributeValidators(Entity $entity, Validator $validator): Validator
    {
        foreach ($entity->getAttributes() as $attribute) {
            if ($attribute->getValidations()['requiredIf']) {
                // todo: this works but doesn't give a nice error response when the rule is broken...
                // When works like this: When(IF, TRUE, FALSE)
                $validator->addRule(new Rules\When(new CustomRules\JsonLogic($attribute->getValidations()['requiredIf']), new Rules\Key($attribute->getName()), new Rules\AlwaysValid()));
            }
            if ($attribute->getValidations()['forbiddenIf']) {
                // todo: this works but doesn't give a nice error response when the rule is broken...
                // When works like this: When(IF, TRUE, FALSE)
                $validator->addRule(new Rules\When(new CustomRules\JsonLogic($attribute->getValidations()['forbiddenIf']), new Rules\Not(new Rules\Key($attribute->getName())), new Rules\AlwaysValid()));
            }
            $validator->AddRule(new Rules\Key($attribute->getName(), $this->getAttributeValidator($attribute), $attribute->getValidations()['required'] === true)); // mandatory = required
        }

        return $validator;
    }

    /**
     * Gets a Validator for the given Attribute.
     *
     * @param Attribute $attribute
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function getAttributeValidator(Attribute $attribute): Validator
    {
        $attributeValidator = new Validator();

        return $attributeValidator->addRule($this->checkIfAttNullable($attribute));
    }

    /**
     * Checks if the attribute is nullable and adds the correct Rules for this if needed.
     *
     * @param Attribute $attribute
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Rules\AbstractRule
     */
    private function checkIfAttNullable(Attribute $attribute): Rules\AbstractRule
    {
        // Check if this attribute can be null
        if ($attribute->getValidations()['nullable'] === true) {
            // When works like this: When(IF, TRUE, FALSE)
            return new Rules\When(new Rules\NotEmpty(), $this->checkIfAttMultiple($attribute), new Rules\AlwaysValid());
        }

        return $this->checkIfAttMultiple($attribute);
    }

    /**
     * Checks if the attribute is an array (multiple) and adds the correct Rules for this if needed.
     *
     * @param Attribute $attribute
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function checkIfAttMultiple(Attribute $attribute): Validator
    {
        // Get all validations for this attribute
        $attributeRulesValidator = $this->getAttributeRules($attribute);

        // Check if this attribute is an array
        if ($attribute->getValidations()['multiple'] === true) {
            // TODO: When we get a validation error we somehow need to get the index of that object in the array for in the error data...

            $multipleValidator = new Validator();
            $multipleValidator->addRule(new Rules\Each($attributeRulesValidator));
            if ($attribute->getValidations()['uniqueItems'] === true) {
                $multipleValidator->addRule(new Rules\Unique());
            }

            return $multipleValidator;
        }

        return $attributeRulesValidator;
    }

    /**
     * Gets all (other) validation, format and type Rules for the given Attribute.
     *
     * @param Attribute $attribute
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function getAttributeRules(Attribute $attribute): Validator
    {
        // note: When multiple rules are broken and somehow only one error is returned for one of the two rules, only the last added rule will be shown in the error message.
        // ^this is why the rules in this function are added in the current order. Subresources->Validations->Format->Type
        $attributeRulesValidator = new Validator();

        // Add rules for validations
        $attributeRulesValidator = $this->addValidationRules($attribute, $attributeRulesValidator);

        // Add rule for format, but only if input is not empty.
        $attribute->getFormat() !== null && $attributeRulesValidator->AddRule($this->getAttFormatRule($attribute));

        // Add rule for type, but only if input is not empty.
        $attribute->getType() !== null && $attributeRulesValidator->AddRule($this->getAttTypeRule($attribute));

        return $attributeRulesValidator;
    }

    /**
     * Gets the correct Rule(s) for the type of the given Attribute.
     *
     * @param Attribute $attribute
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Rules\AbstractRule
     */
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
                return new Rules\KeySet(
                    new Rules\Key('filename', new CustomRules\Filename(), false),
                    new Rules\Key('base64', $this->getBase64Validator(), true)
                );
            case 'object':
                // TODO: move this to a getObjectValidator function?
                $objectValidator = new Validator();
                $objectValidator->addRule(new Rules\ArrayType());

                // Add object (/subresource) validations
                $subresourceValidator = $this->getEntityValidator($attribute->getObject()); // TODO: max depth... ?
                $objectValidator->AddRule($subresourceValidator);

                return $objectValidator;
            default:
                throw new GatewayException('Unknown attribute type!', null, null, ['data' => $attribute->getType(), 'path' => $attribute->getEntity()->getName().'.'.$attribute->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }
    }

    // todo: make this into a customRule
    /**
     * Gets a Validator with rules used for validating a base64 string.
     *
     * @return Validator
     */
    private function getBase64Validator(): Validator
    {
        $base64Validator = new Validator();

        // todo: EXAMPLE: data:text/plain;base64,ZGl0IGlzIGVlbiB0ZXN0IGRvY3VtZW50
        $base64Validator->addRule(new Rules\StringType());
        $base64Validator->addRule(new Rules\Base64()); // this only validates: ZGl0IGlzIGVlbiB0ZXN0IGRvY3VtZW50 of above EXAMPLE
        // todo: in this function we should validate if the base64 string has the correct structure (as shown in EXAMPLE above^)
        // todo: for validation of allowed mime types and file size we should make customRules and use those in the $this->getValidationRule() function
//        new Rules\Mimetype();

        return $base64Validator;
    }

    /**
     * Gets the correct Rule for the format of the given Attribute.
     *
     * @param Attribute $attribute
     *
     * @throws GatewayException
     *
     * @return Rules\AbstractRule
     */
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
                return new CustomRules\DutchPostalcode();
            default:
                throw new GatewayException('Unknown attribute format!', null, null, ['data' => $format, 'path' => $attribute->getEntity()->getName().'.'.$attribute->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }
    }

    /**
     * Adds the correct Rules for (almost) all the validations of the given Attribute.
     *
     * @param Attribute $attribute
     * @param Validator $attributeRulesValidator
     *
     * @throws GatewayException|ComponentException
     *
     * @return Validator
     */
    private function addValidationRules(Attribute $attribute, Validator $attributeRulesValidator): Validator
    {
        foreach ($attribute->getValidations() as $validation => $config) {
            // if we have no config or validation config == false continue without adding a new Rule.
            // And validations in_array here are not done through this addValidationRule function, but somewhere else!
            if (empty($config) || in_array($validation, ['required', 'nullable', 'multiple', 'uniqueItems', 'requiredIf', 'forbiddenIf'])) {
                continue;
            }
            $attributeRulesValidator->AddRule($this->getValidationRule($attribute, $validation, $config));
        }

        return $attributeRulesValidator;
    }

    /**
     * Gets the correct Rule for a specific validation of the given Attribute.
     *
     * @param Attribute $attribute
     * @param $validation
     * @param $config
     *
     * @throws ComponentException|GatewayException
     *
     * @return Rules\AbstractRule|null
     */
    private function getValidationRule(Attribute $attribute, $validation, $config): ?Rules\AbstractRule
    {
        $validations = $attribute->getValidations();
        switch ($validation) {
            case 'enum':
                return new Rules\In($config);
            case 'multipleOf':
                return new Rules\Multiple($config);
            case 'maximum':
                return new Rules\Max($config);
            case 'exclusiveMaximum':
                return new Rules\LessThan($validations['maximum']);
            case 'minimum':
                return new Rules\Min($config);
            case 'exclusiveMinimum':
                return new Rules\GreaterThan($validations['minimum']);
            case 'minLength':
            case 'maxLength':
                return new Rules\Length($validations['minLength'] ?? null, $validations['maxLength'] ?? null);
            case 'maxItems':
            case 'minItems':
                return new Rules\Length($validations['minItems'] ?? null, $validations['maxItems'] ?? null); // todo: merge this with min/maxlength?
            case 'maxProperties':
            case 'minProperties':
                return new Rules\Length($validations['minProperties'] ?? null, $validations['maxProperties'] ?? null); // todo: merge this with min/maxlength?
            case 'minDate':
                return new Rules\Min(new DateTime($config));
            case 'maxDate':
                return new Rules\Max(new DateTime($config));
            case 'maxFileSize':
            case 'minFileSize':
                // base64 Key is mandatory, but this shouldn't be checked here, see: $this->getAttTypeRule(), let's prevent double error messages...
                return new Rules\Key('base64', new CustomRules\Base64Size($validations['minFileSize'] ?? null, $validations['maxFileSize'] ?? null), false);
            case 'fileTypes':
                // todo: see: $this->getAttTypeRule() & $this->getBase64Validator()
                // todo: here we should use new customRules in combination with the Key rule to get the base64 and mimeType from {"filename": "something.txt", "base64": "data:text/plain;base64,ZGl0IGlzIGVlbiB0ZXN0IGRvY3VtZW50"}
                break;
            default:
                // we should never end up here
                if (is_array($config)) {
                    $config = http_build_query($config, '', ', ');
                }

                throw new GatewayException('Unknown validation!', null, null, ['data' => $validation.' set to '.$config, 'path' => $attribute->getEntity()->getName().'.'.$attribute->getName(), 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        return null;
    }
}
