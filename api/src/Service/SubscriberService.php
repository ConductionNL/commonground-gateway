<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\Subscriber;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Promise\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriberService
{
    private EntityManagerInterface $entityManager;
    private ConvertToGatewayService $convertToGatewayService;
    private TranslationService $translationService;
    private EavService $eavService;
    private ValidationService $validationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ConvertToGatewayService $convertToGatewayService,
        TranslationService $translationService,
        EavService $eavService,
        ValidationService $validationService
    ) {
        $this->entityManager = $entityManager;
        $this->convertToGatewayService = $convertToGatewayService;
        $this->translationService = $translationService;
        $this->eavService = $eavService;
        $this->validationService = $validationService;
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

//        var_dump('InternGatewaySubscriber for entity: '.$subscriber->getEntity()->getName().' -> '.$subscriber->getEntityOut()->getName());

        // If we have an 'externalId' or externalUri after mapping we use that to create a gateway object with the ConvertToGatewayService.
        if (array_key_exists('externalId', $data)) {
            $newObjectEntity = $this->convertToGatewayService->convertToGatewayObject($subscriber->getEntityOut(), null, $data['externalId']);
            // todo log this^
            $data = $this->eavService->handleGet($newObjectEntity, null);

            //todo: move this to the bottom where we do mapping out?
            //todo: or even better use mapping in, in the next subscriber instead?
            //todo: add skeletonIn and skeletonOut to subscriber config?
            $skeleton = [
                'zaaktype' => "https://openzaak.sed-xllnc.commonground.nu/catalogi/api/v1/zaaktypen/1d92456b-ed8e-43e7-ad4a-03b8e8556139",
                'bronorganisatie' => "999993653",
                'verantwoordelijkeOrganisatie' => "999993653",
            ];
            $data = $this->translationService->dotHydrator($skeleton, $data, $subscriber->getMappingOut());
//            var_dump($data);
        } else {
            // todo: create a gateway object of entity $subscriber->getEntityOut() with the $data array
            $newObjectEntity = $this->eavService->getObject(null, "POST", $subscriber->getEntityOut());

            $validationServiceRequest = new Request();
            $validationServiceRequest->setMethod("POST");
            $this->validationService->setRequest($validationServiceRequest);
//            $this->validationService->createdObjects = $this->request->getMethod() == 'POST' ? [$object] : [];
//            $this->validationService->removeObjectsNotMultiple = []; // to be sure
//            $this->validationService->removeObjectsOnPut = []; // to be sure
            $newObjectEntity = $this->validationService->validateEntity($newObjectEntity, $data);
            if (!empty($this->validationService->promises)) {
                Utils::settle($this->validationService->promises)->wait();

                foreach ($this->validationService->promises as $promise) {
                    echo $promise->wait();
                }
            }
            $this->entityManager->persist($newObjectEntity);
            $this->entityManager->flush();
            $data['id'] = $newObjectEntity->getId()->toString();
            if ($newObjectEntity->getHasErrors()) {
                $data['validationServiceErrors']['Warning'] = 'There are errors, an ObjectEntity with corrupted data was added, you might want to delete it!';
                $data['validationServiceErrors']['Errors'] = $newObjectEntity->getAllErrors();
//                var_dump($data);
//                var_dump($data['validationServiceErrors']);
            }
        }

        // todo mapping out?
//        $data = $this->translationService->dotHydrator($skeleton, $data, $subscriber->getMappingOut());

        // todo: Create a log at the end of every subscriber trigger? (add config for this?)

        if (!empty($newObjectEntity)) {
//            var_dump('Created a new objectEntity: '.$newObjectEntity->getId()->toString());

            // Check if we need to trigger subscribers for this newly create objectEntity
            $this->handleSubscribers($subscriber->getEntityOut(), $data, "POST");
        }
    }
}
