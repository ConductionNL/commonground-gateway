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

        return $data;
    }
}
