<?php


namespace App\Service;


use App\Entity\ObjectEntity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface as CacheInterface;

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
     * Performs the organization function
     *
     * @param ObjectEntity $objectEntity
     * @param string $uri
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function createOrganization(ObjectEntity $objectEntity, string $uri)
    {
        $objectEntity->setOrganization($uri);
        $this->cache->invalidateTags(['organization']);

        return $objectEntity;
    }

    /**
     * Gets an organization for an url from cache or url, depending on cache
     *
     * @TODO: move this elsewhere.
     * @param $url
     * @return array
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getOrganizationFromCache($url): array
    {
        $item = $this->cache->getItem('organizations_'.md5("$url"));
        if($item->isHit()){
            return $item->get();
        }

        if ($organization = $this->isResource($url)) {
            $item->set($organization);
            $item->tag('organization');
            return $organization;
        }
        return [];
    }

    /**
     * IsResource function from commongroundService without caching.
     *
     * @TODO: Make cache settable in CGB and remove.
     * @param $url
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