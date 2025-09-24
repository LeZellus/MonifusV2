<?php

namespace App\ValueObject;

/**
 * Value Object représentant les statistiques de trading d'un utilisateur
 */
readonly class TradingStats
{
    public function __construct(
        public int $totalInvestment,
        public int $currentInvestment,
        public int $realizedProfit,
        public int $potentialProfit,
        public float $roiOnTotal,
        public ?float $roiOnCurrent,
        public int $totalLots,
        public int $activeLots,
        public int $soldLots,
        public int $totalTransactions
    ) {}

    public function getTotalProfit(): int
    {
        return $this->realizedProfit + $this->potentialProfit;
    }

    public function hasPositiveRoi(): bool
    {
        return $this->roiOnTotal > 0;
    }

    public function getCompletionRate(): float
    {
        return $this->totalLots > 0 ? ($this->soldLots / $this->totalLots) * 100 : 0;
    }

    public function getAverageInvestmentPerLot(): float
    {
        return $this->totalLots > 0 ? $this->totalInvestment / $this->totalLots : 0;
    }

    public function isActiveTrader(): bool
    {
        return $this->activeLots > 0 || $this->totalTransactions > 10;
    }
}