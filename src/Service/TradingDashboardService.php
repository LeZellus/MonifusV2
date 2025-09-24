<?php

namespace App\Service;

use App\Entity\User;

/**
 * Service orchestrateur pour le dashboard de trading
 * Remplace l'ancien TradingCalculatorService en tant que façade simplifiée
 */
class TradingDashboardService
{
    public function __construct(
        private TradingStatsService $statsService,
        private PerformanceAnalysisService $performanceService,
        private TradingRecommendationService $recommendationService,
        private MarketAnalysisService $marketService
    ) {}

    public function getDashboardData(User $user): array
    {
        // Vérifier si l'utilisateur a des personnages
        if (!$this->statsService->hasUserCharacters($user)) {
            return $this->getEmptyDashboard();
        }

        // Calculer les statistiques de base
        $stats = $this->statsService->calculateUserStats($user);

        // Analyser les performances
        $performance = $this->performanceService->analyzeUserPerformance($user);

        // Obtenir les données marché
        $marketData = $this->marketService->getMarketSurveillanceStats($user);
        $topItems = $this->marketService->getTopItems($user);
        $characterBreakdown = $this->marketService->getCharacterBreakdown($user);

        // Générer les recommandations
        $recommendations = $this->recommendationService->generateRecommendations($user, $stats);

        return [
            'global' => [
                'totalInvestment' => $stats->totalInvestment,
                'currentInvestment' => $stats->currentInvestment,
                'realizedProfit' => $stats->realizedProfit,
                'potentialProfit' => $stats->potentialProfit,
                'roiOnTotal' => $stats->roiOnTotal,
                'roiOnCurrent' => $stats->roiOnCurrent,
                'totalLots' => $stats->totalLots,
                'activeLots' => $stats->activeLots,
                'soldLots' => $stats->soldLots,
                'totalTransactions' => $stats->totalTransactions,
            ],
            'topItems' => $topItems,
            'charactersCount' => $this->statsService->getUserCharactersCount($user),
            'characterBreakdown' => $characterBreakdown,
            'weeklyData' => $performance->weeklyData,
            'monthlyData' => $performance->monthlyData,
            'marketData' => $marketData,
            'weekTrend' => $performance->weekTrend,
            'bestDay' => $performance->bestDay,
            'recommendations' => $recommendations,
            // Métriques calculées
            'isActiveTrader' => $stats->isActiveTrader(),
            'completionRate' => $stats->getCompletionRate(),
            'averageInvestmentPerLot' => $stats->getAverageInvestmentPerLot(),
            'performanceTrend' => $performance->getFormattedTrend(),
            'trendIndicator' => $performance->getTrendIndicator(),
        ];
    }

    private function getEmptyDashboard(): array
    {
        $emptyStats = $this->statsService->getEmptyStats();

        return [
            'global' => [
                'totalInvestment' => 0,
                'currentInvestment' => 0,
                'realizedProfit' => 0,
                'potentialProfit' => 0,
                'roiOnTotal' => 0,
                'roiOnCurrent' => null,
                'totalLots' => 0,
                'activeLots' => 0,
                'soldLots' => 0,
                'totalTransactions' => 0,
            ],
            'topItems' => [],
            'charactersCount' => 0,
            'characterBreakdown' => [],
            'weeklyData' => [],
            'monthlyData' => [],
            'marketData' => [
                'totalItemsWatched' => 0,
                'averagePricesTracked' => ['average' => 0, 'observations' => 0],
                'priceAlerts' => 0,
                'marketCoverage' => 0
            ],
            'weekTrend' => 0,
            'bestDay' => 'N/A',
            'recommendations' => [],
            'isActiveTrader' => false,
            'completionRate' => 0,
            'averageInvestmentPerLot' => 0,
            'performanceTrend' => '+0%',
            'trendIndicator' => '📊',
        ];
    }
}