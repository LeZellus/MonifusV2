<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\MarketWatchRepository;
use App\Repository\LotGroupRepository;

/**
 * Service dédié à l'analyse du marché et surveillance
 */
class MarketAnalysisService
{
    public function __construct(
        private MarketWatchRepository $marketWatchRepository,
        private LotGroupRepository $lotGroupRepository
    ) {}

    public function getMarketSurveillanceStats(User $user): array
    {
        $totalWatched = $this->getTotalWatchedItems($user);
        $avgPrices = $this->getAveragePricesTracked($user);
        $priceAlerts = $this->getPriceAlerts($user);

        return [
            'totalItemsWatched' => $totalWatched,
            'averagePricesTracked' => $avgPrices,
            'priceAlerts' => $priceAlerts,
            'marketCoverage' => $this->calculateMarketCoverage($user)
        ];
    }

    public function getTopItems(User $user, int $limit = 5): array
    {
        // Profit = (actualSellPrice - costPerSaleLot) * quantitySold
        // costPerSaleLot = (buyPricePerLot / buyUnit) * saleUnit
        return $this->lotGroupRepository->createQueryBuilder('lg')
            ->select('
                i.name,
                SUM((lu.actualSellPrice - (lg.buyPricePerLot / lg.buyUnit * lg.saleUnit)) * lu.quantitySold) as totalProfit,
                COUNT(lu.id) as transactions,
                AVG(lu.actualSellPrice) as avgSellPrice
            ')
            ->join('lg.item', 'i')
            ->join('lg.lotUnits', 'lu')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->groupBy('i.id')
            ->orderBy('totalProfit', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getCharacterBreakdown(User $user): array
    {
        return $this->lotGroupRepository->createQueryBuilder('lg')
            ->select('
                c.name as characterName,
                s.name as serverName,
                COUNT(CASE WHEN lg.status = \'available\' THEN 1 END) as activeLots,
                COUNT(CASE WHEN lg.status = \'sold\' THEN 1 END) as soldLots,
                SUM(CASE WHEN lg.status = \'available\' THEN lg.buyPricePerLot * lg.lotSize ELSE 0 END) as activeInvestment,
                SUM(CASE WHEN lg.status = \'sold\' THEN
                    (SELECT SUM(lu2.actualSellPrice * lu2.quantitySold) FROM App:LotUnit lu2 WHERE lu2.lotGroup = lg) -
                    (SELECT SUM(lg2.buyPricePerLot * lu3.quantitySold) FROM App:LotGroup lg2 JOIN App:LotUnit lu3 WITH lu3.lotGroup = lg2 WHERE lg2.id = lg.id)
                ELSE 0 END) as realizedProfit
            ')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.server', 's')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->groupBy('c.id')
            ->getQuery()
            ->getArrayResult();
    }

    private function getTotalWatchedItems(User $user): int
    {
        return (int) $this->marketWatchRepository->createQueryBuilder('mw')
            ->select('COUNT(DISTINCT i.id)')
            ->join('mw.item', 'i')
            ->join('mw.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getAveragePricesTracked(User $user): array
    {
        $result = $this->marketWatchRepository->createQueryBuilder('mw')
            ->select('AVG(mw.price) as avgPrice, COUNT(mw.id) as totalObservations')
            ->join('mw.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('mw.observedAt >= :weekAgo')
            ->setParameter('user', $user)
            ->setParameter('weekAgo', new \DateTime('-7 days'))
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'average' => (int) ($result['avgPrice'] ?? 0),
            'observations' => (int) ($result['totalObservations'] ?? 0)
        ];
    }

    private function getPriceAlerts(User $user): int
    {
        // Simuler des alertes de prix (à implémenter selon la logique métier)
        return 0;
    }

    private function calculateMarketCoverage(User $user): float
    {
        $watchedItems = $this->getTotalWatchedItems($user);
        $tradedItems = (int) $this->lotGroupRepository->createQueryBuilder('lg')
            ->select('COUNT(DISTINCT i.id)')
            ->join('lg.item', 'i')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $tradedItems > 0 ? ($watchedItems / $tradedItems) * 100 : 0;
    }
}