<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\DofusCharacter;
use App\Repository\DofusCharacterRepository;
use App\Repository\TradingProfileRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CharacterSelectionService
{
    public function __construct(
        private RequestStack $requestStack,
        private DofusCharacterRepository $characterRepository,
        private TradingProfileRepository $profileRepository,
        private CacheInterface $cache
    ) {
    }

    public function getSelectedCharacter(User $user): ?DofusCharacter
    {
        $session = $this->requestStack->getSession();
        $request = $this->requestStack->getCurrentRequest();
        
        $characterId = $session->get('selected_character_id');
        $selectedProfileId = $session->get('selected_profile_id');

        // Vérifier d'abord si le personnage en session existe
        if ($characterId) {
            $character = $this->findCharacterById($characterId, $user);
            if ($character) {
                return $character;
            }
            $session->remove('selected_character_id');
        }

        // Essayer le cookie
        $lastCharacterId = $request ? $request->cookies->get("last_character_user_{$user->getId()}") : null;
        
        if ($lastCharacterId) {
            $lastCharacter = $this->findCharacterById($lastCharacterId, $user);
            if ($lastCharacter) {
                $this->setSelectedCharacter($lastCharacter);
                return $lastCharacter;
            }
        }

        // Fallback : profil sélectionné
        if ($selectedProfileId) {
            $firstCharacter = $this->getFirstCharacterForProfile($selectedProfileId, $user);
            if ($firstCharacter) {
                $this->setSelectedCharacter($firstCharacter);
                return $firstCharacter;
            }
            return null;
        }
        
        // Fallback final
        $firstCharacter = $this->getFirstCharacterForUser($user);
        if ($firstCharacter) {
            $this->setSelectedCharacter($firstCharacter);
            return $firstCharacter;
        }
        
        return null;
    }

    private function findCharacterById(int $characterId, User $user): ?DofusCharacter
    {
        $cacheKey = "character_{$characterId}_user_{$user->getId()}";
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($characterId, $user) {
            $item->expiresAfter(300); // Cache for 5 minutes
            
            return $this->characterRepository->createQueryBuilder('c')
                ->join('c.tradingProfile', 'tp')
                ->where('c.id = :id')
                ->andWhere('tp.user = :user')
                ->setParameter('id', $characterId)
                ->setParameter('user', $user)
                ->getQuery()
                ->getOneOrNullResult();
        });
    }

    public function setSelectedCharacter(DofusCharacter $character): void
    {
        $session = $this->requestStack->getSession();
        $session->set('selected_character_id', $character->getId());
        $session->set('selected_profile_id', $character->getTradingProfile()->getId());
        
        // Le cookie sera géré automatiquement par l'EventSubscriber
    }

    public function getUserCharacters(User $user): array
    {
        $cacheKey = "user_characters_{$user->getId()}";
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(600); // Cache for 10 minutes
            
            return $this->characterRepository->createQueryBuilder('c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->orderBy('c.name', 'ASC')
                ->getQuery()
                ->getResult();
        });
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