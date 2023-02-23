<?php

namespace App\MessageHandler;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Message\SyncPageMessage;
use App\Repository\EntityRepository;
use App\Repository\ObjectEntityRepository;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SyncPageMessageHandler implements MessageHandlerInterface
{
    private CommonGroundService $commonGroundService;
    private ObjectEntityRepository $objectEntityRepository;
    private EntityRepository $entityRepository;

    public function __construct(CommonGroundService $commonGroundService, ObjectEntityRepository $objectEntityRepository, EntityRepository $entityRepository)
    {
        $this->commonGroundService = $commonGroundService;
        $this->objectEntityRepository = $objectEntityRepository;
        $this->entityRepository = $entityRepository;
    }

    /**
     * Handles a SyncPageMessage message.
     *
     * @param SyncPageMessage $message
     *
     * @throws Exception
     *
     * @return void
     */
    public function __invoke(SyncPageMessage $message): void
    {
        $callServiceData = $message->getCallServiceData();
        $requiredKeys = ['component', 'url', 'query', 'headers'];
        if (count(array_intersect_key($callServiceData, array_flip($requiredKeys))) !== count($requiredKeys)) {
            throw new Exception('CallServiceData is missing one of the following keys: '.implode(', ', $requiredKeys));
        }
        $entity = $this->entityRepository->find($message->getEntityId());

        // Get objects from extern api
        $externObjects = $this->getExternObjects($message, $entity);

        // Loop through all extern objects and update or create an ObjectEntity for it in the gateway.
        $newGatewayObjects = $this->saveObjects($externObjects, ['message' => $message, 'entity' => $entity]);

        // Dump so we can see what's happening in the worker pod online.
        var_dump('Entity: '.$entity->getName().' - Page: '.$message->getPage().' - New / changed gateway objects = '.count($newGatewayObjects));
    }

    /**
     * Uses callService with info from the SyncPageMessage to get all objects from an extern api for one specific page.
     *
     * @param SyncPageMessage $message
     * @param Entity          $entity
     *
     * @return array
     */
    private function getExternObjects(SyncPageMessage $message, Entity $entity): array
    {
        $callServiceData = $message->getCallServiceData();

        $response = $this->commonGroundService->callService(
            $callServiceData['component'],
            $callServiceData['url'],
            '',
            array_merge($callServiceData['query'], $message->getPage() !== 1 ? ['page' => $message->getPage()] : []),
            $callServiceData['headers'],
            false,
            'GET'
        );
        if (is_array($response)) {
            var_dump('callService error: '.$response); //Throw error? //todo?
        }
        $response = json_decode($response->getBody()->getContents(), true);

        // Now get response from the correct place in the response
        $collectionConfigResults = explode('.', $entity->getCollectionConfig()['results']);
        foreach ($collectionConfigResults as $item) {
            $response = $response[$item];
        }

        return $response;
    }

    /**
     * Loop through all extern objects and update or create an ObjectEntity for it in the gateway.
     *
     * @param array $externObjects
     * @param array $messageData   Must contain key 'message' (SyncPageMessage) and key 'entity' (Entity)
     *
     * @return ArrayCollection
     */
    private function saveObjects(array $externObjects, array $messageData): ArrayCollection
    {
        $newGatewayObjects = new ArrayCollection();
        $collectionConfigEnvelope = [];
        if (array_key_exists('envelope', $messageData['entity']->getCollectionConfig())) {
            $collectionConfigEnvelope = explode('.', $messageData['entity']->getCollectionConfig()['envelope']);
        }
        $collectionConfigId = [];
        if (array_key_exists('id', $messageData['entity']->getCollectionConfig())) {
            $collectionConfigId = explode('.', $messageData['entity']->getCollectionConfig()['id']);
        }
        foreach ($externObjects as $externObject) {
            $object = $this->saveObject(
                $externObject,
                [
                    'collectionConfigEnvelope' => $collectionConfigEnvelope,
                    'collectionConfigId'       => $collectionConfigId,
                ],
                $messageData
            );

            if ($object instanceof ObjectEntity) {
                $newGatewayObjects->add($object);
            }
        }

        return $newGatewayObjects;
    }

    private function saveObject(array $externObject, array $config, array $messageData): ?ObjectEntity
    {
        $id = $config['collectionConfigId'] !== [] ? $externObject : null;
        // Make sure to get this item from the correct place in $externObject
        foreach ($config['collectionConfigEnvelope'] as $item) {
            $externObject = $externObject[$item];
        }
        // Make sure to get id of this item from the correct place in $externObject
        if ($id !== null) {
            foreach ($config['collectionConfigId'] as $item) {
                $id = $id[$item];
            }
        }

        // We want to update all objects, unless specified not to, needs config option:
//        if (!$this->objectEntityRepository->findOneBy(['entity' => $messageData['entity'], 'externalId' => $id])) {
        // Convert this object to a gateway object
        // todo: We could use the handleOwner function here to set the owner of this object, but should we set the owner to the person/user that uses the /sync api-call?
//        }

        return $object ?? null;
    }
}
