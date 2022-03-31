<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;

/**
 * This service parses an Entity and its Attributes into a form.io JSON configuration used in front-ends.
 */
class FormIOService
{
    public function __construct()
    {
        $this->basicComponent = [
            'input'       => true,
            'tableView'   => true,
            'inputMask'   => '',
            'prefix'      => '',
            'suffix'      => '',
            'persistent'  => true,
            'autofocus'   => false,
            'hidden'      => false,
            'clearOnHide' => true,
            'spellCheck'  => false,
        ];
        $this->advConfComponent = [
            'title'         => 'Advanced configuration',
            'theme'         => 'default',
            'collapsible'   => true,
            'collapsed'     => true,
            'key'           => 'advancedConfiguration',
            'type'          => 'panel',
            'label'         => 'Advanced configuration',
            'breadcrumb'    => 'default',
            'labelPosition' => 'top',
            'validateOn'    => 'change',
            'components'    => [
                [
                    'label'         => 'Uri',
                    'labelPosition' => 'top',
                    'widget'        => [
                        'type' => 'input',
                    ],
                    'validateOn' => 'change',
                    'key'        => 'uri',
                    'type'       => 'textfield',
                    'inputType'  => 'text',
                ],
                [
                    'label'         => 'External ID',
                    'labelPosition' => 'top',
                    'widget'        => [
                        'type' => 'input',
                    ],
                    'validateOn' => 'change',
                    'key'        => 'externalId',
                    'type'       => 'textfield',
                    'inputType'  => 'text',

                ],
                [
                    'label'         => 'Application',
                    'labelPosition' => 'top',
                    'widget'        => [
                        'type' => 'input',
                    ],
                    'validateOn' => 'change',
                    'key'        => 'application',
                    'type'       => 'textfield',
                    'inputType'  => 'text',

                ],
                [
                    'label'         => 'Organization',
                    'labelPosition' => 'top',
                    'widget'        => [
                        'type' => 'input',
                    ],
                    'validateOn' => 'change',
                    'key'        => 'organization',
                    'type'       => 'textfield',
                    'inputType'  => 'text',

                ],
                [
                    'label'         => 'Owner',
                    'labelPosition' => 'top',
                    'widget'        => [
                        'type' => 'input',
                    ],
                    'validateOn' => 'change',
                    'key'        => 'owner',
                    'type'       => 'textfield',
                    'inputType'  => 'text',

                ],
            ],
        ];
        $this->submitButtonComponent = [
            'type'             => 'button',
            'theme'            => 'primary',
            'disableOnInvalid' => true,
            'action'           => 'submit',
            'rightIcon'        => '',
            'leftIcon'         => '',
            'size'             => 'md',
            'key'              => 'submit',
            'tableView'        => false,
            'label'            => 'Submit',
            'input'            => 'true',
        ];
    }

    /**
     * Checks input type for an Attribute.
     *
     * @param Attribute $attr The Attribute to check
     *
     * @return string Formio type of input
     */
    private function getAttributeInputType(Attribute $attr): string
    {
        // Default as textfield
        $type = 'textfield';

        switch ($attr->getType()) {
      case 'date':
      case 'datetime':
        $type = 'datetime';
        break;
      case 'integer':
      case 'float':
      case 'number':
        $type = 'number';
        break;
      case 'boolean':
        $type = 'checkbox';
        break;
      case 'file':
        $type = 'file';
        break;
    }

        switch ($attr->getFormat()) {
      case 'email':
        $type = 'email';
        break;
      case 'phone':
        $type = 'tel';
        break;
      case 'url':
        $type = 'url';
        break;
    }

        return $type;
    }

    /**
     * Extends pre set key used for the input name/key to cluster nested Attributes.
     *
     * @param ?string $preSetKey The key to extend
     * @param string  $attrName  The Attribute's name will be used for extending the key
     *
     * @return string Extended key
     */
    private function extendPreSetKey(?string $preSetKey = null, string $attrName): string
    {
        return $preSetKey ? $preSetKey = $preSetKey.'['.$attrName.']' : $preSetKey = $attrName;
    }

    /**
     * Checks if we have to create a Object as Attribute or a normal Attribute.
     *
     * @param Attribute $attr      The Attribute that will be parsed to a formio input
     * @param string    $preSetKey The pre set key that will be used as key for that input
     *
     * @return array Array/object of a formio input
     */
    private function createAttribute(Attribute $attr, string $preSetKey = null): array
    {
        if ($attr->getType() == 'object' && $attr->getObject() !== null) {
            return $this->createEntityAsAttribute($attr, $preSetKey);
        } else {
            return $this->createNormalAttribute($attr, $preSetKey);
        }
    }

