<?php

namespace App\Twig;

use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;
use Twig\Extension\RuntimeExtensionInterface;

class DefaultsRuntime implements RuntimeExtensionInterface
{
    public function selfUrl(ObjectEntity $object): string
    {
        $value = $object->getUri();

        return $value;
    }
}
