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

    public function __construct(
        EntityManagerInterface $entityManager,
        ConvertToGatewayService $convertToGatewayService,
        TranslationService $translationService
    ) {
        $this->entityManager = $entityManager;
        $this->convertToGatewayService = $convertToGatewayService;
        $this->translationService = $translationService;
    }

    /**
     * This function handles all subscribers for an Entity with the given data.
     */
    public function handleSubscribers(Entity $entity, array $data)
    {
        if (empty($entity->getSubscribers())) {
            return;
        }
        //todo
        foreach ($entity->getSubscribers() as $subscriber) {
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

        switch ($subscriber->getMethod()) {
            case "POST":
//                var_dump('do a post with this data:');
//                var_dump($data);
                // If we have an 'externalId' or externalUri after mapping we use that to create a gateway object with the ConvertToGatewayService.
                if (array_key_exists('externalId', $data)) {
                    $newObjectEntity = $this->convertToGatewayService->convertToGatewayObject($subscriber->getEntityOut(), null, $data['externalId']);
//                    var_dump($newObjectEntity->getId()->toString());
                    // todo log this^
                } elseif (array_key_exists('externalUri', $data)) {
                    // todo: get id from uri instead and do the above^
                }
                break;
            case "GET":
            default:
                break;
        }

        // todo: Create a log at the end of every subscriber trigger? (add config for this?)
    }
}
