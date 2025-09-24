<?php

namespace App\Service;

use App\Entity\LotGroup;
use App\Entity\LotUnit;

class PriceCalculationService
{
    private const COLOR_CLASSES = [
        'white' => 'text-white',
        'green' => 'text-green-400',
        'blue' => 'text-blue-400',
        'red' => 'text-red-400',
        'orange' => 'text-orange-400',
        'yellow' => 'text-yellow-400',
        'purple' => 'text-purple-400',
        'gray' => 'text-gray-400'
    ];

    private const SIZE_CLASSES = [
        'xs' => 'text-xs',
        'sm' => 'text-sm', 'small' => 'text-sm',
        'base' => 'text-base', 'normal' => 'text-base',
        'lg' => 'text-lg', 'large' => 'text-lg',
        'xl' => 'text-xl'
    ];

    private const VARIANT_CLASSES = [
        'default' => 'price-card',
        'success' => 'price-card border-green-500/30 bg-green-500/5',
        'warning' => 'price-card border-orange-500/30 bg-orange-500/5',
        'danger' => 'price-card border-red-500/30 bg-red-500/5'
    ];
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
     * Formatage des kamas pour Dofus (1000K = 1000 kamas, pas 1K)
     */
    private function formatKamas(int $amount): string
    {
        $isNegative = $amount < 0;
        $absAmount = abs($amount);

        $formatted = match (true) {
            $absAmount >= 1_000_000_000 => number_format($absAmount / 1_000_000_000, 1, ',', ' ') . 'MK',
            $absAmount >= 1_000_000 => number_format($absAmount / 1_000_000, 1, ',', ' ') . 'MK',
            default => number_format($absAmount, 0, ',', ' ') . 'K'
        };

        return ($isNegative ? '-' : '') . $formatted;
    }

    /**
     * Génère le HTML pour un prix simple
     */
    public function renderPrice(int $amount, string $color = 'green', string $size = 'base', ?string $suffix = null): string
    {
        $colorClass = self::COLOR_CLASSES[$color] ?? self::COLOR_CLASSES['green'];
        $sizeClass = self::SIZE_CLASSES[$size] ?? self::SIZE_CLASSES['base'];
        $formatted = $this->formatKamas($amount);

        $html = "<span class=\"{$colorClass} {$sizeClass} font-medium\">{$formatted}</span>";
        if ($suffix) {
            $html .= "<span class=\"text-gray-400 text-sm ml-1\">{$suffix}</span>";
        }

        return $html;
    }

    /**
     * Génère le HTML pour un profit avec ROI
     */
    public function renderProfit(LotGroup $lot, string $mode = 'compact'): string
    {
        $metrics = $this->calculateLotMetrics($lot);
        $data = $this->prepareDisplayData($metrics, $mode);

        return match($mode) {
            'simple' => "<span class=\"{$data['profitClass']} font-medium\">{$data['profitFormatted']}</span>",
            'compact' => $this->renderProfitCompact($data),
            default => $this->renderProfitDetailed($data)
        };
    }

    /**
     * Génère le HTML pour un profit de vente
     */
    public function renderSaleProfit(LotUnit $sale, string $mode = 'compact'): string
    {
        $metrics = $this->calculateSaleMetrics($sale);
        $data = $this->prepareDisplayData($metrics, $mode);

        return match($mode) {
            'simple' => "<span class=\"{$data['profitClass']} font-medium\">{$data['profitFormatted']}</span>",
            'compact' => $this->renderProfitCompact($data),
            default => $this->renderProfitDetailed($data)
        };
    }

    /**
     * Génère une carte financière
     */
    public function renderFinancialCard(string $label, LotGroup $lot, string $variant = 'default'): string
    {
        $variantClass = self::VARIANT_CLASSES[$variant] ?? self::VARIANT_CLASSES['default'];
        $profitHtml = $this->renderProfit($lot, 'compact');

        return <<<HTML
        <div class="{$variantClass}">
            <div class="price-label">{$label}</div>
            {$profitHtml}
        </div>
        HTML;
    }

    private function renderProfitCompact(array $data): string
    {
        $roi = $data['showRoi'] ? "<div class=\"{$data['profitClass']} text-xs opacity-75\">({$data['roiFormatted']})</div>" : '';
        return <<<HTML
        <div class="text-center">
            <div class="{$data['profitClass']} font-bold">{$data['profitFormatted']}</div>
            {$roi}
        </div>
        HTML;
    }

    private function renderProfitDetailed(array $data): string
    {
        $roi = $data['showRoi'] ? "<div class=\"{$data['profitClass']} text-sm opacity-80\">ROI: {$data['roiFormatted']}</div>" : '';
        return <<<HTML
        <div class="space-y-1">
            <div class="{$data['profitClass']} font-medium text-lg">{$data['profitFormatted']}</div>
            {$roi}
        </div>
        HTML;
    }
}