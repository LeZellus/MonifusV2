<?php
// src/Service/ProfileSelectorService.php

namespace App\Service;

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
        $profiles = $this->profileRepository->findBy(['user' => $user]);
        $selectedCharacter = $this->characterService->getSelectedCharacter($user);
        
        // Déterminer le profil actuel en priorité par session, sinon par personnage sélectionné
        $currentProfile = $this->getCurrentProfile($user, $selectedCharacter);

        // Calculer les stats pour chaque personnage avec requêtes optimisées
        $characterCounts = $this->getCharacterCounts($user);
        
        foreach ($profiles as $profile) {
            foreach ($profile->getDofusCharacters() as $character) {
                $characterId = $character->getId();
                $character->tempLotsCount = $characterCounts[$characterId]['lots'] ?? 0;
                $character->tempWatchesCount = $characterCounts[$characterId]['watches'] ?? 0;
            }
        }

        return [
            'profiles' => $profiles,
            'selectedCharacter' => $selectedCharacter,
            'currentProfile' => $currentProfile,
        ];
    }

    private function getCurrentProfile(UserInterface $user, $selectedCharacter)
    {
        $session = $this->requestStack->getSession();
        $selectedProfileId = $session->get('selected_profile_id');

        // Si un profil est explicitement sélectionné en session
        if ($selectedProfileId) {
            $profile = $this->profileRepository->findOneBy([
                'id' => $selectedProfileId,
                'user' => $user
            ]);
            
            if ($profile) {
                return $profile;
            }
            
            // Si le profil n'existe plus, nettoyer la session
            $session->remove('selected_profile_id');
        }

        // Fallback : utiliser le profil du personnage sélectionné
        if ($selectedCharacter) {
            return $selectedCharacter->getTradingProfile();
        }

        // Fallback final : premier profil de l'utilisateur
        $userProfiles = $this->profileRepository->findBy(['user' => $user]);
        return $userProfiles[0] ?? null;
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
}