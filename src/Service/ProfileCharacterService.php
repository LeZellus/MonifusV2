<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\DofusCharacter;
use App\Repository\DofusCharacterRepository;
use App\Repository\TradingProfileRepository;
use App\Repository\LotGroupRepository;
use App\Repository\MarketWatchRepository;
use App\Repository\LotUnitRepository;
use App\Enum\LotStatus;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service fusionné gérant la sélection des profils et personnages
 * Remplace CharacterSelectionService et ProfileSelectorService
 */
class ProfileCharacterService
{
    public function __construct(
        private RequestStack $requestStack,
        private DofusCharacterRepository $characterRepository,
        private TradingProfileRepository $profileRepository,
        private LotGroupRepository $lotGroupRepository,
        private MarketWatchRepository $marketWatchRepository,
        private LotUnitRepository $lotUnitRepository,
        private CacheInterface $cache
    ) {
    }

    // =================== CHARACTER SELECTION METHODS ===================

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

        // 2. Fallback: vérifier s'il y a un profil sélectionné en session
        $selectedProfileId = $session->get('selected_profile_id');
        if ($selectedProfileId) {
            $firstCharacterForProfile = $this->getFirstCharacterForProfile($selectedProfileId, $user);
            if ($firstCharacterForProfile) {
                $this->setSelectedCharacter($firstCharacterForProfile);
                return $firstCharacterForProfile;
            }
            return null;
        }

        // 3. Fallback: cookie de dernière session
        $lastCharacterId = $request ? $request->cookies->get("last_character_user_{$user->getId()}") : null;
        if ($lastCharacterId) {
            $lastCharacter = $this->findCharacterById($lastCharacterId, $user);
            if ($lastCharacter) {
                $this->setSelectedCharacter($lastCharacter);
                return $lastCharacter;
            }
        }

        // 4. Fallback final: premier personnage disponible
        $firstCharacter = $this->getFirstCharacterForUser($user);
        if ($firstCharacter) {
            $this->setSelectedCharacter($firstCharacter);
            return $firstCharacter;
        }

