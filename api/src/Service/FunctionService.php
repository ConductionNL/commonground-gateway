<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;

class FunctionService
{
    private CacheInterface $cache;
    private CommonGroundService $commonGroundService;
    private ObjectEntityService $objectEntityService;

    public function __construct(CacheInterface $cache, CommonGroundService $commonGroundService, ObjectEntityService $objectEntityService)
    {
        $this->cache = $cache;
        $this->commonGroundService = $commonGroundService;
        $this->objectEntityService = $objectEntityService;
    }

    /**
     * Performs the organization function.
     *
     * @param ObjectEntity $objectEntity
     * @param string       $uri
     * @param string|null  $organizationType This is nullable so that it won't trigger 500's when no organization type is given, but a nice and correct error (if organization type is configured to be required, as it should)
     *
     * @return ObjectEntity
     */
    public function createOrganization(ObjectEntity $objectEntity, string $uri, ?string $organizationType): ObjectEntity
    {
        //TODO: $organizationType is a quick fix for taalhuizen, we need to find a better solution!
        if ($organizationType == 'taalhuis') {
            $objectEntity->setOrganization($uri);

            $id = substr($uri, strrpos($uri, '/') + 1);
            if (!$organization = $this->objectEntityService->getOrganizationObject($id)) {
                if (!$organization = $this->objectEntityService->getObjectByUri($uri)) {
                    $organization = $this->isResource($uri);
                }
            }
            // Invalidate all changed & related organizations from cache
            if (!empty($organization)) {
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

        $id = substr($url, strrpos($url, '/') + 1);
        if (!$organization = $this->objectEntityService->getOrganizationObject($id)) {
            if (!$organization = $this->objectEntityService->getObjectByUri($url)) {
                $organization = $this->isResource($url);
            }
        }
        if (!empty($organization)) {
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
