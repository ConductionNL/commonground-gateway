<?php

namespace App\Service;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * This service holds al the logic for the notification plugin.
 */
class NotificationService
{
    private EntityManagerInterface $entityManager;
    private CommonGroundService $commonGroundService;
    private ObjectEntityService $objectEntityService;
    private GatewayService $gatewayService;
    private array $data;
    private array $configuration;

    /**
     * @param EntityManagerInterface $entityManager
     * @param CommonGroundService    $commonGroundService
     * @param ObjectEntityService    $objectEntityService
     * @param GatewayService         $gatewayService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CommonGroundService $commonGroundService,
        ObjectEntityService $objectEntityService,
        GatewayService $gatewayService
    ) {
        $this->entityManager = $entityManager;
        $this->commonGroundService = $commonGroundService;
        $this->objectEntityService = $objectEntityService;
        $this->gatewayService = $gatewayService;
    }

    /**
     * Handles the notification actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws SyntaxError
     *
     * @return array
     */
    public function NotificationHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Let's see if we have an object
        if (
            in_array('id', $this->data['response']) &&
            $object = $this->objectEntityService->getObjectByUri($this->data['response']['@uri'])) {
            return $this->sendNotification($object);
        }

        return $data;
    }

    /**
     * Send a notification in line with the cloudEvent standard.
     *
     * @param array $object
     *
     * @return array
     */
    public function sendNotification(array $object): array
    {
        // Skeleton notification based on https://github.com/VNG-Realisatie/notificatieservices look at https://github.com/VNG-Realisatie/NL-GOV-profile-for-CloudEvents/blob/main/NL-GOV-Guideline-for-CloudEvents-JSON.md for a json example
        $notification = [
            'specversion'=> $this->configuration['specversion'],
            'type'       => $this->configuration['type'],
            'source'     => $this->configuration['source'],
            'subject'    => $object['id'],
            'id'         => 'f3dce042-cd6e-4977-844d-05be8dce7cea',
            'time'       => new \DateTime('now'),
            //"nlbrpnationaliteit"=>"0083",
            //"geheimnummer"=>null,
            'dataref'=> $this->configuration['dataref'].$object['id'],
            //"sequence"=>"1234",
            //"sequencetype"=>"integer",
            'datacontenttype'=> $this->configuration['datacontenttype'],
        ];

        // Include data if so required
        if (array_key_exists('includeData', $this->configuration)) {
            $notification['data'] = $object;
        }

        // Grep the source to notify
        $source = $this->entityManager->getRepository('App:Gateway')->find($this->configuration['sourceId']);

        // Send the notification
        try {
            $this->commonGroundService->callService(
                $this->gatewayService->gatewayToArray($source),
                $source->getLocation().$this->configuration['endpoint'].($object['id'] ? '/'.$object['id'] : ''),
                json_encode($notification),
                array_key_exists('query', $this->configuration) ? $this->configuration['query'] : [],
                array_key_exists('headers', $this->configuration) ? $this->configuration['headers'] : [],
            );
        } catch (Exception $exception) {
            return $object;
        }

        return $object;
    }
}
