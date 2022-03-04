<?php

namespace App\Service;

use App\Entity\Entity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class SubscriberService
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    /**
     * This function handles all subscribers for an Entity with the given data.
     */
    public function handleSubscribers(Entity $entity, array $data)
    {
        //todo
        foreach ($entity->getSubscribers() as $subscriber) {
            switch ($subscriber->getType()) {
                case 'externSource':
                    $this->handleExternSource();
                    break;
                case 'internGateway':
                    $this->handleInternGateway();
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

    private function handleExternSource()
    {

    }

    private function handleInternGateway()
    {
        
    }
}
