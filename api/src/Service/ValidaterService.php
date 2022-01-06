<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use App\Entity\Attribute;
use Respect\Validation\Validator;

class ValidationService
{

    public function __construct(
        EntityManagerInterface $entityManager,
    ) {
        $this->entityManager = $entityManager;
    }

    public function validateData(array $data, Entity $entity, string $method)
    {
        $validatorData = $data;
        $validator = new Validator;
        $validator = $this->createEntityValidator($validatorData, $entity, $method, $validator);

        return $validator->validate($data);
    }

    private function createEntityValidator(array $data, Entity $entity, string $method, Validator $validator)
    {
        // Lets validate each attribute
        foreach ($entity->getAttributes() as $attribute) {
            // fallback for empty data
            if (!array_key_exists($attribute->getName())) {
                $data[$attribute->getName()] = null;
            }
            $validator->key($attribute->getName(), $this->createAttributeEntityValidator($data, $entity, $method, $validator));

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
