<?php

namespace App\Service;

use App\Entity\MarketWatch;
use App\Entity\DofusCharacter;
use App\Entity\Item;
use App\Repository\MarketWatchRepository;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service gérant la logique métier des observations de marché
 */
class MarketWatchService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MarketWatchRepository $marketWatchRepository,
        private ItemRepository $itemRepository,
        private CacheInvalidationService $cacheInvalidation,
        private ProfileCharacterService $profileCharacterService
    ) {
    }

    public function getItemsDataForCharacter(?DofusCharacter $character, ?string $searchQuery = null): array
    {
        return $character
            ? $this->marketWatchRepository->getItemsDataWithStats($character, $searchQuery ?? '')
            : [];
    }

    public function createMarketWatch(
        DofusCharacter $character,
        MarketWatch $marketWatch,
        ?Item $preselectedItem = null,
        ?int $itemId = null
    ): bool {
        // Déterminer l'item à observer
        if ($preselectedItem) {
            $marketWatch->setItem($preselectedItem);
        } elseif ($itemId) {
            $item = $this->itemRepository->find($itemId);
            if (!$item) {
                return false;
            }
            $marketWatch->setItem($item);
        } else {
            return false;
        }

        // S'assurer que le personnage est managé par Doctrine
        $managedCharacter = $this->em->getRepository(DofusCharacter::class)->find($character->getId());
        if (!$managedCharacter) {
            return false;
        }

        $marketWatch->setDofusCharacter($managedCharacter);
        $this->em->persist($marketWatch);
        $this->em->flush();

        $this->invalidateCaches($managedCharacter->getTradingProfile()->getUser());
        return true;
    }

    public function updateMarketWatch(MarketWatch $marketWatch): void
    {
        $this->em->flush();
        $this->invalidateCaches($marketWatch->getDofusCharacter()->getTradingProfile()->getUser());
    }

    public function deleteMarketWatch(MarketWatch $marketWatch): void
    {
        $user = $marketWatch->getDofusCharacter()->getTradingProfile()->getUser();

        $this->em->remove($marketWatch);
        $this->em->flush();

        $this->invalidateCaches($user);
    }

    public function deleteAllObservationsForItem(DofusCharacter $character, int $itemId): int
    {
        $observations = $this->marketWatchRepository->findPriceHistoryForItem($character, $itemId);

        if (empty($observations)) {
            return 0;
        }

        foreach ($observations as $observation) {
            $this->em->remove($observation);
        }
        $this->em->flush();

        $this->invalidateCaches($character->getTradingProfile()->getUser());
        return count($observations);
    }

    public function getPriceHistoryForItem(DofusCharacter $character, int $itemId): array
    {
        return $this->marketWatchRepository->findPriceHistoryForItem($character, $itemId);
    }

    public function calculatePriceAverages(array $priceHistory): array
    {
        return $this->marketWatchRepository->calculatePriceAverages($priceHistory);
    }

    public function canUserAccessMarketWatch(MarketWatch $marketWatch, DofusCharacter $userCharacter): bool
    {
        return $marketWatch->getDofusCharacter()->getId() === $userCharacter->getId();
    }

    private function invalidateCaches($user): void
    {
        $this->profileCharacterService->forceInvalidateCountsCache($user);
        $this->cacheInvalidation->invalidateUserStatsAndMarkActivity($user);
    }
}