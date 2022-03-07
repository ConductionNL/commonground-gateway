<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\Subscriber;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class SubscriberService
{
    private EntityManagerInterface $entityManager;
    private ConvertToGatewayService $convertToGatewayService;
    private TranslationService $translationService;
    private EavService $eavService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ConvertToGatewayService $convertToGatewayService,
        TranslationService $translationService,
        EavService $eavService
    ) {
        $this->entityManager = $entityManager;
        $this->convertToGatewayService = $convertToGatewayService;
        $this->translationService = $translationService;
        $this->eavService = $eavService;
    }

    /**
     * This function handles all subscribers for an Entity with the given data.
     */
    public function handleSubscribers(Entity $entity, array $data, string $method)
    {
        if (empty($entity->getSubscribers())) {
            return;
        }
        //todo
        foreach ($entity->getSubscribers() as $subscriber) {
            if ($method !== $subscriber->getMethod()) {
                continue;
            }
            switch ($subscriber->getType()) {
                case 'externSource':
                    $this->handleExternSource($subscriber, $data);
                    break;
                case 'internGateway':
                    $this->handleInternGateway($subscriber, $data);
                    break;
                default:
                    throw new GatewayException(
                        'Unknown subscriber type!',
                        null,
                        null,
                        [
                            'data'         => $subscriber->getType(),
                            'path'         => $entity->getName(),
                            'responseType' => Response::HTTP_BAD_REQUEST,
                        ]
                    );
            }
        }
    }

    private function handleExternSource(Subscriber $subscriber, array $data)
    {
        // todo: add code for asynchronous option

        // todo: Create a log at the end of every subscriber trigger? (add config for this?)
    }

    private function handleInternGateway(Subscriber $subscriber, array $data)
    {
        // todo: add code for asynchronous option

        // todo: mapping & translation
//        $skeleton = $subscriber->getSkeletonIn();
        $skeleton = [];
        if (!$skeleton || empty($skeleton)) {
            $skeleton = $data;
        }
        $data = $this->translationService->dotHydrator($skeleton, $data, $subscriber->getMappingIn());

//        var_dump('do a post with this data:');
//        var_dump($data);
        // If we have an 'externalId' or externalUri after mapping we use that to create a gateway object with the ConvertToGatewayService.
        if (array_key_exists('externalId', $data)) {
            $newObjectEntity = $this->convertToGatewayService->convertToGatewayObject($subscriber->getEntityOut(), null, $data['externalId']);
//            var_dump($subscriber->getEntity()->getName().' -> '.$subscriber->getEntityOut()->getName());
//            var_dump('Created a new objectEntity: '.$newObjectEntity->getId()->toString());
            // todo log this^
        } elseif (array_key_exists('externalUri', $data)) {
            // todo: get id from uri instead and do the above^
        } else {
            // todo: create a gateway object of entity $subscriber->getEntityOut() with the $data array
//            var_dump('InternGatewaySubscriber for entity: '.$subscriber->getEntity()->getName().' -> '.$subscriber->getEntityOut()->getName());
        }

        // todo mapping out?
        $data = $this->translationService->dotHydrator($skeleton, $data, $subscriber->getMappingOut());

        // todo: Create a log at the end of every subscriber trigger? (add config for this?)

        // Check if we need to trigger subscribers for this newly create objectEntity
        $newObjectData = $this->eavService->handleGet($newObjectEntity, []);
        $this->handleSubscribers($subscriber->getEntityOut(), $newObjectData, "POST");
    }
}
