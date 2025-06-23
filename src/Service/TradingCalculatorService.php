<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\LotGroupRepository;
use App\Repository\DofusCharacterRepository;
use App\Repository\LotUnitRepository;
use App\Repository\MarketWatchRepository;
use App\Enum\LotStatus;

class TradingCalculatorService
{
    public function __construct(
        private LotGroupRepository $lotGroupRepository,
        private DofusCharacterRepository $characterRepository,
        private LotUnitRepository $lotUnitRepository,
        private MarketWatchRepository $marketWatchRepository
    ) {
    }

    public function getUserTradingStats(User $user): array
    {
        $characters = $this->characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        if (empty($characters)) {
            return $this->getEmptyStats();
        }

        // Statistiques globales existantes
        $globalStats = $this->getGlobalStats($user);
        
        // Nouvelles données enrichies
        $topItems = $this->getEnhancedTopItems($user);
        $characterBreakdown = $this->getCharacterBreakdown($user);
        $weeklyData = $this->getWeeklyPerformance($user);
        $monthlyData = $this->getMonthlyPerformance($user);
        $marketData = $this->getMarketSurveillanceStats($user);
        $trends = $this->getTrendAnalysis($user);
        $recommendations = $this->getRecommendations($user);

        return [
            'global' => $globalStats,
            'topItems' => $topItems,
            'charactersCount' => count($characters),
            
            // Nouvelles données
            'characterBreakdown' => $characterBreakdown,
            'weeklyData' => $weeklyData,
            'monthlyData' => $monthlyData,
            'marketData' => $marketData,
            'weekTrend' => $trends['weekTrend'],
            'bestDay' => $trends['bestDay'],
            'recommendations' => $recommendations
        ];
    }

    private function getGlobalStats(User $user): array
    {
        return $this->lotGroupRepository->getUserGlobalStats($user);
    }

