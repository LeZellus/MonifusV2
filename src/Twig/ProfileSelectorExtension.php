<?php
// src/Twig/ProfileSelectorExtension.php

namespace App\Twig;

use App\Service\ProfileSelectorService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class ProfileSelectorExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private ProfileSelectorService $profileSelectorService,
        private Security $security,
        private CacheInterface $cache,
        private RequestStack $requestStack
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        // Don't load any data for non-authenticated users
        if (!$user) {
            return [];
        }

        // Utiliser un cache-busting basé sur un timestamp de session
        $request = $this->requestStack->getCurrentRequest();
        $lastUpdate = 0;

        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $lastUpdate = $session->get('profile_selector_last_update', 0);
        }

        $cacheKey = 'profile_selector_data_user_' . $user->getId() . '_' . $lastUpdate;

        $selectorData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(60); // Cache très court: 1 minute seulement
            return $this->profileSelectorService->getSelectorData($user);
        });

        return [
            'selectorData' => $selectorData
        ];
    }
}