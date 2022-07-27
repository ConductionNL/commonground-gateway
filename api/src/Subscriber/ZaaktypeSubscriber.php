<?php

namespace App\Subscriber;

use App\Entity\Action;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use App\Repository\ObjectEntityRepository;
use App\Service\ObjectEntityService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class ZaaktypeSubscriber implements EventSubscriberInterface
{

    private ObjectEntityRepository $objectEntityRepository;
    private EntityManagerInterface $entityManager;
    private Request $request;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'commongateway.handler.pre' => 'handleEvent',
        ];
    }

    public function __construct(EntityManagerInterface $entityManager, ObjectEntityRepository $objectEntityRepository)
    {
        $this->entityManager = $entityManager;
        $this->objectEntityRepository = $objectEntityRepository;
    }

    public function getAction(Endpoint $endpoint): ?Action
    {
        $actions = $this->entityManager->getRepository("App:Action")->findBy(['endpointId' => $endpoint->getId()]);
        foreach($actions as $action)
        {
            if(
                $action instanceof Action &&
                $action->getType() == 'zds-zaaktype-to-zgw-zaaktype'
            ){
                return $action;
            }
        }
        return null;
    }

    public function getZaakTypeEntity(Action $action): Entity
    {
        $entity = $this->entityManager->getRepository("App:Entity")->findOneBy([
            'collections'   => $action['ztc_collection'],
            'name'          => $action['zaakTypeName'],
        ]);

        if($entity instanceof Entity){
            return $entity;
        } else {
            throw new BadRequestException('The entity of the ZTC collection with name ' . $action['zaakTypeName'] . 'does not exist');
        }
    }

    public function getIdentifier(Request $request, Action $action): string
    {
        $xmlEncoder = new XmlEncoder();
        $data = $xmlEncoder->decode($request->getContent(), 'xml');
        $dotData = new \Adbar\Dot($data);

        return $dotData->get($action->getConfiguration()['zaakTypeIdentifier']);
    }

    public function findObjectEntity(Entity $zaakTypeEntity, string $identifier): ObjectEntity
    {
        return $this->objectEntityRepository->findByEntity($zaakTypeEntity, ['identificatie' => $identifier])[0];
    }

    public function handleEvent(ActionEvent $event): ActionEvent
    {
        return $event;
//        if(
//            $event->getRequest()->getMethod() != 'POST' &&
//            !$action = $this->getAction($event->getEndpoint())
//        ) {
//            return $event;
//        }
//        $zaakTypeEntity = $this->getZaakTypeEntity($action);
//        $zaakType = $this->findObjectEntity($zaakTypeEntity, $this->getIdentifier($event->getRequest(), $action))->getSelf();
//        return $event;
    }
}
