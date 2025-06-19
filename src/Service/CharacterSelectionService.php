<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\DofusCharacter;
use App\Repository\DofusCharacterRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CharacterSelectionService
{
    public function __construct(
        private RequestStack $requestStack,
        private DofusCharacterRepository $characterRepository
    ) {
    }

    public function getSelectedCharacter(User $user): ?DofusCharacter
    {
        $session = $this->requestStack->getSession();
        $characterId = $session->get('selected_character_id');

        if (!$characterId) {
            // Auto-sélectionner le premier personnage de l'utilisateur
            $firstCharacter = $this->getFirstCharacterForUser($user);
            if ($firstCharacter) {
                $this->setSelectedCharacter($firstCharacter);
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
            $firstCharacter = $this->getFirstCharacterForUser($user);
            if ($firstCharacter) {
                $this->setSelectedCharacter($firstCharacter);
                return $firstCharacter;
            }
        }

        return $character;
    }

    public function setSelectedCharacter(DofusCharacter $character): void
    {
        $session = $this->requestStack->getSession();
        $session->set('selected_character_id', $character->getId());
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
}