    private function getEnhancedTopItems(User $user): array
    {
        return $this->lotGroupRepository->createQueryBuilder('lg')
            ->select([
                'i.name as itemName',
                'COUNT(lg.id) as lotsCount',
                'SUM((lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize) as totalProfit',
                'AVG((lg.sellPricePerLot - lg.buyPricePerLot) / lg.buyPricePerLot * 100) as roi',
                'SUM(lg.buyPricePerLot * lg.lotSize) as totalInvested'
            ])
            ->join('lg.item', 'i')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->groupBy('i.id', 'i.name')
            ->orderBy('totalProfit', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    private function getCharacterBreakdown(User $user): array
    {
        // Récupérer les entités complètes avec leurs relations
        $characters = $this->characterRepository->createQueryBuilder('c')
            ->leftJoin('c.lotGroups', 'lg')
            ->join('c.server', 's')
            ->join('c.classe', 'cl')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($characters as $character) {
            $totalLots = 0;
            $activeLots = 0;
            $totalProfit = 0;
            $totalInvested = 0;

            foreach ($character->getLotGroups() as $lot) {
                $totalLots++;
                
                if ($lot->getStatus() === LotStatus::AVAILABLE) {
                    $activeLots++;
                    $totalInvested += $lot->getBuyPricePerLot() * $lot->getLotSize();
                    $totalProfit += ($lot->getSellPricePerLot() - $lot->getBuyPricePerLot()) * $lot->getLotSize();
                } elseif ($lot->getStatus() === LotStatus::SOLD) {
                    $totalProfit += ($lot->getSellPricePerLot() - $lot->getBuyPricePerLot()) * $lot->getLotSize();
                }
            }

            $roi = $totalInvested > 0 ? ($totalProfit / $totalInvested) * 100 : 0;

            $result[] = [
                'id' => $character->getId(),
                'name' => $character->getName(),
                'server' => $character->getServer()->getName(),
                'classe' => [
                    'name' => $character->getClasse()->getName(),
                    'imgUrl' => $character->getClasse()->getImgUrl()
                ],
                'totalLots' => $totalLots,
                'activeLots' => $activeLots,
                'totalProfit' => $totalProfit,
                'totalInvested' => $totalInvested,
                'roi' => $roi
            ];
        }

        return $result;
    }

    private function getWeeklyPerformance(User $user): array
    {
        $weekAgo = new \DateTime('-7 days');
        
        return $this->lotUnitRepository->createQueryBuilder('lu')
            ->select([
                'COUNT(lu.id) as sales',
                'SUM((lu.actualSellPrice - lg.buyPricePerLot) * lu.quantitySold) as profit'
            ])
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lu.soldAt >= :weekAgo')
            ->setParameter('user', $user)
            ->setParameter('weekAgo', $weekAgo)
            ->getQuery()
            ->getOneOrNullResult() ?: ['sales' => 0, 'profit' => 0];
    }

    private function getMonthlyPerformance(User $user): array
    {
        $monthAgo = new \DateTime('-30 days');
        
        return $this->lotUnitRepository->createQueryBuilder('lu')
            ->select([
                'COUNT(lu.id) as sales',
                'SUM((lu.actualSellPrice - lg.buyPricePerLot) * lu.quantitySold) as profit'
            ])
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lu.soldAt >= :monthAgo')
            ->setParameter('user', $user)
            ->setParameter('monthAgo', $monthAgo)
            ->getQuery()
            ->getOneOrNullResult() ?: ['sales' => 0, 'profit' => 0];
    }

    private function getMarketSurveillanceStats(User $user): array
    {
        $watchedItems = $this->marketWatchRepository->createQueryBuilder('mw')
            ->select('COUNT(DISTINCT i.id) as watchedItems')
            ->join('mw.item', 'i')
            ->join('mw.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $activeWatches = $this->marketWatchRepository->createQueryBuilder('mw')
            ->select('COUNT(mw.id) as activeWatches')
            ->join('mw.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $recentUpdates = $this->marketWatchRepository->createQueryBuilder('mw')
            ->select('COUNT(mw.id) as recentUpdates')
            ->join('mw.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('mw.updatedAt >= :yesterday')
            ->setParameter('user', $user)
            ->setParameter('yesterday', new \DateTime('-24 hours'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'watchedItems' => $watchedItems,
            'activeWatches' => $activeWatches,
            'recentUpdates' => $recentUpdates,
            'opportunities' => 0 // À implémenter selon ta logique
        ];
    }

    private function getTrendAnalysis(User $user): array
    {
        // Comparer cette semaine vs semaine précédente
        $thisWeek = $this->getWeeklyPerformance($user);
        
        $twoWeeksAgo = new \DateTime('-14 days');
        $oneWeekAgo = new \DateTime('-7 days');
        
        $lastWeek = $this->lotUnitRepository->createQueryBuilder('lu')
            ->select('SUM((lu.actualSellPrice - lg.buyPricePerLot) * lu.quantitySold) as profit')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lu.soldAt >= :twoWeeksAgo')
            ->andWhere('lu.soldAt < :oneWeekAgo')
            ->setParameter('user', $user)
            ->setParameter('twoWeeksAgo', $twoWeeksAgo)
            ->setParameter('oneWeekAgo', $oneWeekAgo)
            ->getQuery()
            ->getSingleScalarResult() ?: 0;

        $weekTrend = $lastWeek > 0 
            ? (($thisWeek['profit'] - $lastWeek) / $lastWeek) * 100 
            : 0;

        // Meilleur jour (exemple basique)
        $bestDay = [
            'profit' => $thisWeek['profit'] / 7, // Moyenne par jour
            'date' => new \DateTime() // À améliorer avec une vraie logique
        ];

        return [
            'weekTrend' => $weekTrend,
            'bestDay' => $bestDay
        ];
    }

    private function getRecommendations(User $user): array
    {
        // Suggestions basées sur les items les plus rentables
        $suggestedItems = $this->lotGroupRepository->createQueryBuilder('lg')
            ->select('i.name')
            ->join('lg.item', 'i')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lg.status = :sold')
            ->setParameter('user', $user)
            ->setParameter('sold', LotStatus::SOLD)
            ->groupBy('i.name')
            ->orderBy('AVG((lg.sellPricePerLot - lg.buyPricePerLot) / lg.buyPricePerLot)', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        return [
            'suggestedItems' => array_column($suggestedItems, 'name')
        ];
    }

    private function getEmptyStats(): array
    {
        return [
            'global' => [
                'totalLots' => 0,
                'availableLots' => 0,
                'soldLots' => 0,
                'investedAmount' => 0,
                'realizedProfit' => 0,
                'potentialProfit' => 0
            ],
            'topItems' => [],
            'charactersCount' => 0,
            'characterBreakdown' => [],
            'weeklyData' => ['sales' => 0, 'profit' => 0],
            'monthlyData' => ['sales' => 0, 'profit' => 0],
            'marketData' => ['watchedItems' => 0, 'activeWatches' => 0, 'recentUpdates' => 0, 'opportunities' => 0],
            'weekTrend' => 0,
            'bestDay' => ['profit' => 0, 'date' => new \DateTime()],
            'recommendations' => ['suggestedItems' => []]
        ];
    }
}