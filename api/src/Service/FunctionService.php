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
            $this->cache->invalidateTags(['organization']);
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
            $item->tag('organization');

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
