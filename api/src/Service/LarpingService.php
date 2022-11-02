<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;

/**
 * This service holds al the logic for the larping plugin.
 */
class LarpingService
{
    private ObjectEntityService $objectEntityService;
    private array $data;
    private array $configuration;

    /**
     * @param ObjectEntityService $objectEntityService
     */
    public function __construct(
        ObjectEntityService $objectEntityService
    ) {
        $this->objectEntityService = $objectEntityService;
    }

    /**
     * This function calculates the atributes for any or all character effected by a change in the data set.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     * @return array
     */
    public function LarpingHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Check if the provided data is either a character or effect
        if (
            in_array('id', $this->data) &&
            $object = $this->objectEntityService->getObject(null, $this->data['id'])) {
            // okey we have data so lets do our magic
            // switch &&
            //            ($object->getEntity()->getName() == 'character' || $object->getEntity()->getName() == 'effect')
        }

        return $data;
    }

    public function calculateAtributes(ObjectEntity $character): ObjectEntity
    {
        $atributes = [];

        $character->setValue('atributes', $atributes);
    }
}
