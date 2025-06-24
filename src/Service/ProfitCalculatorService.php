<?php

namespace App\Service;

use App\Entity\LotGroup;
use App\Entity\LotUnit;

class ProfitCalculatorService
{
    // ===== CALCULS DE BASE =====
    
    /**
     * Calcule l'investissement total
     */
    public function calculateInvestment(int $quantity, int $buyPricePerLot): int
    {
        return $quantity * $buyPricePerLot;
    }
    
    /**
     * Calcule le profit par lot
     */
    public function calculateProfitPerLot(int $sellPrice, int $buyPrice): int
    {
        return $sellPrice - $buyPrice;
    }
    
    /**
     * Calcule le profit total
     */
    public function calculateTotalProfit(int $quantity, int $sellPrice, int $buyPrice): int
    {
        return $quantity * ($sellPrice - $buyPrice);
    }
    
    /**
     * Calcule le ROI en pourcentage
     */
    public function calculateROI(int $buyPrice, int $sellPrice): float
    {
        if ($buyPrice <= 0) {
            return 0.0;
        }
        return (($sellPrice - $buyPrice) / $buyPrice) * 100;
    }
    
    // ===== CALCULS POUR ENTITÉS =====
    
    /**
     * Calcule tous les métriques d'un LotGroup
     */
    public function calculateLotGroupMetrics(LotGroup $lotGroup): array
    {
        $buyPrice = $lotGroup->getBuyPricePerLot();
        $sellPrice = $lotGroup->getSellPricePerLot() ?? 0;
        $quantity = $lotGroup->getLotSize();
        
        return [
            'investment' => $this->calculateInvestment($quantity, $buyPrice),
            'profitPerLot' => $this->calculateProfitPerLot($sellPrice, $buyPrice),
            'totalProfit' => $this->calculateTotalProfit($quantity, $sellPrice, $buyPrice),
            'roi' => $this->calculateROI($buyPrice, $sellPrice),
            'quantity' => $quantity,
            'buyPrice' => $buyPrice,
            'sellPrice' => $sellPrice
        ];
    }
    
    /**
     * Calcule les métriques d'une vente (LotUnit)
     */
    public function calculateSaleMetrics(LotUnit $lotUnit): array
    {
        $lotGroup = $lotUnit->getLotGroup();
        $buyPrice = $lotGroup->getBuyPricePerLot();
        $actualSellPrice = $lotUnit->getActualSellPrice();
        $quantity = $lotUnit->getQuantitySold();
        $expectedSellPrice = $lotGroup->getSellPricePerLot() ?? 0;
        
        // Profit réalisé
        $realizedProfit = $this->calculateTotalProfit($quantity, $actualSellPrice, $buyPrice);
        
        // Performance vs prévu
        $expectedProfit = $this->calculateTotalProfit($quantity, $expectedSellPrice, $buyPrice);
        $performanceDiff = $realizedProfit - $expectedProfit;
        
        return [
            'investment' => $this->calculateInvestment($quantity, $buyPrice),
            'realizedProfit' => $realizedProfit,
            'expectedProfit' => $expectedProfit,
            'performanceDiff' => $performanceDiff,
            'actualROI' => $this->calculateROI($buyPrice, $actualSellPrice),
            'expectedROI' => $this->calculateROI($buyPrice, $expectedSellPrice),
            'quantity' => $quantity,
            'buyPrice' => $buyPrice,
            'actualSellPrice' => $actualSellPrice,
            'expectedSellPrice' => $expectedSellPrice
        ];
    }
    
    // ===== FORMATAGE =====
    
    /**
     * Formate un montant en kamas
     */
    public function formatKamas(int $amount): string
    {
        if (abs($amount) >= 1000000) {
            return number_format($amount / 1000000, 1) . 'M';
        } elseif (abs($amount) >= 1000) {
            return number_format($amount / 1000, 0) . 'k';
        }
        return number_format($amount);
    }
    
    /**
     * Retourne la classe CSS selon le profit
     */
    public function getProfitClass(int $profit): string
    {
        return $profit >= 0 ? 'text-green-400' : 'text-red-400';
    }
    
    /**
     * Détermine la couleur selon le ROI
     */
    public function getROIColor(float $roi): string
    {
        if ($roi >= 20) return 'green';
        if ($roi >= 5) return 'orange';
        return 'red';
    }
    
    // ===== CALCULS COMPLEXES =====
    
    /**
     * Calcule les stats d'un utilisateur
     */
    public function calculateUserStats(array $lotGroups): array
    {
        $totalInvested = 0;
        $totalRealizedProfit = 0;
        $totalPotentialProfit = 0;
        $availableLots = 0;
        $soldLots = 0;
        
        foreach ($lotGroups as $lotGroup) {
            $metrics = $this->calculateLotGroupMetrics($lotGroup);
            
            $totalInvested += $metrics['investment'];
            
            if ($lotGroup->getStatus()->value === 'sold') {
                $soldLots += $lotGroup->getLotSize();
                // Pour les vendus, utiliser le prix réel si disponible
                $lotUnits = $lotGroup->getLotUnits();
                if (!$lotUnits->isEmpty()) {
                    foreach ($lotUnits as $unit) {
                        $saleMetrics = $this->calculateSaleMetrics($unit);
                        $totalRealizedProfit += $saleMetrics['realizedProfit'];
                    }
                } else {
                    $totalRealizedProfit += $metrics['totalProfit'];
                }
            } else {
                $availableLots += $lotGroup->getLotSize();
                $totalPotentialProfit += $metrics['totalProfit'];
            }
        }
        
        $totalLots = $availableLots + $soldLots;
        $saleRate = $totalLots > 0 ? ($soldLots / $totalLots) * 100 : 0;
        $totalProfit = $totalRealizedProfit + $totalPotentialProfit;
        $roi = $totalInvested > 0 ? ($totalProfit / $totalInvested) * 100 : 0;
        
        return [
            'totalInvested' => $totalInvested,
            'realizedProfit' => $totalRealizedProfit,
            'potentialProfit' => $totalPotentialProfit,
            'totalProfit' => $totalProfit,
            'availableLots' => $availableLots,
            'soldLots' => $soldLots,
            'totalLots' => $totalLots,
            'saleRate' => $saleRate,
            'roi' => $roi
        ];
    }
    
    /**
     * Données pour JavaScript (pour les formulaires temps réel)
     */
    public function getJavaScriptData(array $data = []): array
    {
        $quantity = $data['quantity'] ?? 0;
        $buyPrice = $data['buyPrice'] ?? 0;
        $sellPrice = $data['sellPrice'] ?? 0;
        
        return [
            'investment' => $this->calculateInvestment($quantity, $buyPrice),
            'profitPerLot' => $this->calculateProfitPerLot($sellPrice, $buyPrice),
            'totalProfit' => $this->calculateTotalProfit($quantity, $sellPrice, $buyPrice),
            'roi' => $this->calculateROI($buyPrice, $sellPrice),
            'formattedInvestment' => $this->formatKamas($this->calculateInvestment($quantity, $buyPrice)),
            'formattedProfitPerLot' => $this->formatKamas($this->calculateProfitPerLot($sellPrice, $buyPrice)),
            'formattedTotalProfit' => $this->formatKamas($this->calculateTotalProfit($quantity, $sellPrice, $buyPrice)),
            'profitClass' => $this->getProfitClass($this->calculateTotalProfit($quantity, $sellPrice, $buyPrice))
        ];
    }
}