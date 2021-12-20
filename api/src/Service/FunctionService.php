<?php


namespace App\Service;


use App\Entity\ObjectEntity;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;

class FunctionService
{
    public CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function createOrganization(ObjectEntity $objectEntity, string $uri)
    {
        $objectEntity->setOrganization($uri);
        $this->cache->clear('organizations');

    }
}