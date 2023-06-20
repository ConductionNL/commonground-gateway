<?php

namespace App\Twig;

use Adbar\Dot;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class MappingRuntime implements RuntimeExtensionInterface
{
    private MappingService $mappingService;
    private EntityManagerInterface $entityManager;

    public function __construct(MappingService $mappingService, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->mappingService = $mappingService;
    }

    public function map(string $mappingString, array $data, bool $list = false): array
    {
        $mapping = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $mappingString]);

        $value = $this->mappingService->mapping($mapping, $data, $list);

        return $value;
    }

    public function dotToArray(array $array): array
    {
        $dotArray = new Dot($array, true);

        return $dotArray->all();
    }
}
