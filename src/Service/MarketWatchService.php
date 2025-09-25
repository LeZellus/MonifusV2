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
        // Récupérer l'utilisateur avant la suppression avec protection des relations
        $user = null;
        $character = $marketWatch->getDofusCharacter();
        if ($character) {
            $profile = $character->getTradingProfile();
            if ($profile) {
                $user = $profile->getUser();
            }
        }

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

    public function calculateMarketWatchStats(?DofusCharacter $character): array
    {
        if (!$character) {
            return [
                'total_items_watched' => 0,
                'total_observations' => 0,
                'average_price' => 0,
                'price_range' => 0
            ];
        }

        $itemsData = $this->getItemsDataForCharacter($character);

        $totalItems = count($itemsData);
        $totalObservations = 0;
        $totalPrice = 0;
        $prices = [];

        foreach ($itemsData as $itemData) {
            $observations = $itemData['total_observations'] ?? 0;
            $avgPrice = $itemData['average_price'] ?? 0;

            $totalObservations += $observations;
            if ($avgPrice > 0) {
                $totalPrice += $avgPrice;
                $prices[] = $avgPrice;
            }
        }

        $averagePrice = $totalItems > 0 ? $totalPrice / $totalItems : 0;
        $priceRange = 0;

        if (!empty($prices)) {
            $priceRange = max($prices) - min($prices);
        }

        return [
            'total_items_watched' => $totalItems,
            'total_observations' => $totalObservations,
            'average_price' => $averagePrice,
            'price_range' => $priceRange
        ];
    }

    private function invalidateCaches($user): void
    {
        if ($user) {
            $this->profileCharacterService->forceInvalidateCountsCache($user);
            $this->cacheInvalidation->invalidateUserStatsAndMarkActivity($user);
        }
    }
}