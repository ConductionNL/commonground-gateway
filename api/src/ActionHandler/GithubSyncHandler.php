<?php

namespace App\ActionHandler;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use App\Service\ObjectEntityService;
use App\Service\PubliccodeService;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use function Symfony\Component\String\u;

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
     * This function creates a github organization
     *
     * @param array $data
     * @param ObjectEntity|null $objectEntity The object entity that relates to the entity Eigenschap
     * @param string $method
     * @return ObjectEntity Creates a github organization
     * @throws GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     */
    public function createOrUpdateObject(array $data, ObjectEntity $objectEntity, string $method = 'POST'): ObjectEntity
    {
        $object = new ObjectEntity();
        $object->setEntity($objectEntity->getEntity());

        return $this->synchronizationService->populateObject($data, $object, $method);
    }

    /**
     * This function creates a github organization
     *
     * @param ObjectEntity $orgObjectEntity
     * @param ObjectEntity|null $objectEntity The object entity that relates to the entity Eigenschap
     * @param bool $exist
     * @param array $githubOrg
     * @return bool Creates a github organization
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
     * This function creates a github organization
     *
     * @param ObjectEntity|null $orgObjectEntity
     * @param string $githubUrl
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
     * This function creates a github organization
     *
     * @param array $githubOrg
     * @param ObjectEntity|null $objectEntity The object entity that relates to the entity Eigenschap
     * @return bool Creates a github organization
     * @throws GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     */
    public function checkOrganization(array $githubOrg, ObjectEntity $objectEntity): bool
    {
        $organization = null;
        $collection = $this->entityManager->getRepository('App:CollectionEntity')->findOneBy(['name' => 'OpenCatalogi collection']);
        foreach ($collection->getEntities() as $entity) {
            if ($entity->getName() == 'Organisation') {
                $organization = $entity;
                break;
            }
        }

        if ($organization !== null && count($organization->getObjectEntities()) > 0) {
            foreach ($organization->getObjectEntities() as $orgObjectEntity) {
                $exist = $this->checkIfOrganizationExist($orgObjectEntity, $githubOrg['github']);
                if ($exist) {
                    break;
                }
            }
            return $this->createOrLinkOrg($orgObjectEntity, $objectEntity, $exist, $githubOrg);
        }

        return false;
    }

    /**
     * This function creates a github organization
     *
     * @param ObjectEntity|null $objectEntity The object entity that relates to the entity Eigenschap
     * @return array|null Creates a github organization
     * @throws \GuzzleHttp\Exception\GuzzleException
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
     * This function creates a github organization
     *
     * @param array $data
     * @param array $configuration
     * @return array Creates a github organization
     * @throws GatewayException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     */
    public function __run(array $data, array $configuration): array
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Repository']);
        if (count($entity->getObjectEntities()) > 0) {
            foreach ($entity->getObjectEntities() as $objectEntity) {
                $githubOrg = $this->getGithubOrg($objectEntity);
                $githubOrg !== null && key_exists('github', $githubOrg) ? $exist = $this->checkOrganization($githubOrg, $objectEntity) : $exist = null;
            }
        }

        return $data;
    }
}
