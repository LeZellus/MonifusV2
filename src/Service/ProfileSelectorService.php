<?php
// src/Service/ProfileSelectorService.php

namespace App\Service;

use App\Repository\TradingProfileRepository;
use App\Repository\DofusCharacterRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ProfileSelectorService
{
    public function __construct(
        private TradingProfileRepository $profileRepository,
        private CharacterSelectionService $characterService,
        private RequestStack $requestStack
    ) {}

    public function getSelectorData(UserInterface $user): array
    {
        $profiles = $this->profileRepository->findBy(['user' => $user]);
        $selectedCharacter = $this->characterService->getSelectedCharacter($user);
        
        // Déterminer le profil actuel en priorité par session, sinon par personnage sélectionné
        $currentProfile = $this->getCurrentProfile($user, $selectedCharacter);

        // Calculer les stats pour chaque personnage
        foreach ($profiles as $profile) {
            foreach ($profile->getDofusCharacters() as $character) {
                $character->tempLotsCount = $character->getLotGroups()->count();
                $character->tempWatchesCount = $character->getMarketWatches()->count();
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
}