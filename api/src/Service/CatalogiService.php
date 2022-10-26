<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class CatalogiService
{
    private EntityManagerInterface $entityManager;
    private array $data;
    private array $configuration;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    /**
     * Handles the sending of an email based on an event.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     */
    public function catalogiHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        //todo:
        // Get all the Catalogi we know of
        // Get the Catalogi of all the Catalogi we know of
        // Check if these Catalogi know any Catalogi we don't know yet
        // Add any unknown Catalogi so we know them as well
        // Repeat for newly added Catalogi (recursion)

        // todo: how do we ever remove a Catalogi? If the existing Catalogi keep adding the removed Catalogi?

        return $data;
    }
}
