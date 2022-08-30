<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;

/**
 * This service holds al the logic for the notification plugin
 */
class NotificationService
{
    private ObjectEntityService $objectEntityService;
    private array $data;
    private array $configuration;

    /**
     * @param \App\Service\ObjectEntityService $objectEntityService
     */
    public function __construct(
        ObjectEntityService $objectEntityService
    ) {
        $this->objectEntityService = $objectEntityService;
    }

    /**
     * Handles the notifiaction actions
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     */
    public function NotificationHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Lets see if we have an object
        if(
            in_array('id', $this->data) &&
            $object = $this->objectEntityService->getObject(null, $this->data['id'])){

            return $this->sendNotification($object)->toArray();
        }

        return $data;
    }

    /**
     * Send a notifiction in line with the cloudevent standard
     *
     * @param ObjectEntity $object
     * @return ObjectEntity
     */
    public function sendNotification(ObjectEntity $object): ObjectEntity
    {
        // Skelleton notificaiton
        $notification = [];



        return $object;
    }

}
