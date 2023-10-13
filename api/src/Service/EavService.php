<?php

namespace App\Service;

use Adbar\Dot;
use App\Entity\Application;
use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

use function GuzzleHttp\json_decode;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Gino Kok, Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 * @deprecated todo: Carefully take a look at all code before deleting this service, we might want to keep some BL.
 * todo: (and we might use some functions from CoreBundle?)
 */
class EavService
{
    private EntityManagerInterface $em;
    private SessionInterface $session;
    private Stopwatch $stopwatch;
    private FunctionService $functionService;

    public function __construct(
        EntityManagerInterface $em,
        AuthorizationService $authorizationService,
        SessionInterface $session,
        Stopwatch $stopwatch,
        FunctionService $functionService
    ) {
        $this->em = $em;
        $this->session = $session;
        $this->stopwatch = $stopwatch;
        $this->functionService = $functionService;
    }

    /**
     * Looks for an Entity object using a entityName.
     *
     * @param string $entityName
     *
     * @return Entity|array
     * @deprecated
     */
    public function getEntity(string $entityName)
    {
        if (!$entityName) {
            return [
                'message' => 'No entity name provided',
                'type'    => 'Bad Request',
                'path'    => 'entity',
                'data'    => [],
            ];
        }
        $entity = $this->em->getRepository('App:Entity')->findOneBy(['name' => $entityName]);
        if (!($entity instanceof Entity)) {
            $entity = $this->em->getRepository('App:Entity')->findOneBy(['route' => '/api/'.$entityName]);
        }

        if (!($entity instanceof Entity)) {
            return [
                'message' => 'Could not establish an entity for '.$entityName,
                'type'    => 'Bad Request',
                'path'    => 'entity',
                'data'    => ['Entity Name' => $entityName],
            ];
        }

        return $entity;
    }

    /**
     * Looks for a ObjectEntity using an id or creates a new ObjectEntity if no ObjectEntity was found with that id or if no id is given at all.
     *
     * @param string|null $id
     * @param string      $method
     * @param Entity      $entity
     *
     * @throws Exception
     *
     * @return ObjectEntity|array|null
     * @deprecated
     */
    public function getObject(?string $id, string $method, Entity $entity)
    {
        if ($id) {
            // make sure $id is actually an uuid
            if (Uuid::isValid($id) == false) {
                return [
                    'message' => 'The given id ('.$id.') is not a valid uuid.',
                    'type'    => 'Bad Request',
                    'path'    => $entity->getName(),
                    'data'    => ['id' => $id],
                ];
            }

            // Look for object in the gateway with this id (for ObjectEntity id and for ObjectEntity externalId)
            if (!$object = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'id' => $id])) {
                if (!$object = $this->em->getRepository('App:ObjectEntity')->findOneBy(['entity' => $entity, 'externalId' => $id])) {
                    // If gateway->location and endpoint are set on the attribute(->getObject) Entity look outside of the gateway for an existing object.
                    if (!$object) {
                        return [
                            'message' => 'Could not find an object with id '.$id.' of type '.$entity->getName(),
                            'type'    => 'Bad Request',
                            'path'    => $entity->getName(),
                            'data'    => ['id' => $id],
                        ];
                    }
                }
            }
            if ($object instanceof ObjectEntity && $entity !== $object->getEntity()) {
                return [
                    'message' => "There is a mismatch between the provided ({$entity->getName()}) entity and the entity already attached to the object ({$object->getEntity()->getName()})",
                    'type'    => 'Bad Request',
                    'path'    => $entity->getName(),
                    'data'    => [
                        'providedEntityName' => $entity->getName(),
                        'attachedEntityName' => $object->getEntity()->getName(),
                    ],
                ];
            }

            return $object;
        } elseif ($method == 'POST') {
            $object = new ObjectEntity();
            $object->setEntity($entity);
            // if entity->function == 'organization', organization for this ObjectEntity will be changed later in handleMutation
            $this->session->get('activeOrganization') ? $object->setOrganization($this->session->get('activeOrganization')) : $object->setOrganization('http://testdata-organization');
            $application = $this->em->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
            $object->setApplication(!empty($application) ? $application : null);

            return $object;
        }

        return null;
    }

    /**
     * Handles a delete api call.
     *
     * @param ObjectEntity         $object
     * @param ArrayCollection|null $maxDepth
     *
     * @throws InvalidArgumentException
     *
     * @return array
     * @deprecated
     */
    public function handleDelete(ObjectEntity $object, ArrayCollection $maxDepth = null): array
    {
        // Check mayBeOrphaned
        // Get all attributes with mayBeOrphaned == false and one or more objects
        $cantBeOrphaned = $object->getEntity()->getAttributes()->filter(function (Attribute $attribute) use ($object) {
            if (!$attribute->getMayBeOrphaned() && count($object->getSubresources($attribute)) > 0) {
                return true;
            }

            return false;
        });
        if (count($cantBeOrphaned) > 0) {
            $data = [];
            foreach ($cantBeOrphaned as $attribute) {
                $data[] = $attribute->getName();
                //                $data[$attribute->getName()] = $object->getValueObject($attribute)->getId();
            }

            return [
                'message' => 'You are not allowed to delete this object because of attributes that can not be orphaned.',
                'type'    => 'Forbidden',
                'path'    => $object->getEntity()->getName(),
                'data'    => ['cantBeOrphaned' => $data],
            ];
        }

        // Lets keep track of objects we already encountered, for inversedBy, checking maxDepth 1, preventing recursion loop:
        if (is_null($maxDepth)) {
            $maxDepth = new ArrayCollection();
        }
        $maxDepth->add($object);

        foreach ($object->getEntity()->getAttributes() as $attribute) {
            // If this object has subresources and cascade delete is set to true, delete the subresources as well.
            // TODO: use switch for type? ...also delete type file?
            if ($attribute->getType() == 'object' && $attribute->getCascadeDelete() && !is_null($object->getValue($attribute))) {
                if ($attribute->getMultiple()) {
                    // !is_null check above makes sure we do not try to loop through null
                    foreach ($object->getValue($attribute) as $subObject) {
                        if ($subObject && !$maxDepth->contains($subObject)) {
                            $this->handleDelete($subObject, $maxDepth);
                        }
                    }
                }
            } else {
                $subObject = $object->getValue($attribute);
                if ($subObject instanceof ObjectEntity && !$maxDepth->contains($subObject)) {
                    $this->handleDelete($subObject, $maxDepth);
                }
            }
        }
        if ($object->getEntity()->getSource() && $object->getEntity()->getSource()->getLocation() && $object->getEntity()->getEndpoint() && $object->getExternalId()) {
            if ($resource = $this->commonGroundService->isResource($object->getUri())) {
                $this->commonGroundService->deleteResource(null, $object->getUri()); // could use $resource instead?
            }
        }

        // Lets remove unread objects before we delete this object
        $unreads = $this->em->getRepository('App:Unread')->findBy(['object' => $object]);
        foreach ($unreads as $unread) {
            $this->em->remove($unread);
        }

        // Remove this object from cache
        $this->functionService->removeResultFromCache($object);

        $this->em->remove($object);
        $this->em->flush();

        return [];
    }
}
