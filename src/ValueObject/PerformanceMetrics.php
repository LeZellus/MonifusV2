<?php

namespace App\ValueObject;

/**
 * Value Object pour les métriques de performance temporelle
 */
readonly class PerformanceMetrics
{
    public function __construct(
        public array $weeklyData,
        public array $monthlyData,
        public float $weekTrend,
        public string $bestDay,
        public int $bestDayProfit
    ) {}

    public function hasPositiveWeekTrend(): bool
    {
        return $this->weekTrend > 0;
    }

    public function getFormattedTrend(): string
    {
        $sign = $this->weekTrend >= 0 ? '+' : '';
        return $sign . round($this->weekTrend, 2) . '%';
    }

    public function getTrendIndicator(): string
    {
        return $this->hasPositiveWeekTrend() ? '📈' : '📉';
    }

    public function getWeeklyAverage(): float
    {
        if (empty($this->weeklyData)) {
            return 0;
        }

        $total = array_sum(array_column($this->weeklyData, 'profit'));
        return $total / count($this->weeklyData);
    }

    public function getMonthlyAverage(): float
    {
        if (empty($this->monthlyData)) {
            return 0;
        }

        $total = array_sum(array_column($this->monthlyData, 'profit'));
        return $total / count($this->monthlyData);
    }
}