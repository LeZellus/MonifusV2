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

        // Statistiques globales avec différenciation investi en cours/total
        $globalStats = $this->getEnhancedGlobalStats($user);
        
        // Données enrichies
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
            'characterBreakdown' => $characterBreakdown,
            'weeklyData' => $weeklyData,
            'monthlyData' => $monthlyData,
            'marketData' => $marketData,
            'weekTrend' => $trends['weekTrend'],
            'bestDay' => $trends['bestDay'],
            'recommendations' => $recommendations
        ];
    }

    /**
     * Stats globales enrichies avec différenciation investi en cours/total
     */
    private function getEnhancedGlobalStats(User $user): array
    {
        $baseStats = $this->lotGroupRepository->getUserGlobalStats($user);
        
        // Calculs ROI enrichis
        $totalProfit = $baseStats['realizedProfit'] + $baseStats['potentialProfit'];
        
        // ROI sur investi total (le vrai ROI global)
        $roiOnTotal = $baseStats['totalInvestment'] > 0 
            ? ($totalProfit / $baseStats['totalInvestment']) * 100 
            : 0;
        
        // ROI sur investi en cours (pour les lots actifs)
        $roiOnCurrent = $baseStats['currentInvestment'] > 0 
            ? ($baseStats['potentialProfit'] / $baseStats['currentInvestment']) * 100 
            : null;

        // Efficacité du capital
        $capitalEfficiency = $baseStats['totalInvestment'] > 0 
            ? $baseStats['realizedProfit'] / $baseStats['totalInvestment'] 
            : 0;

        // Profit moyen par lot - EN COURS (lots disponibles)
        $avgProfitPerLotCurrent = $baseStats['availableLots'] > 0 
            ? $baseStats['potentialProfit'] / $baseStats['availableLots'] 
            : 0;

        // Profit moyen par lot - TOTAL (lots vendus)
        $avgProfitPerLotTotal = $baseStats['soldLots'] > 0 
            ? $baseStats['realizedProfit'] / $baseStats['soldLots'] 
            : 0;

        return array_merge($baseStats, [
            'roiOnTotal' => $roiOnTotal,
            'roiOnCurrent' => $roiOnCurrent,
            'capitalEfficiency' => $capitalEfficiency,
            'avgProfitPerLotCurrent' => $avgProfitPerLotCurrent,
            'avgProfitPerLotTotal' => $avgProfitPerLotTotal,
            // Legacy
            'avgProfitPerLot' => $avgProfitPerLotTotal
        ]);
    }

    private function getEnhancedTopItems(User $user): array
    {
        return $this->lotGroupRepository->getTopItemsByProfit($user, 5);
    }

    private function getCharacterBreakdown(User $user): array
    {
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
            $currentInvestment = 0;
            $totalInvestment = 0;

            foreach ($character->getLotGroups() as $lot) {
                $totalLots++;
                $lotInvestment = $lot->getBuyPricePerLot() * $lot->getLotSize();
                $totalInvestment += $lotInvestment;
                
                if ($lot->getStatus() === LotStatus::AVAILABLE) {
                    $activeLots++;
                    $currentInvestment += $lotInvestment;
                    $totalProfit += ($lot->getSellPricePerLot() - $lot->getBuyPricePerLot()) * $lot->getLotSize();
                }
            }

            // ROI sur investissement total du personnage
            $roi = $totalInvestment > 0 ? ($totalProfit / $totalInvestment) * 100 : 0;

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
                'currentInvestment' => $currentInvestment,
                'totalInvestment' => $totalInvestment,
                'roi' => $roi
            ];
        }

        return $result;
    }

    private function getWeeklyPerformance(User $user): array
    {
        $weekAgo = new \DateTime('-7 days');
        
        $result = $this->lotUnitRepository->createQueryBuilder('lu')
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
            ->getOneOrNullResult();

        return $result ?: ['sales' => 0, 'profit' => 0];
    }

    private function getMonthlyPerformance(User $user): array
    {
        $monthAgo = new \DateTime('-30 days');
        
        $result = $this->lotUnitRepository->createQueryBuilder('lu')
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
            ->getOneOrNullResult();

        return $result ?: ['sales' => 0, 'profit' => 0];
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
            'watchedItems' => $watchedItems ?: 0,
            'activeWatches' => $activeWatches ?: 0,
            'recentUpdates' => $recentUpdates ?: 0,
            'opportunities' => 0 // À implémenter selon ta logique
        ];
    }

    private function getTrendAnalysis(User $user): array
    {
        // Comparer cette semaine vs semaine précédente
        $thisWeek = $this->getWeeklyPerformance($user);
        
        $twoWeeksAgo = new \DateTime('-14 days');
        $oneWeekAgo = new \DateTime('-7 days');
        
        $lastWeekProfit = $this->lotUnitRepository->createQueryBuilder('lu')
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

        $weekTrend = $lastWeekProfit > 0 
            ? (($thisWeek['profit'] - $lastWeekProfit) / $lastWeekProfit) * 100 
            : 0;

        // Meilleur jour (simplifié)
        $bestDay = [
            'profit' => $thisWeek['profit'],
            'date' => new \DateTime()
        ];

        return [
            'weekTrend' => $weekTrend,
            'bestDay' => $bestDay
        ];
    }

    private function getRecommendations(User $user): array
    {
        $recommendations = [];
        $stats = $this->lotGroupRepository->getUserGlobalStats($user);

        // Recommandations basées sur les métriques
        if ($stats['currentInvestment'] == 0 && $stats['totalLots'] == 0) {
            $recommendations[] = [
                'type' => 'action',
                'title' => 'Commencer le trading',
                'message' => 'Créez vos premiers lots pour commencer à trader',
                'priority' => 'high'
            ];
        }

        if ($stats['availableLots'] > 20) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Beaucoup de lots en attente',
                'message' => 'Vous avez ' . $stats['availableLots'] . ' lots à vendre',
                'priority' => 'medium'
            ];
        }

        $roiOnTotal = $stats['totalInvestment'] > 0 
            ? (($stats['realizedProfit'] + $stats['potentialProfit']) / $stats['totalInvestment']) * 100 
            : 0;

        if ($roiOnTotal < 10 && $stats['totalInvestment'] > 0) {
            $recommendations[] = [
                'type' => 'tip',
                'title' => 'ROI faible',
                'message' => 'Votre ROI global est de ' . number_format($roiOnTotal, 1) . '%. Analysez vos stratégies.',
                'priority' => 'low'
            ];
        }

        return $recommendations;
    }

    private function getEmptyStats(): array
    {
        return [
            'global' => [
                'totalLots' => 0,
                'availableLots' => 0,
                'soldLots' => 0,
                'currentInvestment' => 0,
                'totalInvestment' => 0,
                'investedAmount' => 0, // legacy
                'realizedProfit' => 0,
                'potentialProfit' => 0,
                'roiOnTotal' => 0,
                'roiOnCurrent' => null,
                'capitalEfficiency' => 0,
                'avgProfitPerLot' => 0
            ],
            'topItems' => [],
            'charactersCount' => 0,
            'characterBreakdown' => [],
            'weeklyData' => ['sales' => 0, 'profit' => 0],
            'monthlyData' => ['sales' => 0, 'profit' => 0],
            'marketData' => ['watchedItems' => 0, 'activeWatches' => 0, 'opportunities' => 0],
            'weekTrend' => 0,
            'bestDay' => ['profit' => 0, 'date' => new \DateTime()],
            'recommendations' => []
        ];
    }
}