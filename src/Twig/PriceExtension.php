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
            new TwigFunction('lot_display_data', [$this, 'getLotDisplayData']),
            new TwigFunction('sale_display_data', [$this, 'getSaleDisplayData']),
            new TwigFunction('price_display_data', [$this, 'getPriceDisplayData']),
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
}