        return null;
    }

    public function setSelectedCharacter(DofusCharacter $character): void
    {
        $session = $this->requestStack->getSession();
        $session->set('selected_character_id', $character->getId());
        $session->set('selected_profile_id', $character->getTradingProfile()->getId());
    }

    public function getUserCharacters(User $user): array
    {
        $cacheKey = "user_characters_{$user->getId()}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(300);

            return $this->characterRepository->createQueryBuilder('c')
                ->join('c.tradingProfile', 'tp')
                ->leftJoin('c.classe', 'cl')
                ->leftJoin('c.server', 's')
                ->addSelect('cl', 's')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->orderBy('tp.name', 'ASC')
                ->addOrderBy('c.name', 'ASC')
                ->getQuery()
                ->getResult();
        });
    }

    // =================== PROFILE SELECTOR METHODS ===================

    public function getSelectorData(User $user): array
    {
        // Récupérer les profils avec leurs personnages
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

        // Récupérer le personnage sélectionné une seule fois
        $selectedCharacter = $this->getSelectedCharacter($user);
        $currentProfile = $this->getCurrentProfile($user, $profiles, $selectedCharacter);

        // Calculer les stats pour chaque personnage
        if (!empty($profiles)) {
            $characterCounts = $this->getCharacterCounts($user);

            foreach ($profiles as $profile) {
                foreach ($profile->getDofusCharacters() as $character) {
                    $characterId = $character->getId();
                    $character->tempLotsAvailable = $characterCounts[$characterId]['lots_available'] ?? 0;
                    $character->tempSalesTransactions = $characterCounts[$characterId]['sales_transactions'] ?? 0;
                    $character->tempWatchesCount = $characterCounts[$characterId]['watches'] ?? 0;
                }
            }

            // Aussi appliquer les stats au personnage sélectionné s'il existe
            if ($selectedCharacter) {
                $selectedCharacterId = $selectedCharacter->getId();
                $selectedCharacter->tempLotsAvailable = $characterCounts[$selectedCharacterId]['lots_available'] ?? 0;
                $selectedCharacter->tempSalesTransactions = $characterCounts[$selectedCharacterId]['sales_transactions'] ?? 0;
                $selectedCharacter->tempWatchesCount = $characterCounts[$selectedCharacterId]['watches'] ?? 0;
            }
        }

        return [
            'profiles' => $profiles,
            'selectedCharacter' => $selectedCharacter,
            'currentProfile' => $currentProfile,
        ];
    }

    // =================== CACHE MANAGEMENT METHODS ===================

    public function invalidateUserCache(User $user): void
    {
        try {
            $userCacheKey = "user_characters_{$user->getId()}";
            $characterCountsKey = "character_counts_user_{$user->getId()}";

            $this->cache->delete($userCacheKey);
            $this->cache->delete($characterCountsKey);

            // Invalider les caches individuels des personnages
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
        }
    }

    public function forceInvalidateCountsCache(?User $user): void
    {
        if (!$user) {
            return;
        }

        try {
            $characterCountsKey = "character_counts_user_{$user->getId()}";
            $this->cache->delete($characterCountsKey);
        } catch (\Exception $e) {
            // Ignorer les erreurs
        }
    }

    // =================== PRIVATE HELPER METHODS ===================

    private function findCharacterById(int $characterId, User $user): ?DofusCharacter
    {
        $cacheKey = "character_{$characterId}_user_{$user->getId()}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($characterId, $user) {
            $item->expiresAfter(300);

            try {
                return $this->characterRepository->createQueryBuilder('c')
                    ->join('c.tradingProfile', 'tp')
                    ->leftJoin('c.classe', 'cl')
                    ->leftJoin('c.server', 's')
                    ->addSelect('cl', 's')
                    ->where('c.id = :id')
                    ->andWhere('tp.user = :user')
                    ->setParameter('id', $characterId)
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getOneOrNullResult();
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    private function getCurrentProfile(User $user, array $profiles, ?DofusCharacter $selectedCharacter = null)
    {
        $session = $this->requestStack->getSession();
        $selectedProfileId = $session->get('selected_profile_id');

        // Si un profil est explicitement sélectionné en session
        if ($selectedProfileId) {
            foreach ($profiles as $profile) {
                if ($profile->getId() === $selectedProfileId) {
                    return $profile;
                }
            }

            // Si le profil n'existe plus, nettoyer la session
            $session->remove('selected_profile_id');
            $session->remove('selected_character_id');
        }

        // Fallback : utiliser le profil du personnage sélectionné (déjà récupéré)
        if ($selectedCharacter) {
            return $selectedCharacter->getTradingProfile();
        }

        // Fallback final : premier profil de l'utilisateur
        return $profiles[0] ?? null;
    }

    private function getFirstCharacterForUser(User $user): ?DofusCharacter
    {
        return $this->characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->leftJoin('c.classe', 'cl')
            ->leftJoin('c.server', 's')
            ->addSelect('cl', 's')
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
            ->leftJoin('c.classe', 'cl')
            ->leftJoin('c.server', 's')
            ->addSelect('cl', 's')
            ->where('tp.id = :profileId')
            ->andWhere('tp.user = :user')
            ->setParameter('profileId', $profileId)
            ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function getCharacterCounts(User $user): array
    {
        $cacheKey = "character_counts_user_{$user->getId()}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(60);

            // Une seule requête optimisée pour récupérer tous les compteurs
            $query = "
                SELECT
                    c.id as character_id,
                    COALESCE(lot_available.lot_count, 0) as lots_available,
                    COALESCE(lot_sold.lot_count, 0) as lots_sold,
                    COALESCE(watch_count.watch_count, 0) as watches,
                    COALESCE(sale_count.sale_count, 0) as sales_transactions
                FROM dofus_character c
                INNER JOIN trading_profile tp ON c.trading_profile_id = tp.id
                LEFT JOIN (
                    SELECT lg.dofus_character_id, COUNT(*) as lot_count
                    FROM lot_group lg
                    WHERE lg.status = 'available'
                    GROUP BY lg.dofus_character_id
                ) lot_available ON c.id = lot_available.dofus_character_id
                LEFT JOIN (
                    SELECT lg.dofus_character_id, COUNT(*) as lot_count
                    FROM lot_group lg
                    WHERE lg.status = 'sold'
                    GROUP BY lg.dofus_character_id
                ) lot_sold ON c.id = lot_sold.dofus_character_id
                LEFT JOIN (
                    SELECT mw.dofus_character_id, COUNT(*) as watch_count
                    FROM market_watch mw
                    GROUP BY mw.dofus_character_id
                ) watch_count ON c.id = watch_count.dofus_character_id
                LEFT JOIN (
                    SELECT lg.dofus_character_id, COUNT(*) as sale_count
                    FROM lot_unit lu
                    INNER JOIN lot_group lg ON lu.lot_group_id = lg.id
                    WHERE lu.sold_at IS NOT NULL
                    GROUP BY lg.dofus_character_id
                ) sale_count ON c.id = sale_count.dofus_character_id
                WHERE tp.user_id = :user_id
            ";

            $stmt = $this->lotGroupRepository->getEntityManager()->getConnection()->prepare($query);
            $result = $stmt->executeQuery(['user_id' => $user->getId()])->fetchAllAssociative();

            // Traiter les résultats de la requête optimisée
            $counts = [];

            foreach ($result as $row) {
                $characterId = (int)$row['character_id'];
                $counts[$characterId] = [
                    'lots_available' => (int)$row['lots_available'],
                    'lots_sold' => (int)$row['lots_sold'],
                    'sales_transactions' => (int)$row['sales_transactions'],
                    'watches' => (int)$row['watches']
                ];
            }

            return $counts;
        });
    }
}