    /**
     * Creates a panel (accordion) type formio input for a Object Attribute.
     *
     * @param Attribute $attr      The Attribute that will be parsed to a formio input
     * @param string    $preSetKey The pre set key that will be used as key for that input
     *
     * @return array Array/object of a formio input
     */
    private function createEntityAsAttribute(Attribute $attr, string $preSetKey = null): array
    {
        $preSetKey = $this->extendPreSetKey($preSetKey, $attr->getName());

        if ($attr->getCascade() !== true) {
            return $this->createUriAttribute($attr, $preSetKey);
        }

        $object = $attr->getObject();
        $accordionComponent = [
            'title'         => $attr->getName(),
            'theme'         => 'default',
            'collapsible'   => true,
            'collapsed'     => true,
            'key'           => !empty($preSetKey) ? $preSetKey : $attr->getName(),
            'type'          => 'panel',
            'label'         => 'Panel',
            'breadcrumb'    => $attr->getName(),
            'labelPosition' => 'top',
            'validateOn'    => 'change',
        ];

        $accordionComponent['components'] = [];
        foreach ($object->getAttributes() as $objectAttr) {
            $accordionComponent['components'][] = $this->createAttribute($objectAttr, $preSetKey);
        }

        return $accordionComponent;
    }

    /**
     * Creates a textfield type formio input for a Object Attribute where cascade is false.
     *
     * @param Attribute $attr      The Attribute that will be parsed to a formio input
     * @param string    $preSetKey The pre set key that will be used as key for that input
     *
     * @return array Array/object of a formio input
     */
    private function createUriAttribute(Attribute $attr, string $preSetKey = null): array
    {
        $component = $this->basicComponent;
        $component['label'] = $attr->getName();
        $component['key'] = $preSetKey ?? $attr->getName();
        $component['type'] = 'textfield';

        return $component;
    }

    /**
     * Creates a normal type formio input for an Attribute.
     *
     * @param Attribute $attr      The Attribute that will be parsed to a formio input
     * @param string    $preSetKey The pre set key that will be used as key for that input
     *
     * @return array Array/object of a formio input
     */
    private function createNormalAttribute(Attribute $attr, string $preSetKey = null): array
    {
        $preSetKey = $this->extendPreSetKey($preSetKey, $attr->getName());

        $component = $this->basicComponent;
        $component['label'] = $attr->getName();
        $component['key'] = $preSetKey ?? $attr->getName();
        $component['multiple'] = $attr->getMultiple();
        $component['defaultValue'] = $attr->getDefaultValue() ?? '';
        $component['placeholder'] = $attr->getExample() ?? '';
        $component['unique'] = $attr->getMustBeUnique() ?? '';
        $attr->getReadOnly() !== null && $component['disabled'] = true;
        $attr->getReadOnly() !== null && $attr->getReadOnly() == true && $component['label'].+' (read only)';

        $component['validate'] = [
            'required'      => $attr->getRequired() ?? false,
            'minLength'     => $attr->getMinLength() ?? '',
            'maxLength'     => $attr->getMaxLength() ?? '',
            'pattern'       => '',
            'custom'        => '',
            'customPrivate' => false,
        ];

        // Default required to false when readOnly is true
        $attr->getReadOnly() !== null && $attr->getReadOnly() == true && $component['validate']['required'] = false;

        $component['type'] = $this->getAttributeInputType($attr);

        return $component;
    }

    /**
     * Creates the standard data needed in a formio configuration array/object.
     *
     * @param Entity $entity The Entity that will be parsed to a formio form
     *
     * @return array Array/object of the formio form
     */
    public function createFormIOArray(Entity $entity): array
    {
        $formIOArray['components'] = [];
        // All attributes as inputs
        foreach ($entity->getAttributes() as $attr) {
            $formIOArray['components'][] = $this->createAttribute($attr);
        }

        $formIOArray['components'][] = $this->advConfComponent;
        $formIOArray['components'][] = $this->submitButtonComponent;

        $formIOArray['display'] = 'form';
        $formIOArray['page'] = 0;
        $formIOArray['entity'] = $entity->getName();

        return $formIOArray;
    }
}
