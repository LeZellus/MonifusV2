<?php
// src/Service/ProfileSelectorService.php

namespace App\Service;

use App\Entity\DofusCharacter;
use App\Repository\TradingProfileRepository;
use App\Repository\DofusCharacterRepository;
use App\Repository\LotGroupRepository;
use App\Repository\MarketWatchRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ProfileSelectorService
{
    public function __construct(
        private TradingProfileRepository $profileRepository,
        private CharacterSelectionService $characterService,
        private RequestStack $requestStack,
        private LotGroupRepository $lotGroupRepository,
        private MarketWatchRepository $marketWatchRepository,
        private CacheInterface $cache
    ) {}

    public function getSelectorData(UserInterface $user): array
    {
        // Récupérer les profils avec leurs personnages en une seule requête
        $profiles = $this->profileRepository->createQueryBuilder('tp')
            ->leftJoin('tp.dofusCharacters', 'dc')
            ->leftJoin('dc.classe', 'cl')
            ->leftJoin('dc.server', 's')
            ->addSelect('dc', 'cl', 's')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->orderBy('tp.name', 'ASC')
            ->addOrderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Déterminer d'abord le profil courant selon la session
        $currentProfile = $this->getCurrentProfile($user, null, $profiles);

        // Puis obtenir le personnage sélectionné (qui respectera le profil sélectionné)
        $selectedCharacter = $this->characterService->getSelectedCharacter($user);

        // Calculer les stats pour chaque personnage (si il y a des personnages)
        if (!empty($profiles)) {
            $characterCounts = $this->getCharacterCounts($user);

            foreach ($profiles as $profile) {
                foreach ($profile->getDofusCharacters() as $character) {
                    $characterId = $character->getId();
                    $character->tempLotsCount = $characterCounts[$characterId]['lots'] ?? 0;
                    $character->tempWatchesCount = $characterCounts[$characterId]['watches'] ?? 0;
                }
            }
        }

        return [
            'profiles' => $profiles,
            'selectedCharacter' => $selectedCharacter,
            'currentProfile' => $currentProfile,
        ];
    }

    private function getCurrentProfile(UserInterface $user, ?DofusCharacter $selectedCharacter, array $profiles)
    {
        $session = $this->requestStack->getSession();
        $selectedProfileId = $session->get('selected_profile_id');

        // Si un profil est explicitement sélectionné en session, TOUJOURS le respecter
        if ($selectedProfileId) {
            // Chercher dans les profils déjà récupérés pour éviter une nouvelle requête
            foreach ($profiles as $profile) {
                if ($profile->getId() === $selectedProfileId) {
                    // IMPORTANT: retourner ce profil même s'il est vide
                    return $profile;
                }
            }

            // Si le profil n'existe plus, nettoyer la session
            $session->remove('selected_profile_id');
            $session->remove('selected_character_id');
        }

        // Fallback : utiliser le profil du personnage sélectionné (seulement si aucun profil explicite)
        if ($selectedCharacter) {
            return $selectedCharacter->getTradingProfile();
        }

        // Fallback final : premier profil de l'utilisateur
        return $profiles[0] ?? null;
    }

    private function getCharacterCounts(UserInterface $user): array
    {
        $cacheKey = "character_counts_user_{$user->getId()}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(300); // Cache for 5 minutes

            // Get lot counts for all user characters in one query
            $lotCounts = $this->lotGroupRepository->createQueryBuilder('lg')
                ->select('c.id as character_id, COUNT(lg.id) as lot_count')
                ->join('lg.dofusCharacter', 'c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->groupBy('c.id')
                ->getQuery()
                ->getArrayResult();

            // Get market watch counts for all user characters in one query
            $watchCounts = $this->marketWatchRepository->createQueryBuilder('mw')
                ->select('c.id as character_id, COUNT(mw.id) as watch_count')
                ->join('mw.dofusCharacter', 'c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->groupBy('c.id')
                ->getQuery()
                ->getArrayResult();

            // Combine results
            $counts = [];

            foreach ($lotCounts as $row) {
                $counts[$row['character_id']]['lots'] = (int)$row['lot_count'];
            }

            foreach ($watchCounts as $row) {
                $counts[$row['character_id']]['watches'] = (int)$row['watch_count'];
            }

            return $counts;
        });
    }

    /**
     * Invalide les caches liés au sélecteur de profil
     */
    public function invalidateCache(UserInterface $user): void
    {
        try {
            $characterCountsKey = "character_counts_user_{$user->getId()}";
            $this->cache->delete($characterCountsKey);

            // Invalider aussi le cache global de l'extension Twig
            $selectorKey = "profile_selector_data_user_{$user->getId()}";
            $this->cache->delete($selectorKey);
        } catch (\Exception $e) {
            // En cas d'erreur, ne pas faire échouer l'opération principale
        }
    }
}