<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @Author Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 * @deprecated
 */
class FunctionService
{
    private CacheInterface $cache;
    public array $removeResultFromCache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
        $this->removeResultFromCache = [];
    }

    /**
     * Removes all responses saved for the given ObjectEntity from the cache. Also does the same for all parent objects of the given object.
     * Always use $this->functionService->removeResultFromCache = []; before using this function to reset the list of objects that already got removed from cache.
     * If this function is called multiple times in a row it might be better to do this before a loop or starting a recursive function.
     *
     * @param ObjectEntity      $objectEntity The ObjectEntity to remove saved results for from the cache.
     * @param SymfonyStyle|null $io           If SymfonyStyle $io is given, will also send a text message for each removed parent object to $io.
     *
     * @throws InvalidArgumentException
     *
     * @return bool
     * @deprecated
     */
    public function removeResultFromCache(ObjectEntity $objectEntity, ?SymfonyStyle $io = null): bool
    {
        if (!in_array($objectEntity->getId()->toString(), $this->removeResultFromCache)) {
            if (!$objectEntity->getSubresourceOf()->isEmpty() ||
                !$objectEntity->getAllSubresources(new ArrayCollection())->isEmpty()) {
                $this->removeResultFromCache[] = $objectEntity->getId()->toString();
                $this->removeParentResultsFromCache($objectEntity, $io);
                $this->removeChildResultsFromCache($objectEntity, $io);
            }

            return $this->cache->invalidateTags(['object_'.base64_encode($objectEntity->getId()->toString())]) && $this->cache->commit();
        }

        return false;
    }

    /**
     * Removes all responses saved for the parent objects of the given ObjectEntity from the cache. Followup function of removeResultFromCache().
     * And will also loop back to removeResultFromCache() function if needed.
     * Always use $this->functionService->removeResultFromCache = []; before using removeResultFromCache() function to reset the list of objects that already got removed from cache.
     * If removeResultFromCache() function is called multiple times in a row it might be better to do this before a loop or starting a recursive function.
     *
     * @param ObjectEntity      $objectEntity The ObjectEntity to remove saved results of parent objects for from the cache.
     * @param SymfonyStyle|null $io           If SymfonyStyle $io is given, will also send a text message for each removed parent object to $io.
     *
     * @throws InvalidArgumentException
     *
     * @return void
     * @deprecated
     */
    private function removeParentResultsFromCache(ObjectEntity $objectEntity, ?SymfonyStyle $io)
    {
        foreach ($objectEntity->getSubresourceOf() as $parentValue) {
            $parentObject = $parentValue->getObjectEntity();
            if (!$this->removeResultFromCache($parentObject, $io) && $io !== null) {
                $io->text("Successfully removed parent Object (parent of Object: {$objectEntity->getId()->toString()}) with id: {$parentObject->getId()->toString()} (of Entity type: {$parentObject->getEntity()->getName()}) from cache");
            }
        }
    }

    /**
     * Removes all responses saved for the child objects of the given ObjectEntity from the cache. Followup function of removeResultFromCache().
     * And will also loop back to removeResultFromCache() function if needed.
     * Always use $this->functionService->removeResultFromCache = []; before using removeResultFromCache() function to reset the list of objects that already got removed from cache.
     * If removeResultFromCache() function is called multiple times in a row it might be better to do this before a loop or starting a recursive function.
     *
     * @param ObjectEntity      $objectEntity The ObjectEntity to remove saved results of child objects for from the cache.
     * @param SymfonyStyle|null $io           If SymfonyStyle $io is given, will also send a text message for each removed parent object to $io.
     *
     * @throws InvalidArgumentException
     *
     * @return void
     * @deprecated
     */
    private function removeChildResultsFromCache(ObjectEntity $objectEntity, ?SymfonyStyle $io)
    {
        foreach ($objectEntity->getAllSubresources(new ArrayCollection()) as $childObject) {
            if (!$this->removeResultFromCache($childObject, $io) && $io !== null) {
                $io->text("Successfully removed child Object (child of Object: {$objectEntity->getId()->toString()}) with id: {$childObject->getId()->toString()} (of Entity type: {$childObject->getEntity()->getName()}) from cache");
            }
        }
    }
}
