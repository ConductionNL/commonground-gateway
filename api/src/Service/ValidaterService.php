<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Exception\GatewayException;
use App\Service\Validation\Rules as CustomRules;
use DateTime;
use Exception;
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
    private string $method;

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
     * @param string $method used to be able to use different validations for different methods.
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return string[]|void
     */
    public function validateData(array $data, Entity $entity, string $method)
    {
        // We could use a different function to set the $method, but this way we can only validate data if we also have a method.
        if (!in_array($method, ['POST', 'PUT', 'PATCH'])) {
            throw new GatewayException(
                'This validation method is not allowed.',
                null,
                null,
                [
                    'data'         => $method,
                    'path'         => $entity->getName(),
                    'responseType' => Response::HTTP_BAD_REQUEST,
                ]
            );
        }
        $this->method = $method;
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
        $item = $this->cache->getItem('entityValidators_'.$entity->getId()->toString().'_'.$this->method);
        if ($item->isHit()) {
//            return $item->get(); // TODO: put this back so that we use caching
        }

        // No Validator cached for this Entity, so create a new Validator and cache it.
        $validator = new Validator();
        $validator = $this->addAttributeValidators($entity, $validator);

        $item->set($validator);
        $item->tag('entityValidator'); // Tag for all Entity Validators
        $item->tag('entityValidator_'.$entity->getId()->toString()); // Tag for the Validators of this specific Entity.

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
            if (($this->method == 'PUT' || $this->method == 'PATCH') && $attribute->getValidations()['immutable']) {
                // If immutable this attribute should not be present when doing a PUT or PATCH.
                $validator->addRule(new Rules\Not(new Rules\Key($attribute->getName())));
                // Skip any other validations
                continue;
            }
            if ($this->method == 'POST' && $attribute->getValidations()['unsetable']) {
                // If unsetable this attribute should not be present when doing a POST.
                $validator->addRule(new Rules\Not(new Rules\Key($attribute->getName())));
                // Skip any other validations
                continue;
            }

            if ($attribute->getValidations()['requiredIf']) {
                // todo: this works but doesn't give a nice and clear error response why the rule is broken.
                $validator->addRule(
                    new Rules\When(
                        new CustomRules\JsonLogic($attribute->getValidations()['requiredIf']), // IF
                        new Rules\Key($attribute->getName()), // TRUE
                        new Rules\AlwaysValid() // FALSE
//                        new CustomRules\JsonLogic($attribute->getValidations()['requiredIf']) // FALSE
                    )
                );
            }
            if ($attribute->getValidations()['forbiddenIf']) {
                // todo: this works but doesn't give a nice and clear error response why the rule is broken.
                $validator->addRule(
                    new Rules\When(
                        new CustomRules\JsonLogic($attribute->getValidations()['forbiddenIf']), // IF
                        new Rules\Not(new Rules\Key($attribute->getName())), // TRUE
                        new Rules\AlwaysValid() // FALSE
//                        new CustomRules\JsonLogic($attribute->getValidations()['forbiddenIf']) // FALSE
                    )
                );
            }

            // todo: only validate the following rule if the requiredIf and forbiddenIf rules above do not return an exception.
            // todo: Use when rule or make a custom rule for this?
            $validator->AddRule(
                new Rules\Key(
                    $attribute->getName(),
                    $this->getAttributeValidator($attribute),
                    $attribute->getValidations()['required'] === true // mandatory = required
                )
            );
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
        $attributeRulesValidator = $this->getAttTypeValidator($attribute);

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
     * Gets a Validator for the type of the given Attribute. (And format and other validations if type validation is true).
     *
     * @param Attribute $attribute
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function getAttTypeValidator(Attribute $attribute): Validator
    {
        $attributeTypeValidator = new Validator();

        // Make sure we do not call functions like this twice when using Validations or Rules twice in the When Rule.
        $attTypeRule = $this->getAttTypeRule($attribute);

        // If attribute type is correct continue validation of attribute format
        $attributeTypeValidator->addRule(
            new Rules\When(
                $attTypeRule, // IF
                $this->getAttFormatValidator($attribute), // TRUE
                $attTypeRule // FALSE
            )
        );

        return $attributeTypeValidator;
    }

    /**
     * Gets a Validator for the format of the given Attribute. (And other validations if format validation is true).
     *
     * @param Attribute $attribute
     *
     * @throws ComponentException|GatewayException
     *
     * @return Validator
     */
    private function getAttFormatValidator(Attribute $attribute): Validator
    {
        $attributeFormatValidator = new Validator();

        // Make sure we do not call functions like this twice when using Validations or Rules twice in the When Rule.
        $attFormatRule = $this->getAttFormatRule($attribute);

        // If attribute format is correct continue validation of other validationRules
        $attributeFormatValidator->addRule(
            new Rules\When(
                $attFormatRule, // IF
                $this->getAttValidationRulesValidator($attribute), // TRUE
                $attFormatRule // FALSE
            )
        );

        return $attributeFormatValidator;
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
            case 'array':
                return new Rules\ArrayType();
            case 'boolean':
            case 'bool':
                return new Rules\BoolType();
            case 'file':
                return new CustomRules\Base64File();
            case 'object':
                return $this->getObjectValidator($attribute);
            default:
                throw new GatewayException(
                    'Unknown attribute type.',
                    null,
                    null,
                    [
                        'data'         => $attribute->getType(),
                        'path'         => $attribute->getEntity()->getName().'.'.$attribute->getName(),
                        'responseType' => Response::HTTP_BAD_REQUEST,
                    ]
                );
        }
    }

    /**
     * Gets a Validator for the object of the given Attribute with type = 'object'.
     *
     * @param Attribute $attribute
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function getObjectValidator(Attribute $attribute): Validator
    {
        $objectValidator = new Validator();

        // TODO: Make a custom rule for cascading so we can give custom exception messages back?
        // Validate for cascading
        if ($attribute->getValidations()['cascade'] === true) {
            // Array or Uuid
            $objectValidator->addRule(new Rules\OneOf(
                new Rules\ArrayType(),
                new Rules\Uuid()
            ));
            // If we are allowed to cascade and the input is an array, validate the input array for the Attribute->object Entity
            $objectValidator->addRule(
                new Rules\When(
                    new Rules\ArrayType(), // IF
                    $this->getEntityValidator($attribute->getObject()), // TRUE // TODO: max depth... ?
                    new Rules\AlwaysValid() // FALSE
                )
            );
        } else {
            // Uuid
            $objectValidator->addRule(new Rules\Uuid());
        }

        return $objectValidator;
    }

    /**
     * Gets the correct Rule for the format of the given Attribute. If attribute has no format this will return alwaysValid.
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
            case null:
                return new Rules\AlwaysValid();
            default:
                throw new GatewayException(
                    'Unknown attribute format.',
                    null,
                    null,
                    [
                        'data'         => $format,
                        'path'         => $attribute->getEntity()->getName().'.'.$attribute->getName(),
                        'responseType' => Response::HTTP_BAD_REQUEST,
                    ]
                );
        }
    }

    /**
     * Gets a Validator with the correct Rules for (almost) all the validations of the given Attribute.
     *
     * @param Attribute $attribute
     *
     * @throws ComponentException|GatewayException
     *
     * @return Validator
     */
    private function getAttValidationRulesValidator(Attribute $attribute): Validator
    {
        $validationRulesValidator = new Validator();

        foreach ($attribute->getValidations() as $validation => $config) {
            // if we have no config or validation config == false continue without adding a new Rule.
            // And $ignoredValidations here are not done through this getValidationRule function, but somewhere else!
            $ignoredValidations = ['required', 'nullable', 'multiple', 'uniqueItems', 'requiredIf', 'forbiddenIf', 'cascade', 'immutable', 'unsetable'];
            // todo: instead of this^ array we could also add these options to the getValidationRule function but return the AlwaysValid rule?
            if (empty($config) || in_array($validation, $ignoredValidations)) {
                continue;
            }
            $validationRulesValidator->AddRule($this->getValidationRule($attribute, $validation, $config));
        }

        return $validationRulesValidator;
    }

    /**
     * Gets the correct Rule for a specific validation of the given Attribute.
     *
     * @param Attribute $attribute
     * @param $validation
     * @param $config
     *
     * @throws ComponentException|GatewayException|Exception
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
                return new Rules\Length($validations['minItems'] ?? null, $validations['maxItems'] ?? null);
            case 'maxProperties':
            case 'minProperties':
                return new Rules\Length($validations['minProperties'] ?? null, $validations['maxProperties'] ?? null);
            case 'minDate':
                return new Rules\Min(new DateTime($config));
            case 'maxDate':
                return new Rules\Max(new DateTime($config));
            case 'maxFileSize':
            case 'minFileSize':
                return new Rules\Key(
                    'base64',
                    new CustomRules\Base64Size($validations['minFileSize'] ?? null, $validations['maxFileSize'] ?? null),
                    true
                );
            case 'fileTypes':
                return new Rules\Key(
                    'base64',
                    new CustomRules\Base64MimeTypes($config),
                    true
                );
            default:
                // we should never end up here
                if (is_array($config)) {
                    $config = http_build_query($config, '', ', ');
                }

                throw new GatewayException(
                    'Unknown validation.',
                    null,
                    null,
                    [
                        'data'         => $validation.' set to '.$config,
                        'path'         => $attribute->getEntity()->getName().'.'.$attribute->getName(),
                        'responseType' => Response::HTTP_BAD_REQUEST,
                    ]
                );
        }
    }
}
