<?php

namespace App\Service;

use App\Entity\Entity;

class FormIOService
{
    /**
     * This function creates a form.io array for rendering forms in front-ends from a entity and its attributes.
     */
    public function createFormIOArray(Entity $entity): array
    {
        $formIOArray['components'] = [];

        // Basic values for a input
        $basicComponent = [
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

        // All attributes as inputs
        foreach ($entity->getAttributes() as $attr) {
            $component = $basicComponent;

            $component['label'] = $attr->getName();
            $component['key'] = $attr->getName();
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

            // Default required to false when readoOnly is true
            $attr->getReadOnly() !== null && $attr->getReadOnly() == true && $component['validate']['required'] = false;

            $component['type'] = $this->getAttributeInputType($attr);

            $formIOArray['components'][] = $component;
        }

        // Submit button
        $formIOArray['components'][] = [
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

        $formIOArray['display'] = 'form';
        $formIOArray['page'] = 0;
        $formIOArray['entity'] = $entity->getName();

        // Advanced configuration
        $formIOArray['components'][] = [
            'title'         => 'Advanced configuration',
            'theme'         => 'default',
            'collapsible'   => true,
            'key'           => 'advancedConfiguration',
            'type'          => 'panel',
            'label'         => 'Panel',
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

        return $formIOArray;
    }

    private function getAttributeInputType($attr): string
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
}
