<?php

namespace App\ActionHandler;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use App\Service\PubliccodeService;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

class GithubSyncHandler implements ActionHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private PubliccodeService $publiccodeService;
    private SynchronizationService $synchronizationService;

    public function __construct(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $publiccodeService = $container->get('publiccodeservice');
        $synchronizationService = $container->get('synchronizationservice');
        if ($entityManager instanceof EntityManagerInterface) {
            $this->entityManager = $entityManager;
            $this->publiccodeService = $publiccodeService;
            $this->synchronizationService = $synchronizationService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
    }

    /**
     * This function creates or updates an object entity.
     *
     * @param array             $data
     * @param ObjectEntity|null $objectEntity The object entity that relates to the entity Eigenschap
     * @param string            $method
     *
     * @throws GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return ObjectEntity Creates a github organization
     */
    public function createOrUpdateObject(array $data, ObjectEntity $objectEntity, string $method = 'POST'): ObjectEntity
    {
        $object = new ObjectEntity();
        $object->setEntity($objectEntity->getEntity());

        return $this->synchronizationService->populateObject($data, $object, $method);
    }

    /**
     * This function creates and/or links the repo object to an organization.
     *
     * @param ObjectEntity      $orgObjectEntity The object entity that relates to the entity Organization
     * @param ObjectEntity|null $objectEntity    The object entity that relates to the entity Repository
     * @param bool              $exist           Bool if organisation exist in the database
     * @param array             $githubOrg       The github organization details
     *
     * @throws GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     */
    public function createOrLinkOrg(ObjectEntity $orgObjectEntity, ObjectEntity $objectEntity, bool $exist, array $githubOrg): bool
    {
        if ($exist == false) {
            // create and link organization
            $newOrg = $this->createOrUpdateObject($githubOrg, $orgObjectEntity);
            $organization['organisation'] = $newOrg->getId()->toString();
            $this->createOrUpdateObject($organization, $objectEntity, 'PUT');
        }

        if ($exist == true) {
            // link organisation
            $organization['organisation'] = $orgObjectEntity->getId()->toString();
            $this->createOrUpdateObject($organization, $objectEntity, 'PUT');
        }

        return false;
    }

    /**
     * This function checks if the organization exist in the database.
     *
     * @param ObjectEntity|null $orgObjectEntity The object entity that relates to the entity Organization
     * @param string            $githubUrl       The github url from the organization array
     *
     * @return bool Creates a github organization
     */
    public function checkIfOrganizationExist(?ObjectEntity $orgObjectEntity, string $githubUrl): ?bool
    {
        if ($orgObjectEntity instanceof ObjectEntity) {
            $githubValue = $orgObjectEntity->getValueByAttribute($orgObjectEntity->getEntity()->getAttributeByName('github'))->getStringValue();

            if ($githubValue == $githubUrl) {
                return true;
            }

            return false;
        }

        return null;
    }

    /**
     * This function returns the entity Organisation from the OpenCatalogi collection.
     *
     * @return Entity Creates a github organization
     */
    public function getCollection(): Entity
    {
        $organization = null;
        $collection = $this->entityManager->getRepository('App:CollectionEntity')->findOneBy(['name' => 'OpenCatalogi collection']);
        foreach ($collection->getEntities() as $entity) {
            if ($entity->getName() == 'Organisation') {
                $organization = $entity;
                break;
            }
        }

        return $organization;
    }

    /**
     * This function gets the entity Organisation from the OpenCatalogi collection
     * Then checks if the organisation exists in the database
     * Then creates and/or links the organisation to the repository.
     *
     * @param array             $githubOrg
     * @param ObjectEntity|null $objectEntity The object entity that relates to the entity Eigenschap
     *
     * @throws GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return bool Creates a github organization
     */
    public function checkOrganization(array $githubOrg, ObjectEntity $objectEntity): bool
    {
        $organization = $this->getCollection();

        if ($organization === null) {
            return false;
        }

        foreach($organization->getObjectEntities() as $orgObjectEntity) {
            $exist = $this->checkIfOrganizationExist($orgObjectEntity, $githubOrg['github']);
            if ($exist) {
                break;
            }
        }

        return $this->createOrLinkOrg($orgObjectEntity, $objectEntity, $exist, $githubOrg);
    }

    /**
     * This function gets the url from the object
     * Then checks if the domain is from github
     * Then retrieves the slug from the url and get the repo from the github api.
     *
     * @param ObjectEntity|null $objectEntity The object entity that relates to the entity Eigenschap
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return array|null Creates a github organization
     */
    public function getGithubOrg(?ObjectEntity $objectEntity): ?array
    {
        $organization = [];
        if ($objectEntity instanceof ObjectEntity) {
            $url = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('url'))->getStringValue();
            $domain = parse_url($url, PHP_URL_HOST);
            $domain == 'github.com' ? $slug = trim(parse_url($url, PHP_URL_PATH), '/') : $slug = null;
            $slug !== null && $organization = $this->publiccodeService->getGithubRepository($slug);
        }

        return $organization;
    }

    /**
     * This function runs the GithubSyncHandler.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws GatewayException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return array Creates a github organization
     */
    public function __run(array $data, array $configuration): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Repository']);
        foreach ($entity->getObjectEntities() as $objectEntity) {
            $githubOrg = $this->getGithubOrg($objectEntity);
            $githubOrg !== null && key_exists('github', $githubOrg) ? $exist = $this->checkOrganization($githubOrg, $objectEntity) : $exist = null;
        }

        return $data;
    }
}
