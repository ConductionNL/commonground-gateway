<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;

class FunctionService
{
    private CacheInterface $cache;
    private CommonGroundService $commonGroundService;
    private ObjectEntityService $objectEntityService;
    public array $removeResultFromCache;

    public function __construct(CacheInterface $cache, CommonGroundService $commonGroundService, ObjectEntityService $objectEntityService)
    {
        $this->cache = $cache;
        $this->commonGroundService = $commonGroundService;
        $this->objectEntityService = $objectEntityService;
        $this->removeResultFromCache = [];
    }

    /**
     * Handles the function of an Entity, this can be done in very different situations. That is why the data array should always contains a few specific keys!
     *
     * @param ObjectEntity $objectEntity
     * @param string       $function
     * @param array        $data         Should at least contain the following key: method
     *
     * @return ObjectEntity
     */
    public function handleFunction(ObjectEntity $objectEntity, string $function, array $data): ObjectEntity
    {
        switch ($function) {
            case 'organization':
                if ($data['method'] == 'POST') {
                    if (array_key_exists('organizationType', $data) && $data['organizationType']) {
                        $organizationType = $data['organizationType'];
                    } else {
                        $organizationType = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('type'))->getValue();
                    }
                    $objectEntity = $this->createOrganization($objectEntity, $data['uri'], $organizationType);
                }
                break;
            case 'userGroup':
                if ($data['method'] == 'PUT') {
                    if (array_key_exists('userGroupName', $data) && $data['userGroupName']) {
                        $userGroupName = $data['userGroupName'];
                    } else {
                        $userGroupName = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('name'))->getValue();
                    }
                    $objectEntity = $this->updateUserGroup($objectEntity, $userGroupName);
                }
                break;
            default:
                break;
        }

        return $objectEntity;
    }

    //todo: note: this createOrganization function is also used in different places than only the handleFunction function above^
    /**
     * Performs the organization function. This is called when a new ObjectEntity is created for an Entity with function = 'organization'.
     *
     * @param ObjectEntity $objectEntity
     * @param string       $uri
     * @param string|null  $organizationType This is nullable so that it won't trigger 500's when no organization type is given, but a nice and correct error (if organization type is configured to be required, as it should)
     *
     * @return ObjectEntity
     */
    public function createOrganization(ObjectEntity $objectEntity, string $uri, ?string $organizationType): ObjectEntity
    {
        if ($organizationType == 'taalhuis') {
            $objectEntity->setOrganization($uri);

            $id = substr($uri, strrpos($uri, '/') + 1);
            if (!$organization = $this->isResource($uri)) {
                if (!$organization = $this->objectEntityService->getObjectByUri($uri)) {
                    $organization = $this->objectEntityService->getOrganizationObject($id);
                }
            }
            // Invalidate all changed & related organizations from cache
            if (!empty($organization)) {
                $tags = ['organization_'.base64_encode($uri)];
                if (array_key_exists('subOrganizations', $organization) && count($organization['subOrganizations']) > 0) {
                    foreach ($organization['subOrganizations'] as $subOrganization) {
                        $tags[] = 'organization_'.base64_encode($subOrganization['@id']);
                    }
                }
                if (array_key_exists('parentOrganization', $organization) && $organization['parentOrganization'] != null) {
                    $tags[] = 'organization_'.base64_encode($organization['parentOrganization']['@id']);
                }
                $this->cache->invalidateTags($tags);
            }
        }

        return $objectEntity;
    }

    /**
     * Performs the userGroup function.
     *
     * @param ObjectEntity $objectEntity
     * @param string|null  $userGroupName This is nullable so that it won't trigger 500's when no group name is given
     *
     * @return ObjectEntity
     */
    public function updateUserGroup(ObjectEntity $objectEntity, ?string $userGroupName): ObjectEntity
    {
        if ($userGroupName == 'ANONYMOUS') {
            $this->cache->invalidateTags(['anonymousScopes']);
        }

        return $objectEntity;
    }

    /**
     * Gets an organization for an url from cache or url, depending on cache.
     *
     * @TODO: move this elsewhere.
     *
     * @param $url
     *
     * @throws \Psr\Cache\CacheException
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function getOrganizationFromCache($url): array
    {
        $item = $this->cache->getItem('organizations_'.base64_encode("$url"));
        if ($item->isHit()) {
            return $item->get();
        }

        $id = substr($url, strrpos($url, '/') + 1);
        if (!$organization = $this->isResource($url)) {
            if (!$organization = $this->objectEntityService->getObjectByUri($url)) {
                $organization = $this->objectEntityService->getOrganizationObject($id);
            }
        }
        if (!empty($organization)) {
            $item->set($organization);
            $item->tag('organization_'.base64_encode("$url"));

            $this->cache->save($item);

            return $organization;
        }

        return [];
    }

    /**
     * IsResource function from commongroundService without caching.
     *
     * @TODO: Make cache settable in CGB and remove.
     *
     * @param $url
     *
     * @return array|false|mixed|string|null
     */
    public function isResource($url)
    {
        try {
            return $this->commonGroundService->getResource($url, [], false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Removes all responses saved for the given ObjectEntity from the cache. Also does the same for all parent objects of the given object.
     * Always use $this->functionService->removeResultFromCache = []; before using this function to reset the list of objects that already got removed from cache.
     * If this function is called multiple times in a row it might be better to do this before a loop or starting a recursive function.
     *
     * @param ObjectEntity $objectEntity
     *
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    public function removeResultFromCache(ObjectEntity $objectEntity): bool
    {
        if (!in_array($objectEntity->getId()->toString(), $this->removeResultFromCache)) {
            if (!$objectEntity->getSubresourceOf()->isEmpty()) {
                $this->removeResultFromCache[] = $objectEntity->getId()->toString();
                foreach ($objectEntity->getSubresourceOf() as $parentValue) {
                    $this->removeResultFromCache($parentValue->getObjectEntity());
                }
            }

            return $this->cache->invalidateTags(['object_'.base64_encode($objectEntity->getId()->toString())]) && $this->cache->commit();
        }

        return false;
    }
}
