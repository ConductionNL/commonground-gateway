<?php

namespace App\Service;

use App\Entity\Entity;
use Attribute;

class FormIOService
{


    public function __construct(
    ) {
    }

    /**
     * This function creates a form.io array for rendering forms in front-ends from a entity and its attributes.
     */
    public function createFormIOArray(Entity $entity): array
    {
      $formIOArray['components'] = [];

      $basicComponent = [
        'input' => true,
        'tableView' => true,
        'inputMask' => '',
        'prefix' => '',
        'suffix' => '',
      ];

      foreach ($entity->getAttributes() as $attr) {
        $component = $basicComponent;

        $component['label'] = $attr->getName();
        $component['key'] = $attr->getName();
        $component['multiple'] = $attr->getMultiple();
        $component['defaultValue'] = $attr->getDefaultValue() ?? '';
        $component['placeholder'] = $attr->getExample() ?? '';

        $component['validate'] = [
          'required' => $attr->getRequired() ?? false,
          'minLength' => $attr->getMinLength() ?? '',
          'maxLength' => $attr->getMaxLength() ?? '',
          'pattern' => '',
          'custom' => '',
          'customPrivate' => false
        ];

        $component['type'] = $this->getAttributeInputType($attr);

        $formIOArray['components'][] = $component;
      }

      

      return $formIOArray;
    }

    private function getAttributeInputType($attr): string
    {
      $type = 'text';

      switch ($attr->getType()) {
        case 'string': 
        case 'date': 
        case 'date-time':
          $type = 'text';
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
