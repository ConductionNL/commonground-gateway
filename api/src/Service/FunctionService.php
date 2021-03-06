<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;

class FunctionService
{
    public CacheInterface $cache;
    public CommonGroundService $commonGroundService;

    public function __construct(CacheInterface $cache, CommonGroundService $commonGroundService)
    {
        $this->cache = $cache;
        $this->commonGroundService = $commonGroundService;
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
                if (array_key_exists('organizationType', $data) && $data['organizationType']) {
                    $organizationType = $data['organizationType'];
                } else {
                    $organizationType = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('type'))->getValue();
                }
                $objectEntity = $this->createOrganization($objectEntity, $data['uri'], $organizationType);
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

            // Invalidate all changed & related organizations from cache
            if ($organization = $this->isResource($uri)) {
                $tags = ['organization_'.md5($uri)];
                if (count($organization['subOrganizations']) > 0) {
                    foreach ($organization['subOrganizations'] as $subOrganization) {
                        $tags[] = 'organization_'.md5($subOrganization['@id']);
                    }
                }
                if (array_key_exists('parentOrganization', $organization) && $organization['parentOrganization'] != null) {
                    $tags[] = 'organization_'.md5($organization['parentOrganization']['@id']);
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
     * @throws \Psr\Cache\InvalidArgumentException
     *
     * @return array
     */
    public function getOrganizationFromCache($url): array
    {
        $item = $this->cache->getItem('organizations_'.md5("$url"));
        if ($item->isHit()) {
            return $item->get();
        }

        if ($organization = $this->isResource($url)) {
            $item->set($organization);
            $item->tag('organization_'.md5("$url"));

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
}
