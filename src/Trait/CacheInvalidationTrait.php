<?php

namespace App\Trait;

use App\Service\ProfileCharacterService;
use App\Service\CacheInvalidationService;

/**
 * Trait pour gérer l'invalidation des caches dans les contrôleurs
 * Évite la répétition du pattern d'invalidation de cache
 */
trait CacheInvalidationTrait
{
    /**
     * Invalide tous les caches liés à un utilisateur
     * Pattern utilisé après création/modification/suppression d'entités
     */
    protected function invalidateUserCaches(
        $user,
        ProfileCharacterService $profileCharacterService,
        CacheInvalidationService $cacheInvalidationService
    ): void {
        // Invalider le cache des compteurs pour mise à jour immédiate
        $profileCharacterService->forceInvalidateCountsCache($user);

        // Invalider le cache des stats utilisateur
        $cacheInvalidationService->invalidateUserStatsAndMarkActivity($user);
    }

    /**
     * Invalide les caches et force la mise à jour du sélecteur de profil
     * Utilisé après des opérations qui modifient la structure des profils/personnages
     */
    protected function invalidateUserCachesAndUpdateSelector(
        $user,
        ProfileCharacterService $profileCharacterService,
        CacheInvalidationService $cacheInvalidationService,
        ?\Symfony\Component\HttpFoundation\RequestStack $requestStack = null
    ): void {
        // Invalidations standard
        $this->invalidateUserCaches($user, $profileCharacterService, $cacheInvalidationService);

        // Forcer l'invalidation du cache de l'extension Twig
        if ($requestStack) {
            $request = $requestStack->getCurrentRequest();
            if ($request && $request->hasSession()) {
                $session = $request->getSession();
                $session->set('profile_selector_last_update', time());
            }
        }
    }
}