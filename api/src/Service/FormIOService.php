<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service parses an Entity and its Attributes into a form.io JSON configuration used in front-ends.
 *
 * @Author Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class FormIOService
{
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
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
        $this->advConfComponent =
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
     * Creates disabled textfield component for ID's.
     *
     * @param string $id        The ID to set
     * @param string $preSetKey The pre set key that will be used as key for that input
     *
     * @return array Formio textfield component
     */
    private function createIDComponent(string $id, string $preSetKey = null): array
    {
        return [
            'key'           => $preSetKey ?? 'id',
            'defaultValue'  => $id,
            'label'         => 'ID',
            'disabled'      => true,
            'type'          => 'textfield',
        ];
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
     * @param Attribute $attr         The Attribute that will be parsed to a formio input
     * @param string    $preSetKey    The pre set key that will be used as key for that input
     * @param           $defaultValue Default value to give to current made input
     *
     * @return array Array/object of a formio input
     */
    private function createAttribute(Attribute $attr, string $preSetKey = null, $defaultValue = null, string $parentAttribute = null)
    {
        // $attr->getName() == 'openpubAudience' && var_dump($attr->getNullable());
        if ($attr->getType() == 'object' && $attr->getObject() !== null) {
            if ($parentAttribute && $this->checkIfWeAreLooping($attr, $parentAttribute)) {
                return null;
            }

            return $this->createEntityAsAttribute($attr, $preSetKey, $defaultValue);
        } else {
            return $this->createNormalAttribute($attr, $preSetKey, $defaultValue);
        }
    }

    private function checkIfWeAreLooping(Attribute $attr, string $parentAttribute)
    {
        $entity = $attr->getObject();
        foreach ($entity->getAttributes() as $childAttribute) {
            if ($childAttribute->getName() === $parentAttribute) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a panel (accordion) type formio input for a Object Attribute.
     *
     * @param Attribute $attr         The Attribute that will be parsed to a formio input
     * @param string    $preSetKey    The pre set key that will be used as key for that input
     * @param           $defaultValue Default value to give to current made input
     *
     * @return array Array/object of a formio input
     */
    private function createEntityAsAttribute(Attribute $attr, string $preSetKey = null, $defaultValue = null): array
    {
        $preSetKey = $this->extendPreSetKey($preSetKey, $attr->getName());

        // if ($preSetKey == 'taxonomies[openpubAudience]') {
        //     var_dump($preSetKey);
        //     // die;
        // }

        $createDataGrid = false;
        if ($attr->getMultiple()) {
            $createDataGrid = true;
        }

        if ($attr->getCascade() !== true) {
            return $this->createUriAttribute($attr, $preSetKey);
        }
        // $attr->getName() == 'openPubAudience' && var_dump($attr->getName());
        $object = $attr->getObject();

        $dataGridComponent = null;
        if ($createDataGrid) {
            $dataGridComponent = $this->createDataGrid($attr, $preSetKey);
        }

        $accordionComponent = [
            'title'         => $attr->getName(),
            'theme'         => 'default',
            'collapsible'   => true,
            'collapsed'     => true,
            'key'           => $createDataGrid ? null : (!empty($preSetKey) ? $preSetKey : $attr->getName()),
            'type'          => 'panel',
            'label'         => 'Panel',
            'breadcrumb'    => $attr->getName(),
            'labelPosition' => 'top',
            'validateOn'    => 'change',
            'components'    => [],
        ];

        if (isset($defaultValue['id'])) {
            $accordionComponent['components'][] = $this->createIDComponent($defaultValue['id'], $preSetKey.'[id]');
        }

        // Add attributes from this object as sub components
        foreach ($object->getAttributes() as $objectAttr) {
            !$objectAttr->getMultiple() && isset($defaultValue[0]) && $defaultValue = $defaultValue[0];
            isset($defaultValue[$objectAttr->getName()]) ? $defaultValueToPass = $defaultValue[$objectAttr->getName()] : $defaultValueToPass = null;
            if ($dataGridComponent) {
                $newComponent = $this->createAttribute($objectAttr, null, $defaultValueToPass, $attr->getName());
            } else {
                $newComponent = $this->createAttribute($objectAttr, $preSetKey, $defaultValueToPass, $attr->getName());
            }
            $newComponent && $dataGridComponent['components'][] = $newComponent;
        }

        // If this attribute is a array of subobjects return the datagrid
        if ($attr->getMultiple() && $attr->getType() === 'object') {
            return $dataGridComponent;
        }

        if ($dataGridComponent) {
            return $accordionComponent['components'][] = $dataGridComponent;
        }

        return $accordionComponent;
    }

    /**
     * Creates a datagrid (table) type formio input for a Object Attribute Multiple.
     *
     * @param Attribute $attr      The Attribute that will be parsed to a formio input
     * @param string    $preSetKey The pre set key that will be used as key for that input
     *
     * @return array Array/object of a formio input
     */
    private function createDataGrid(Attribute $attr, string $preSetKey = null, $defaultValue = null): array
    {
        return [
            'label'              => $attr->getName(),
            'addAnotherPosition' => 'bottom',
            'defaultValue'       => $defaultValue,
            'key'                => $preSetKey ?? $attr->getName(),
            'type'               => 'datagrid',
            'input'              => true,
            'persistent'         => true,
            'hidden'             => false,
            'clearOnHide'        => true,
            'labelPosition'      => 'top',
            'validateOn'         => 'change',
            'validate'           => [
                'required' => $attr->getRequired() ?? false,
            ],
            'tree' => true,
        ];
    }

    /**
     * Creates a textfield type formio input for a Object Attribute where cascade is false.
     *
     * @param Attribute $attr         The Attribute that will be parsed to a formio input
     * @param string    $preSetKey    The pre set key that will be used as key for that input
     * @param           $defaultValue Default value to give to current made input
     *
     * @return array Array/object of a formio input
     */
    private function createUriAttribute(Attribute $attr, string $preSetKey = null, $defaultValue = null): array
    {
        $component = $this->basicComponent;
        $component['label'] = $attr->getName().' (uri)';
        $component['key'] = $preSetKey ?? $attr->getName();
        $component['type'] = 'textfield';

        return $component;
    }

    /**
     * Creates a normal type formio input for an Attribute.
     *
     * @param Attribute $attr         The Attribute that will be parsed to a formio input
     * @param string    $preSetKey    The pre set key that will be used as key for that input
     * @param           $defaultValue Default value to give to current made input
     *
     * @return array Array/object of a formio input
     */
    private function createNormalAttribute(Attribute $attr, string $preSetKey = null, $defaultValue = null): array
    {
        $preSetKey = $this->extendPreSetKey($preSetKey, $attr->getName());

        $component = $this->basicComponent;
        $component['label'] = $attr->getName().($attr->getRequired() ? '*' : '');
        $component['key'] = $preSetKey ?? $attr->getName();
        $component['multiple'] = $attr->getMultiple();
        empty($defaultValue) && $defaultValue = null;

        $component['defaultValue'] = $attr->getDefaultValue() ?? $defaultValue;
        $component['placeholder'] = $attr->getExample() ?? '';
        $component['unique'] = $attr->getMustBeUnique() ?? '';
        $attr->getReadOnly() !== null && $component['disabled'] = true;
        $attr->getReadOnly() !== null && $attr->getReadOnly() == true && $component['label'] .= ' (read only)';

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
     * @param array  $object Object to fill form with
     *
     * @return array Array/object of the formio form
     */
    public function createFormIOArray(Entity $entity, array $object = null): array
    {
        isset($object['id']) && $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($object['id']);

        $formIOArray['components'] = [];
        $readOnlyArray = [];

        // If we have a id set it as disabled component
        isset($object['id']) &&
            $formIOArray['components'][] = $this->createIDComponent($object['id']);

        // All attributes as inputs
        foreach ($entity->getAttributes() as $attr) {
            isset($object[$attr->getName()]) ? $defaultValue = $object[$attr->getName()] : $defaultValue = null;
            $formIOArray['components'][] = $this->createAttribute($attr, null, $defaultValue);
            $attr->getReadOnly() && $readOnlyArray[] = $attr->getName();
        }

        // Add advanced configuration
        $formIOArray['components'][] = [
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
                    'label'         => 'Application',
                    'labelPosition' => 'top',
                    'widget'        => [
                        'type' => 'input',
                    ],
                    'validateOn'   => 'change',
                    'key'          => '@application',
                    'type'         => 'textfield',
                    'inputType'    => 'text',
                    'defaultValue' => isset($objectEntity) && $objectEntity->getApplication() ? '/admin/applications/'.$objectEntity->getApplication()->getId() : '',

                ],
                // [
                //     'label'         => 'Organization',
                //     'labelPosition' => 'top',
                //     'widget'        => [
                //         'type' => 'input',
                //     ],
                //     'validateOn'   => 'change',
                //     'key'          => '@organization',
                //     'type'         => 'textfield',
                //     'inputType'    => 'text',
                //     'defaultValue' => isset($objectEntity) ? $objectEntity->getOrganization() : '',

                // ],
                [
                    'label'         => 'Owner',
                    'labelPosition' => 'top',
                    'widget'        => [
                        'type' => 'input',
                    ],
                    'validateOn'   => 'change',
                    'key'          => '@owner',
                    'type'         => 'textfield',
                    'inputType'    => 'text',
                    'defaultValue' => isset($objectEntity) ? $objectEntity->getOwner() : '',

                ],
            ],
        ];

        // Add submit button
        $formIOArray['components'][] = $this->submitButtonComponent;

        $formIOArray['display'] = 'form';
        $formIOArray['page'] = 0;
        $formIOArray['entity'] = $entity->getName();

        $formIOArray['custom']['readOnly'] = $readOnlyArray;

        return $formIOArray;
    }
}
