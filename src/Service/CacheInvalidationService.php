<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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
        $this->cache->get(
            "user_last_activity_{$user->getId()}",
            function (ItemInterface $item) {
                $item->expiresAfter(300); // 5 minutes
                return time();
            }
        );
    }

    public function invalidateUserStatsAndMarkActivity(User $user): void
    {
        $this->invalidateUserStats($user);
        $this->markUserActivity($user);
    }
}