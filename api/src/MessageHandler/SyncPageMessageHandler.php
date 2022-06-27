<?php

namespace App\MessageHandler;

use App\Entity\ObjectEntity;
use App\Message\NotificationMessage;
use App\Message\SyncPageMessage;
use App\Repository\EntityRepository;
use App\Repository\ObjectEntityRepository;
use App\Service\ConvertToGatewayService;
use App\Service\ObjectEntityService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SyncPageMessageHandler implements MessageHandlerInterface
{
    private CommonGroundService $commonGroundService;
    private ConvertToGatewayService $convertToGatewayService;
    private ObjectEntityRepository $objectEntityRepository;
    private EntityRepository $entityRepository;

    public function __construct(CommonGroundService $commonGroundService, ConvertToGatewayService $convertToGatewayService, ObjectEntityRepository $objectEntityRepository, EntityRepository $entityRepository)
    {
        $this->commonGroundService = $commonGroundService;
        $this->convertToGatewayService = $convertToGatewayService;
        $this->objectEntityRepository = $objectEntityRepository;
        $this->entityRepository = $entityRepository;
    }

    public function __invoke(SyncPageMessage $message): void
    {
        $callServiceData = $message->getCallServiceData();
        $requiredKeys = ['component', 'url', 'query', 'headers'];
        if (count(array_intersect_key($callServiceData, array_flip($requiredKeys))) !== count($requiredKeys)) {
            // todo: throw error or something
            var_dump('SyncPageMessageHandler->CallServiceData is missing one of the following keys: '.implode(', ', $requiredKeys));
            return;
        }
        $entity = $this->entityRepository->find($message->getEntityId());
        $page = $message->getPage();
        var_dump('Page: '.$page);

        $response = $this->commonGroundService->callService(
            $callServiceData['component'],
            $callServiceData['url'],
            '',
            array_merge($callServiceData['query'], ['page' => $page]),
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

        // Loop through all extern objects and check if they have an object in the gateway, if not create one.
        $newGatewayObjects = new ArrayCollection();
        $collectionConfigEnvelope = [];
        if (array_key_exists('envelope', $entity->getCollectionConfig())) {
            $collectionConfigEnvelope = explode('.', $entity->getCollectionConfig()['envelope']);
        }
        $collectionConfigId = explode('.', $entity->getCollectionConfig()['id']);
        foreach ($response as $externObject) {
            $id = $externObject;
            // Make sure to get this item from the correct place in $externObject
            foreach ($collectionConfigEnvelope as $item) {
                $externObject = $externObject[$item];
            }
            // Make sure to get id of this item from the correct place in $externObject
            foreach ($collectionConfigId as $item) {
                $id = $id[$item];
            }

            // todo: what if this object got changed, than we need to update the gateway object as well?
            if (!$this->objectEntityRepository->findOneBy(['entity' => $entity, 'externalId' => $id])) {
                // Convert this object to a gateway object
                $object = $this->convertToGatewayService->convertToGatewayObject($entity, $externObject, $id, null, null, null, $message->getSessionData());
                if ($object instanceof ObjectEntity) {
                    $newGatewayObjects->add($object);
                }
                // todo: We could use the handleOwner function here to set the owner of this object, but should we set the owner to the person/user that uses the /sync api-call?
            }
        }

        var_dump('New gateway objects = '.count($newGatewayObjects));
    }
}
