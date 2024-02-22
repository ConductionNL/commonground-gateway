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

    /**
     * Uses CoreBundle MappingService to map data.
     * If $list is set to true you could use the key 'listInput' in the $data array to pass along the list of items to map.
     * This makes it possible to also pass along other key+value pairs to use in mapping besides just one array of items to map.
     *
     * @param string $mappingString The reference of a Mapping object.
     * @param array $data The data to map. Or one list of items to map.
     * @param bool $list False by default, if set to true mapping will be done for each item in the $data array.
     *
     * @return array The mapped result.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\SyntaxError
     */
    public function map(string $mappingString, array $data, bool $list = false): array
    {
        $mapping = $this->entityManager->getRepository(Mapping::class)->findOneBy(['reference' => $mappingString]);

        $value = $this->mappingService->mapping($mapping, $data, $list);

        return $value;
    }

    /**
     * Turns given array into an Adbar\Dot (dot notation).
     *
     * @param array $array The array to turn into a dot array.
     *
     * @return array The dot aray.
     */
    public function dotToArray(array $array): array
    {
        $dotArray = new Dot($array, true);

        return $dotArray->all();
    }

    /**
     * Makes it possible to use the php function array_values in twig.
     *
     * @param array $array The array to use array_values on.
     *
     * @return array The updated array.
     */
    public function arrayValues(array $array): array
    {
        return array_values($array);
    }
}
