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
        $currentTime = time();

        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $lastUpdate = $session->get('profile_selector_last_update', 0);
        }

        // Si le timestamp est récent (moins de 2 minutes), ne pas utiliser le cache
        $bypassCache = ($currentTime - $lastUpdate) < 120;

        if ($bypassCache) {
            // Données fraîches pour s'assurer que les changements récents sont visibles
            $selectorData = $this->profileSelectorService->getSelectorData($user);

            // Validation supplémentaire : s'assurer que les données sont cohérentes
            if ($session && $session->get('selected_profile_id')) {
                $selectedProfileId = $session->get('selected_profile_id');
                $currentProfile = $selectorData['currentProfile'];

                // Si le profil courant ne correspond pas au profil sélectionné en session,
                // forcer un refresh en supprimant les variables de session problématiques
                if (!$currentProfile || $currentProfile->getId() !== $selectedProfileId) {
                    // Nettoyer et forcer un nouveau calcul
                    $session->remove('selected_character_id');
                    $selectorData = $this->profileSelectorService->getSelectorData($user);
                }
            }

            return [
                'selectorData' => $selectorData
            ];
        }

        $cacheKey = 'profile_selector_data_user_' . $user->getId() . '_' . $lastUpdate;

        $selectorData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(300); // Cache normal: 5 minutes
            return $this->profileSelectorService->getSelectorData($user);
        });

        return [
            'selectorData' => $selectorData
        ];
    }
}