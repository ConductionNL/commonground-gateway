<?php

namespace App\Twig;

use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class DefaultsRuntime implements RuntimeExtensionInterface
{

    private ObjectEntityService $objectEntityService;

    public function __construct(ObjectEntityService $objectEntityService)
    {
        $this->objectEntityService = $objectEntityService;
    }
    public function selfUrl(ObjectEntity $object): string
    {
        $value = $object->getUri();

        return $value;
    }
}
