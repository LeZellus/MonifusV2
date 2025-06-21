<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\DofusCharacter;
use App\Repository\DofusCharacterRepository;
use App\Repository\TradingProfileRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CharacterSelectionService
{
    public function __construct(
        private RequestStack $requestStack,
        private DofusCharacterRepository $characterRepository,
        private TradingProfileRepository $profileRepository
    ) {
    }

    public function getSelectedCharacter(User $user): ?DofusCharacter
    {
        $session = $this->requestStack->getSession();
        $characterId = $session->get('selected_character_id');
        $selectedProfileId = $session->get('selected_profile_id');

        if (!$characterId) {
            // Si un profil spécifique est sélectionné, chercher dans ce profil
            if ($selectedProfileId) {
                $firstCharacter = $this->getFirstCharacterForProfile($selectedProfileId, $user);
                if ($firstCharacter) {
                    $this->setSelectedCharacter($firstCharacter);
                    return $firstCharacter;
                }
                // Le profil sélectionné est vide, retourner null
                return null;
            }
            
            // Sinon auto-sélectionner le premier personnage de l'utilisateur
            $firstCharacter = $this->getFirstCharacterForUser($user);
            if ($firstCharacter) {
                $this->setSelectedCharacter($firstCharacter);
                // Sauvegarder aussi le profil correspondant
                $session->set('selected_profile_id', $firstCharacter->getTradingProfile()->getId());
                return $firstCharacter;
            }
            return null;
        }

        // Vérifier que le personnage appartient à l'utilisateur
        $character = $this->characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('c.id = :id')
            ->andWhere('tp.user = :user')
            ->setParameter('id', $characterId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$character) {
            // Le personnage n'existe plus ou n'appartient pas à l'utilisateur
            $session->remove('selected_character_id');
            
            // Si un profil spécifique est sélectionné, chercher dans ce profil
            if ($selectedProfileId) {
                $firstCharacter = $this->getFirstCharacterForProfile($selectedProfileId, $user);
                if ($firstCharacter) {
                    $this->setSelectedCharacter($firstCharacter);
                    return $firstCharacter;
                }
                return null;
            }
            
            // Sinon chercher le premier personnage disponible
            $firstCharacter = $this->getFirstCharacterForUser($user);
            if ($firstCharacter) {
                $this->setSelectedCharacter($firstCharacter);
                $session->set('selected_profile_id', $firstCharacter->getTradingProfile()->getId());
                return $firstCharacter;
            }
        }

        return $character;
    }

    public function setSelectedCharacter(DofusCharacter $character): void
    {
        $session = $this->requestStack->getSession();
        $session->set('selected_character_id', $character->getId());
        // Synchroniser le profil sélectionné
        $session->set('selected_profile_id', $character->getTradingProfile()->getId());
    }

    public function getUserCharacters(User $user): array
    {
        return $this->characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function getFirstCharacterForUser(User $user): ?DofusCharacter
    {
        return $this->characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function getFirstCharacterForProfile(int $profileId, User $user): ?DofusCharacter
    {
        return $this->characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.id = :profileId')
            ->andWhere('tp.user = :user')
            ->setParameter('profileId', $profileId)
            ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}