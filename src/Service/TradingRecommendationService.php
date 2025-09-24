<?php

namespace App\Service;

use App\Entity\User;
use App\ValueObject\TradingStats;
use App\Repository\LotGroupRepository;
use App\Repository\MarketWatchRepository;

/**
 * Service dédié aux recommandations de trading
 */
class TradingRecommendationService
{
    public function __construct(
        private LotGroupRepository $lotGroupRepository,
        private MarketWatchRepository $marketWatchRepository
    ) {}

    public function generateRecommendations(User $user, TradingStats $stats): array
    {
        $recommendations = [];

        // Recommandations basées sur les performances
        $recommendations = array_merge($recommendations, $this->getPerformanceRecommendations($stats));

        // Recommandations basées sur le portefeuille
        $recommendations = array_merge($recommendations, $this->getPortfolioRecommendations($user, $stats));

        // Recommandations de diversification
        $recommendations = array_merge($recommendations, $this->getDiversificationRecommendations($user));

        // Limiter à 3 recommandations max
        return array_slice($recommendations, 0, 3);
    }

    private function getPerformanceRecommendations(TradingStats $stats): array
    {
        $recommendations = [];

        if (!$stats->hasPositiveRoi()) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'title' => 'ROI négatif détecté',
                'message' => 'Analysez vos lots non rentables et ajustez vos prix de vente.',
                'action' => 'Revoir la stratégie de pricing'
            ];
        }

        if ($stats->getCompletionRate() < 50) {
            $recommendations[] = [
                'type' => 'efficiency',
                'priority' => 'medium',
                'title' => 'Taux de vente faible',
                'message' => sprintf('Seulement %.1f%% de vos lots sont vendus.', $stats->getCompletionRate()),
                'action' => 'Ajuster les prix ou diversifier'
            ];
        }

        return $recommendations;
    }

    private function getPortfolioRecommendations(User $user, TradingStats $stats): array
    {
        $recommendations = [];

        // Si beaucoup d'investissement immobilisé
        $immobilizationRate = $stats->totalInvestment > 0
            ? ($stats->currentInvestment / $stats->totalInvestment) * 100
            : 0;

        if ($immobilizationRate > 70) {
            $recommendations[] = [
                'type' => 'liquidity',
                'priority' => 'medium',
                'title' => 'Liquidités immobilisées',
                'message' => sprintf('%.1f%% de votre investissement est immobilisé.', $immobilizationRate),
                'action' => 'Vendre des lots anciens à prix réduit'
            ];
        }

        // Si moyenne par lot très élevée
        if ($stats->getAverageInvestmentPerLot() > 1000000) { // 1M kamas
            $recommendations[] = [
                'type' => 'diversification',
                'priority' => 'low',
                'title' => 'Investissement concentré',
                'message' => 'Vos lots ont une valeur unitaire élevée.',
                'action' => 'Diversifier avec des lots plus petits'
            ];
        }

        return $recommendations;
    }

    private function getDiversificationRecommendations(User $user): array
    {
        $recommendations = [];

        // Vérifier la diversification des items
        $itemStats = $this->lotGroupRepository->createQueryBuilder('lg')
            ->select('COUNT(DISTINCT i.id) as uniqueItems, COUNT(lg.id) as totalLots')
            ->join('lg.item', 'i')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lg.status = :available')
            ->setParameter('user', $user)
            ->setParameter('available', 'available')
            ->getQuery()
            ->getOneOrNullResult();

        if ($itemStats && $itemStats['totalLots'] > 10) {
            $diversificationRate = $itemStats['uniqueItems'] / $itemStats['totalLots'];

            if ($diversificationRate < 0.3) { // Moins de 30% de diversification
                $recommendations[] = [
                    'type' => 'diversification',
                    'priority' => 'medium',
                    'title' => 'Portfolio peu diversifié',
                    'message' => sprintf('Vous tradez %d items différents sur %d lots.',
                        $itemStats['uniqueItems'], $itemStats['totalLots']),
                    'action' => 'Explorer de nouveaux marchés'
                ];
            }
        }

        return $recommendations;
    }
}