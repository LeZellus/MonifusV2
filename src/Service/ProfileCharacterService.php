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

        $currentProfile = $this->getCurrentProfile($user, $profiles);
        $selectedCharacter = $this->getSelectedCharacter($user);

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

    public function forceInvalidateCountsCache(User $user): void
    {
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

    private function getCurrentProfile(User $user, array $profiles)
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

        // Fallback : utiliser le profil du personnage sélectionné
        $selectedCharacter = $this->getSelectedCharacter($user);
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

    private function getCharacterCounts(User $user): array
    {
        $cacheKey = "character_counts_user_{$user->getId()}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(60);

            // Get lot counts by status
            $lotCounts = $this->lotGroupRepository->createQueryBuilder('lg')
                ->select('c.id as character_id, lg.status, COUNT(lg.id) as lot_count')
                ->join('lg.dofusCharacter', 'c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->groupBy('c.id', 'lg.status')
                ->getQuery()
                ->getArrayResult();

            // Get market watch counts
            $watchCounts = $this->marketWatchRepository->createQueryBuilder('mw')
                ->select('c.id as character_id, COUNT(mw.id) as watch_count')
                ->join('mw.dofusCharacter', 'c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->groupBy('c.id')
                ->getQuery()
                ->getArrayResult();

            // Get sale counts
            $saleCounts = $this->lotUnitRepository->createQueryBuilder('lu')
                ->select('c.id as character_id, COUNT(lu.id) as sale_count')
                ->join('lu.lotGroup', 'lg')
                ->join('lg.dofusCharacter', 'c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->groupBy('c.id')
                ->getQuery()
                ->getArrayResult();

            // Combine results
            $counts = [];

            foreach ($lotCounts as $row) {
                $characterId = $row['character_id'];
                $status = $row['status']->value;
                $count = (int)$row['lot_count'];

                if (!isset($counts[$characterId])) {
                    $counts[$characterId] = [
                        'lots_available' => 0,
                        'lots_sold' => 0,
                        'sales_transactions' => 0,
                        'watches' => 0
                    ];
                }

                if ($status === 'available') {
                    $counts[$characterId]['lots_available'] = $count;
                } elseif ($status === 'sold') {
                    $counts[$characterId]['lots_sold'] = $count;
                }
            }

            foreach ($watchCounts as $row) {
                $characterId = $row['character_id'];
                if (!isset($counts[$characterId])) {
                    $counts[$characterId] = [
                        'lots_available' => 0,
                        'lots_sold' => 0,
                        'sales_transactions' => 0,
                        'watches' => 0
                    ];
                }
                $counts[$characterId]['watches'] = (int)$row['watch_count'];
            }

            foreach ($saleCounts as $row) {
                $characterId = $row['character_id'];
                if (!isset($counts[$characterId])) {
                    $counts[$characterId] = [
                        'lots_available' => 0,
                        'lots_sold' => 0,
                        'sales_transactions' => 0,
                        'watches' => 0
                    ];
                }
                $counts[$characterId]['sales_transactions'] = (int)$row['sale_count'];
            }

            return $counts;
        });
    }
}