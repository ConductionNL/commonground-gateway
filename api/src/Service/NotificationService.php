<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;

/**
 * This service holds al the logic for the notification plugin.
 */
class NotificationService
{
    private CommonGroundService $commonGroundService;
    private ObjectEntityService $objectEntityService;
    private array $data;
    private array $configuration;

    /**
     * @param \Conduction\CommonGroundBundle\Service\CommonGroundServic $commonGroundService
     * @param \App\Service\ObjectEntityService                          $objectEntityService
     */
    public function __construct(
        CommonGroundService $commonGroundService,
        ObjectEntityService $objectEntityService
    ) {
        $this->commonGroundService = $commonGroundService;
        $this->objectEntityService = $objectEntityService;
    }

    /**
     * Handles the notifiaction actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     * @return array
     */
    public function NotificationHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Lets see if we have an object
        if (
            in_array('id', $this->data) &&
            $object = $this->objectEntityService->getObject(null, $this->data['id'])) {
            return $this->sendNotification($object)->toArray();
        }

        return $data;
    }

    /**
     * Send a notifiction in line with the cloudevent standard.
     *
     * @param ObjectEntity $object
     *
     * @return ObjectEntity
     */
    public function sendNotification(ObjectEntity $object): ObjectEntity
    {
        // Determine the id

        // Skelleton notification bassed on https://github.com/VNG-Realisatie/notificatieservices look at https://github.com/VNG-Realisatie/NL-GOV-profile-for-CloudEvents/blob/main/NL-GOV-Guideline-for-CloudEvents-JSON.md for a json example
        $notification = [
            'specversion'=> $this->configuration['specversion'],
            'type'       => $this->configuration['type'],
            'source'     => $this->configuration['source'],
            'subject'    => $object->getId(),
            'id'         => 'f3dce042-cd6e-4977-844d-05be8dce7cea',
            'time'       => '2021-12-10T17:31:00Z', // @todo current datetime
            //"nlbrpnationaliteit"=>"0083",
            //"geheimnummer"=>null,
            'dataref'=> $this->configuration['dataref'].$object->getId(),
            //"sequence"=>"1234",
            //"sequencetype"=>"integer",
            'datacontenttype'=> $this->configuration['source'],
        ];

        // Include data if so required
        if ($this->configuration['includeData']) {
            $notification['data'] = $object->toArray();
        }

        // @todo  fire a simple guzle call to post the notification

        // Grap the source to notify
        $source = $this->objectEntityServic->get($this->configuration['sourceId']);

        // Send the notification
        try {
            $result = $this->commonGroundService->callService(
                $source,
                $this->configuration['endpoint'],
                json_encode($notification),
                $this->configuration['query'],
                $this->configuration['headers'],
            );
        } catch (Exception $exception) {
        }

        return $object;
    }
}
