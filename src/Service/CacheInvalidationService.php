<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Contracts\Cache\CacheInterface;

class CacheInvalidationService
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function invalidateUserStats(User $user): void
    {
        $this->cache->delete("user_trading_stats_{$user->getId()}");
    }

    public function markUserActivity(User $user): void
    {
        $cacheItem = $this->cache->getItem("user_last_activity_{$user->getId()}");
        $cacheItem->set(time());
        $cacheItem->expiresAfter(300); // 5 minutes
        $this->cache->save($cacheItem);
    }

    public function invalidateUserStatsAndMarkActivity(User $user): void
    {
        $this->invalidateUserStats($user);
        $this->markUserActivity($user);
    }
}