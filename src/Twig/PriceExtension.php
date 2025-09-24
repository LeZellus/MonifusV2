<?php

namespace App\Twig;

use App\Entity\LotGroup;
use App\Entity\LotUnit;
use App\Service\PriceCalculationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PriceExtension extends AbstractExtension
{
    public function __construct(
        private readonly PriceCalculationService $priceCalculationService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            // Anciennes fonctions (compatibilité)
            new TwigFunction('lot_display_data', [$this, 'getLotDisplayData']),
            new TwigFunction('sale_display_data', [$this, 'getSaleDisplayData']),
            new TwigFunction('price_display_data', [$this, 'getPriceDisplayData']),

            // Nouvelles fonctions ultra-courtes
            new TwigFunction('price', [$this, 'renderPrice'], ['is_safe' => ['html']]),
            new TwigFunction('profit', [$this, 'renderProfit'], ['is_safe' => ['html']]),
            new TwigFunction('sale_profit', [$this, 'renderSaleProfit'], ['is_safe' => ['html']]),
            new TwigFunction('manual_profit', [$this, 'renderManualProfit'], ['is_safe' => ['html']]),
            new TwigFunction('financial_card', [$this, 'renderFinancialCard'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Prépare les données d'affichage pour un lot
     */
    public function getLotDisplayData(LotGroup $lotGroup, string $mode = 'detailed'): array
    {
        $metrics = $this->priceCalculationService->calculateLotMetrics($lotGroup);
        return $this->priceCalculationService->prepareDisplayData($metrics, $mode);
    }

    /**
     * Prépare les données d'affichage pour une vente
     */
    public function getSaleDisplayData(LotUnit $lotUnit, string $mode = 'detailed'): array
    {
        $metrics = $this->priceCalculationService->calculateSaleMetrics($lotUnit);
        return $this->priceCalculationService->prepareDisplayData($metrics, $mode);
    }

    /**
     * Prépare les données d'affichage pour des prix manuels
     */
    public function getPriceDisplayData(int $buyPrice, int $sellPrice, int $quantity = 1, string $mode = 'detailed'): array
    {
        $metrics = $this->priceCalculationService->calculateMetrics($buyPrice, $sellPrice, $quantity);
        return $this->priceCalculationService->prepareDisplayData($metrics, $mode);
    }

    // ===== NOUVELLES FONCTIONS ULTRA-COURTES =====

    /**
     * Affiche un prix : {{ price(lot.buyPrice, 'white', 'lg') }}
     */
    public function renderPrice(int $amount, string $color = 'green', string $size = 'base', ?string $suffix = null): string
    {
        return $this->priceCalculationService->renderPrice($amount, $color, $size, $suffix);
    }

    /**
     * Affiche un profit : {{ profit(lot, 'compact') }}
     */
    public function renderProfit(LotGroup $lot, string $mode = 'compact'): string
    {
        return $this->priceCalculationService->renderProfit($lot, $mode);
    }

    /**
     * Affiche un profit de vente : {{ sale_profit(lotUnit) }}
     */
    public function renderSaleProfit(LotUnit $sale, string $mode = 'compact'): string
    {
        return $this->priceCalculationService->renderSaleProfit($sale, $mode);
    }

    /**
     * Affiche une carte financière : {{ financial_card('Profit total', lot, 'success') }}
     */
    public function renderFinancialCard(string $label, LotGroup $lot, string $variant = 'default'): string
    {
        return $this->priceCalculationService->renderFinancialCard($label, $lot, $variant);
    }

    /**
     * Affiche un profit avec prix manuels : {{ manual_profit(1000, 1200, 10, 'simple') }}
     */
    public function renderManualProfit(int $buyPrice, int $sellPrice, int $quantity = 1, string $mode = 'compact'): string
    {
        $metrics = $this->priceCalculationService->calculateMetrics($buyPrice, $sellPrice, $quantity);
        $data = $this->priceCalculationService->prepareDisplayData($metrics, $mode);

        return match($mode) {
            'simple' => "<span class=\"{$data['profitClass']} font-medium\">{$data['profitFormatted']}</span>",
            'compact' => $this->renderProfitCompactHtml($data),
            default => $this->renderProfitDetailedHtml($data)
        };
    }

    private function renderProfitCompactHtml(array $data): string
    {
        $roi = $data['showRoi'] ? "<div class=\"{$data['profitClass']} text-xs opacity-75\">({$data['roiFormatted']})</div>" : '';
        return <<<HTML
        <div class="text-center">
            <div class="{$data['profitClass']} font-bold">{$data['profitFormatted']}</div>
            {$roi}
        </div>
        HTML;
    }

    private function renderProfitDetailedHtml(array $data): string
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