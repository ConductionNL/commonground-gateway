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

        $newCatalogi = $this->pullCatalogi();

        // todo: how do we ever remove a Catalogi? If the existing Catalogi keep adding the removed Catalogi?

        // todo: return $newCatalogi?
        return $data;
    }

    /**
     * @todo
     *
     * @param array|null $newCatalogi
     *
     * @return array
     */
    private function pullCatalogi(array $newCatalogi = null): array
    {
        // Get all the Catalogi we know of
        $knownCatalogi = $newCatalogi ? [$newCatalogi] : $this->getAllKnownCatalogi();

        // Check for new unknown Catalogi
        $unknownCatalogi = $this->checkForUnknownCatalogi($knownCatalogi);

        // Add any unknown Catalogi so we know them as well
        return $this->addNewCatalogi($unknownCatalogi);
    }

    /**
     * @todo
     *
     * @return array knownCatalogi
     */
    private function getAllKnownCatalogi(): array
    {
        //todo: do this in a different way?
        $knownCatalogi = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $this->configuration['entity']]);

        // Convert ObjectEntities to useable arrays
        foreach ($knownCatalogi as &$catalogi) {
            $catalogi = $catalogi->toArray();
        }

        return $knownCatalogi;
    }

    /**
     * @todo
     *
     * @param array $knownCatalogi
     *
     * @return array unknownCatalogi
     */
    private function checkForUnknownCatalogi(array $knownCatalogi): array
    {
        $unknownCatalogi = [];

        // Get the Catalogi of all the Catalogi we know of
        foreach ($knownCatalogi as $catalogi) {
            $url = $catalogi['source']['location'].$this->configuration['location'];
            $externCatalogi = []; //todo: callService on the source of these $catalogi

            // Check if these Catalogi know any Catalogi we don't know yet
            foreach ($externCatalogi as $checkCatalogi) {
                if (!$this->checkIfCatalogiExists($knownCatalogi, $checkCatalogi)) {
                    $unknownCatalogi[] = $checkCatalogi;
                }
            }
        }

        return $unknownCatalogi;
    }

    /**
     * @todo
     *
     * @param array $knownCatalog
     * @param array $checkCatalogi
     *
     * @return bool
     */
    private function checkIfCatalogiExists(array $knownCatalog, array $checkCatalogi): bool
    {
        $catalogiIsKnown = array_filter($knownCatalog, function ($catalogi) use ($checkCatalogi) {
            //todo can we use break here? or do we need a foreach for that?
            return $catalogi['source']['location'] === $checkCatalogi['source']['location'];
        });

        if (is_countable($catalogiIsKnown) and count($catalogiIsKnown) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @todo
     *
     * @param array $unknownCatalogi
     *
     * @return array addedCatalogi
     */
    private function addNewCatalogi(array $unknownCatalogi): array
    {
        $addedCatalogi = [];
        // Add unknown Catalogi
        foreach ($unknownCatalogi as $addCatalogi) {
            $newCatalogi = $addCatalogi; //todo actually add Catalogi

            // Repeat pull for newly added Catalogi (recursion)
            $addedCatalogi = array_merge($addedCatalogi, $this->pullCatalogi($newCatalogi));
        }

        return $addedCatalogi;
    }
}
