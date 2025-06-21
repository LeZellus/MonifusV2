<?php
// src/Service/ProfileSelectorService.php

namespace App\Service;

use App\Repository\TradingProfileRepository;
use App\Repository\DofusCharacterRepository;
use Symfony\Component\Security\Core\User\UserInterface;

class ProfileSelectorService
{
    public function __construct(
        private TradingProfileRepository $profileRepository,
        private CharacterSelectionService $characterService
    ) {}

    public function getSelectorData(UserInterface $user): array
    {
        $profiles = $this->profileRepository->findBy(['user' => $user]);
        $selectedCharacter = $this->characterService->getSelectedCharacter($user);
        $currentProfile = $selectedCharacter?->getTradingProfile();

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
}