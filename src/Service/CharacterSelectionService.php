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

        // 1. Essayer le personnage en session (priorité absolue)
        $characterId = $session->get('selected_character_id');
        if ($characterId) {
            $character = $this->findCharacterById($characterId, $user);
            if ($character) {
                return $character;
            }
            // Nettoyer la session si le personnage n'existe plus
            $session->remove('selected_character_id');
            $session->remove('selected_profile_id');
        }

        // 2. Fallback: cookie de dernière session
        $lastCharacterId = $request ? $request->cookies->get("last_character_user_{$user->getId()}") : null;
        if ($lastCharacterId) {
            $lastCharacter = $this->findCharacterById($lastCharacterId, $user);
            if ($lastCharacter) {
                $this->setSelectedCharacter($lastCharacter);
                return $lastCharacter;
            }
        }

        // 3. Fallback: vérifier s'il y a un profil sélectionné en session
        $session = $this->requestStack->getSession();
        $selectedProfileId = $session->get('selected_profile_id');

        if ($selectedProfileId) {
            // Si un profil est sélectionné, chercher un personnage dans ce profil uniquement
            $firstCharacterForProfile = $this->getFirstCharacterForProfile($selectedProfileId, $user);
            if ($firstCharacterForProfile) {
                $this->setSelectedCharacter($firstCharacterForProfile);
                return $firstCharacterForProfile;
            }
            // Si le profil sélectionné n'a pas de personnage, retourner null
            return null;
        }

        // 4. Fallback final: premier personnage disponible (seulement si aucun profil spécifique sélectionné)
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

            try {
                return $this->characterRepository->createQueryBuilder('c')
                    ->join('c.tradingProfile', 'tp')
                    ->where('c.id = :id')
                    ->andWhere('tp.user = :user')
                    ->setParameter('id', $characterId)
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getOneOrNullResult();
            } catch (\Exception $e) {
                // En cas d'erreur de base de données, retourner null
                // et laisser les fallbacks prendre le relais
                return null;
            }
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
            $item->expiresAfter(300); // Réduit à 5 minutes pour plus de réactivité

            return $this->characterRepository->createQueryBuilder('c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->orderBy('tp.name', 'ASC')  // Ordre par profil puis personnage
                ->addOrderBy('c.name', 'ASC')
                ->getQuery()
                ->getResult();
        });
    }

    /**
     * Invalide le cache des personnages d'un utilisateur
     * Appelé automatiquement lors de modifications (création/suppression personnages)
     */
    public function invalidateUserCache(User $user): void
    {
        try {
            $userCacheKey = "user_characters_{$user->getId()}";
            $this->cache->delete($userCacheKey);

            // Invalider aussi les caches individuels des personnages de cet utilisateur
            $characters = $this->characterRepository->createQueryBuilder('c')
                ->select('c.id')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getArrayResult();

            foreach ($characters as $char) {
                $charCacheKey = "character_{$char['id']}_user_{$user->getId()}";
                $this->cache->delete($charCacheKey);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, ne pas faire échouer l'opération principale
            // L'invalidation du cache n'est pas critique
        }
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