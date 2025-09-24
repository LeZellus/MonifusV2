<?php

namespace App\Service;

use App\Entity\LotGroup;
use App\Entity\LotUnit;

class PriceCalculationService
{
    /**
     * Calcule les métriques de profit pour un lot
     */
    public function calculateLotMetrics(LotGroup $lotGroup): array
    {
        $buyPrice = $lotGroup->getBuyPricePerLot();
        $sellPrice = $lotGroup->getSellPricePerLot();
        $quantity = $lotGroup->getLotSize();

        return $this->calculateMetrics($buyPrice, $sellPrice, $quantity);
    }

    /**
     * Calcule les métriques de profit pour une vente réalisée
     */
    public function calculateSaleMetrics(LotUnit $lotUnit): array
    {
        $buyPrice = $lotUnit->getLotGroup()->getBuyPricePerLot();
        $sellPrice = $lotUnit->getActualSellPrice();
        $quantity = $lotUnit->getQuantitySold();

        $metrics = $this->calculateMetrics($buyPrice, $sellPrice, $quantity);
        $metrics['isRealized'] = true;
        $metrics['saleDate'] = $lotUnit->getSoldAt();

        return $metrics;
    }

    /**
     * Calcule les métriques de base pour des prix donnés
     */
    public function calculateMetrics(int $buyPrice, int $sellPrice, int $quantity = 1): array
    {
        $profitPerUnit = $sellPrice - $buyPrice;
        $totalProfit = $profitPerUnit * $quantity;
        $totalInvestment = $buyPrice * $quantity;
        $roi = $buyPrice > 0 ? ($profitPerUnit / $buyPrice) * 100 : 0;

        return [
            'buyPrice' => $buyPrice,
            'sellPrice' => $sellPrice,
            'quantity' => $quantity,
            'profitPerUnit' => $profitPerUnit,
            'totalProfit' => $totalProfit,
            'totalInvestment' => $totalInvestment,
            'roi' => $roi,
            'profitClass' => $this->getProfitClass($totalProfit),
            'isPositive' => $totalProfit > 0,
            'isNegative' => $totalProfit < 0,
            'isNeutral' => $totalProfit === 0,
        ];
    }

    /**
     * Retourne la classe CSS appropriée selon le profit
     */
    public function getProfitClass(int $profit): string
    {
        return match (true) {
            $profit > 0 => 'text-green-400',
            $profit < 0 => 'text-red-400',
            default => 'text-gray-400'
        };
    }

    /**
     * Prépare les données d'affichage pour les templates
     */
    public function prepareDisplayData(array $metrics, string $mode = 'detailed'): array
    {
        return [
            'profit' => $metrics['totalProfit'],
            'profitFormatted' => $this->formatKamas($metrics['totalProfit']),
            'roi' => $metrics['roi'],
            'roiFormatted' => number_format($metrics['roi'], 1) . '%',
            'profitClass' => $metrics['profitClass'],
            'mode' => $mode,
            'investment' => $metrics['totalInvestment'],
            'investmentFormatted' => $this->formatKamas($metrics['totalInvestment']),
            'showRoi' => $metrics['roi'] != 0,
        ];
    }

    /**
     * Formatage optimisé des kamas avec gestion des négatifs
     */
    private function formatKamas(int $amount): string
    {
        $isNegative = $amount < 0;
        $absAmount = abs($amount);

        $formatted = match (true) {
            $absAmount >= 1_000_000_000 => number_format($absAmount / 1_000_000_000, 1, ',', ' ') . 'B',
            $absAmount >= 1_000_000 => number_format($absAmount / 1_000_000, 1, ',', ' ') . 'M',
            $absAmount >= 10_000 => number_format($absAmount / 1_000, 0, ',', ' ') . 'K',
            $absAmount >= 1_000 => number_format($absAmount / 1_000, 1, ',', ' ') . 'K',
            default => number_format($absAmount, 0, ',', ' ')
        };

        return ($isNegative ? '-' : '') . $formatted . 'K';
    }
}