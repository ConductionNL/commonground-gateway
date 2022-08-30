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
        // Skelleton notificaiton bassed on https://github.com/VNG-Realisatie/notificatieservices look at https://github.com/VNG-Realisatie/NL-GOV-profile-for-CloudEvents/blob/main/NL-GOV-Guideline-for-CloudEvents-JSON.md for a json example
        $notification = [
            "specversion"=>"1.0",
             "type"=>"nl.overheid.zaken.zaakstatus-gewijzigd",
             "source"=>"urn:nld:oin:00000001823288444000:systeem:BRP-component",
             "subject"=>"123456789",
             "id"=>"f3dce042-cd6e-4977-844d-05be8dce7cea",
             "time"=>"2021-12-10T17:31:00Z",
             "nlbrpnationaliteit"=>"0083",
             "geheimnummer"=>null,
             "dataref"=>"https://gemeenteX/api/persoon/123456789",
             "sequence"=>"1234",
             "sequencetype"=>"integer",
             "datacontenttype"=>"application/json"
        ];

        // Include data if so required
        if($this->configuration['includeData']){
            $notification['data'] = $object->toArray();
        }

        // @todo  fire a simple guzle call to post the notification

        return $object;
    }

}
