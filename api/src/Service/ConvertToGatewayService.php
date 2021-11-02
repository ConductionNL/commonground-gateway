<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ConvertToGatewayService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $em;
    private SessionInterface $session;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, SessionInterface $session)
    {
        $this->commonGroundService = $commonGroundService;
        $this->em = $entityManager;
        $this->session = $session;
    }

    /**
     * @param Entity $entity
     *
     * @return void|null
     * @throws Exception
     */
    public function convertEntityObjects(Entity $entity)
    {
        // Make sure we have a gateway and endpoint on this Entity.
        if (!$entity->getGateway()->getLocation() || !$entity->getEndpoint()) {
            return null; //Or false or error? //todo?
        }

        // Get all objects for this Entity that exist outside the gateway
        $totalExternObjects = $this->commonGroundService->getResourceList($entity->getGateway()->getLocation().'/'.$entity->getEndpoint())['hydra:totalItems'];

        // Loop through all extern objects and check if they have an object in the gateway, if not create one.
        $newGatewayObjects = new ArrayCollection();
        foreach ($totalExternObjects as $externObject) {
            if (!$this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'externalId' => $externObject['id']])) {
                // Convert this object to a gateway object
                $object = $this->convertToGatewayObject($entity, $externObject);
                if ($object) {
                    $newGatewayObjects->add($object);
                }
            }
        }
        var_dump(count($newGatewayObjects));

        $this->em->flush(); // Do we need this here or not?
    }

    /**
     * @param Entity $entity
     * @param array|null $body
     * @param string|null $id
     * @param Value|null $subresourceOf
     * @param ObjectEntity|null $objectEntity
     *
     * @return ObjectEntity|null
     * @throws Exception
     */
    public function convertToGatewayObject(Entity $entity, ?array $body, string $id = null, Value $subresourceOf = null, ObjectEntity $objectEntity = null): ?ObjectEntity
    {
        // Always make sure we have a gateway and endpoint on this Entity.
        if (!$entity->getGateway()->getLocation() || !$entity->getEndpoint()) {
            return null; //Or false or error? //todo?
        }

        // If we have no $body we should use id to look for an extern object, if it exists get it and convert it to ObjectEntity in the gateway
        if (!$body) {
            if (!$id || !$object = $this->commonGroundService->isResource($entity->getGateway()->getLocation() . '/' . $entity->getEndpoint() . '/' . $id)) {
                // If we have no $body or $id, or if no resource with this $id exists...
                return null; //Or false or error? //todo?
            } else {
                $body = $object;
            }
        }

        // Filter out unwanted properties before converting extern object to a gateway ObjectEntity
        $body = array_filter($body, function ($propertyName) use ($entity) {
            if ($entity->getAvailableProperties()) {
                return in_array($propertyName, $entity->getAvailableProperties());
            }

            return $entity->getAttributeByName($propertyName);
        }, ARRAY_FILTER_USE_KEY);

        $newObject = new ObjectEntity();
        $newObject->setEntity($entity);
        if (!is_null($subresourceOf)) {
            $newObject->addSubresourceOf($subresourceOf);
        }

        // Set the externalId, uri, organization and application.
        $newObject->setExternalId($id);
        $newObject->setUri($entity->getGateway()->getLocation().'/'.$entity->getEndpoint().'/'.$id);
        $newObject->setOrganization($this->session->get('activeOrganization')); // TODO?
//                $newObject->setApplication(); // TODO
        // TODO: Do not use validationService validateEntity here, find another way to do subresources and 'validation', required fields in the gateway that are not set in extern object should be set to null?!
        // Loop through entity attributes? if we find a value for this attribute from extern object set it, if not but is required set to null.
//        $newObject = $this->validationService->validateEntity($newObject, $body, true);

        // For in the rare case that a body contains the same uuid of an extern object more than once we need to persist and flush this ObjectEntity in the gateway.
        // Because if we do not do this, multiple ObjectEntities will be created for the same extern object. (externalId needs to be set!)
        if ((is_null($objectEntity) || !$objectEntity->getHasErrors()) && !$newObject->getHasErrors()) {
            $this->em->persist($newObject);
        }

        return $newObject;
    }
}
