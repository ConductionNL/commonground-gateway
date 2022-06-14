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
    private LogService $logService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ConvertToGatewayService $convertToGatewayService,
        TranslationService $translationService,
        EavService $eavService,
        ValidationService $validationService,
        LogService $logService
    ) {
        $this->entityManager = $entityManager;
        $this->convertToGatewayService = $convertToGatewayService;
        $this->translationService = $translationService;
        $this->eavService = $eavService;
        $this->validationService = $validationService;
        $this->logService = $logService;
    }

    /**
     * This function handles all subscribers for an Entity with the given data.
     */
    public function handleSubscribers(Entity $entity, array $data, string $method)
    {
        if (empty($entity->getSubscribers())) {
            return;
        }
        //todo subscriber->runOrder
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

        // todo: here we should handle the creation of a zgw zaak, instead of with the old validationService...
        // todo: see code in validationService->createPromise function...

        // todo: Create a log at the end of every subscriber trigger? (add config for this?)
    }

    private function handleInternGateway(Subscriber $subscriber, array $data)
    {
        // todo: add code for asynchronous option

        // Mapping in incl. skeletonIn
        $skeleton = $subscriber->getSkeletonIn();
        if (empty($skeleton)) {
            $skeleton = $data;
        }
        $data = $this->translationService->dotHydrator($skeleton, $data, $subscriber->getMappingIn());

        // todo: translation
        //todo: use translationService to change datetime format
        if (array_key_exists('startdatum', $data)) {
            $startdatum = new \DateTime($data['startdatum']);
            $data['startdatum'] = $startdatum->format('Y-m-d');
        }
        if (array_key_exists('registratiedatum', $data)) {
            $registratiedatum = new \DateTime($data['registratiedatum']);
            $data['registratiedatum'] = $registratiedatum->format('Y-m-d');
        }

//        var_dump('InternGatewaySubscriber for entity: '.$subscriber->getEntity()->getName().' -> '.$subscriber->getEntityOut()->getName());
//        var_dump($data);

        // If we have an 'externalId' after mapping we use that to create a gateway object with the ConvertToGatewayService.
        if (array_key_exists('externalId', $data)) {
            // Convert an object outside the gateway into an ObjectEntity in the gateway
            $newObjectEntity = $this->convertToGatewayService->convertToGatewayObject($subscriber->getEntityOut(), null, $data['externalId']);
            $data = $this->eavService->handleGet($newObjectEntity, null, null);
//            var_dump($data);

            // create log
            $responseLog = new Response(json_encode($data), 201, []);
            $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 11, json_encode($data), null, 'out');
        } else {
            // Create a gateway object of entity $subscriber->getEntityOut() with the $data array
            $newObjectEntity = $this->eavService->getObject(null, 'POST', $subscriber->getEntityOut());

            $validationServiceRequest = new Request();
            $validationServiceRequest->setMethod('POST');
            $this->validationService->setRequest($validationServiceRequest);
//            $this->validationService->createdObjects = $this->request->getMethod() == 'POST' ? [$object] : [];
//            $this->validationService->removeObjectsNotMultiple = []; // to be sure
//            $this->validationService->removeObjectsOnPut = []; // to be sure
            // todo: use new ObjectEntityService->saveObject function
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

            // todo mapping out & translation out?
//            $skeleton = $subscriber->getSkeletonOut();
//            if (empty($skeleton)) {
//                $skeleton = $data;
//            }
//            $data = $this->translationService->dotHydrator($skeleton, $data, $subscriber->getMappingOut());
        }

        // todo: Create a log at the end of every subscriber trigger? (add config for this?)

        if (!empty($newObjectEntity)) {
//            var_dump('Created a new objectEntity: '.$newObjectEntity->getId()->toString());

            // Check if we need to trigger subscribers for this newly create objectEntity
            $this->handleSubscribers($subscriber->getEntityOut(), $data, $subscriber->getMethod());
        }
    }
}
