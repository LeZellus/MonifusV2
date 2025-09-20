<?php
// src/Twig/ProfileSelectorExtension.php

namespace App\Twig;

use App\Service\ProfileSelectorService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class ProfileSelectorExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private ProfileSelectorService $profileSelectorService,
        private Security $security,
        private CacheInterface $cache
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        
        // Don't load any data for non-authenticated users
        if (!$user) {
            return [];
        }

        $cacheKey = 'profile_selector_data_user_' . $user->getId();
        
        $selectorData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(600); // Cache for 10 minutes
            return $this->profileSelectorService->getSelectorData($user);
        });

        return [
            'selectorData' => $selectorData
        ];
    }
}