<?php

namespace App\Service;

use App\Entity\User;
use App\ValueObject\TradingStats;
use App\Repository\LotGroupRepository;
use App\Repository\DofusCharacterRepository;

/**
 * Service dédié aux statistiques de trading de base
 */
class TradingStatsService
{
    public function __construct(
        private LotGroupRepository $lotGroupRepository,
        private DofusCharacterRepository $characterRepository
    ) {}

    public function calculateUserStats(User $user): TradingStats
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

        return new TradingStats(
            totalInvestment: $baseStats['totalInvestment'],
            currentInvestment: $baseStats['currentInvestment'],
            realizedProfit: $baseStats['realizedProfit'],
            potentialProfit: $baseStats['potentialProfit'],
            roiOnTotal: $roiOnTotal,
            roiOnCurrent: $roiOnCurrent,
            totalLots: $baseStats['totalLots'],
            activeLots: $baseStats['activeLots'],
            soldLots: $baseStats['soldLots'],
            totalTransactions: $baseStats['totalTransactions']
        );
    }

    public function getUserCharactersCount(User $user): int
    {
        return count($this->characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult());
    }

    public function hasUserCharacters(User $user): bool
    {
        return $this->getUserCharactersCount($user) > 0;
    }

    public function getEmptyStats(): TradingStats
    {
        return new TradingStats(
            totalInvestment: 0,
            currentInvestment: 0,
            realizedProfit: 0,
            potentialProfit: 0,
            roiOnTotal: 0,
            roiOnCurrent: null,
            totalLots: 0,
            activeLots: 0,
            soldLots: 0,
            totalTransactions: 0
        );
    }
}